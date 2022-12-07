<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use Exception;
use SoapClient as SoapClientBase;
use SoapFault;

/**
 * A replacement SoapClient which traces requests
 *
 * It's not possible to implement as a decorator
 *   * we need to override __doRequest
 *   * we need to make sure options['trace'] is true (can set trace property via reflection)
 */
class SoapClient extends SoapClientBase
{
    private $debug;
    protected $icon = 'fa fa-exchange';

    /** @var \DOMDocument */
    private $dom;

    /**
     * Constructor
     *
     * new options:
     *    list_functions: (false)
     *    list_types: (false)
     *
     * @param string $wsdl    URI of the WSDL file or NULL if working in non-WSDL mode.
     * @param array  $options Array of options
     * @param Debug  $debug   (optional) Specify PHPDebugConsole instance
     *                            if not passed, will create Soap channel on singleton instance
     *                            if root channel is specified, will create a Soap channel
     *
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch
     */
    public function __construct($wsdl, $options = array(), Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('Soap', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Soap', array('channelIcon' => $this->icon));
        }
        $this->debug = $debug;
        $this->dom = new \DOMDocument();
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;
        $debug->addPlugin($debug->pluginHighlight);
        $options['trace'] = true;
        $exception = null;
        try {
            parent::__construct($wsdl, $options);
        } catch (Exception $exception) {
            // rethrow below
        }
        $this->logConstruct($wsdl, $options, $exception);
        if ($exception) {
            throw $exception;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch
     */
    #[\ReturnTypeWillChange]
    public function __call($name, $args)
    {
        $exception = null;
        try {
            $return = parent::__call($name, $args);
        } catch (SoapFault $exception) {
            // we'll rethrow bellow
        }
        $this->logReqRes($name, $exception);
        if ($exception) {
            throw $exception;
        }
        return $return;
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function __doRequest($request, $location, $action, $version, $oneWay = 0)
    {
        $exception = null;
        try {
            $xmlResponse = parent::__doRequest($request, $location, $action, $version, $oneWay);
        } catch (SoapFault $e) {
            // we'll rethrow bellow
        }
        $this->setLastRequest($request);
        $this->setLastResponse($xmlResponse);
        if ($this->isViaCall() === false) {
            // __doRequest called directly
            $this->logReqRes($action, $exception, true);
        }
        if ($exception) {
            throw $exception;
        }
        return $xmlResponse;
    }

    /**
     * Get defined types keyed by name
     *
     * @return array
     */
    private function debugGetFunctions()
    {
        return \array_map(static function ($val) {
            $matches = null;
            if (\preg_match('/^(\w+) (.+)$/s', $val, $matches)) {
                $val = $matches[2] . ': ' . $matches[1];
            }
            return $val;
        }, $this->__getFunctions());
    }

    /**
     * Get defined types keyed by name
     *
     * @return array
     */
    private function debugGetTypes()
    {
        $types = array();
        $matches = null;
        foreach ($this->__getTypes() as $val) {
            $val = \preg_replace('/\bboolean\b/', 'bool', $val);
            if (\preg_match('/^struct ([^{]+) (.+)$/s', $val, $matches)) {
                $key = $matches[1];
                $types[$key] = 'struct ' . $matches[2];
                continue;
            }
            $types[] = $val;
        }
        \ksort($types);
        return $types;
    }

    /**
     * Get whitespace formatted request xml
     *
     * @param string $action Populated with  SOAP action
     *
     * @return string|null XML
     */
    private function debugGetXmlRequest(&$action)
    {
        $requestXml = $this->__getLastRequest();
        if (!$requestXml) {
            return null;
        }
        \set_error_handler(static function () {
            // suppress DOMDocument::loadXML warnings
        });
        $this->dom->loadXML($requestXml);
        \restore_error_handler();
        if (!$action) {
            $envelope = $this->dom->childNodes->item(0);
            $body = $envelope->childNodes->item(0)->localName !== 'Header'
                ? $envelope->childNodes->item(0)
                : $envelope->childNodes->item(1);
            $action = $body->childNodes->item(0)->localName;
        }
        return $this->dom->saveXML();
    }

    /**
     * Get whitespace formatted response xml
     *
     * @param mixed $faultInfo Populated with Fault info
     *
     * @return string|null XML
     */
    private function debugGetXmlResponse(&$faultInfo)
    {
        $responseXml = $this->__getLastResponse();
        if (!$responseXml) {
            return null;
        }
        $this->dom->loadXML($responseXml);

        /*
        SOAP_1_1 :
            namespace:  "http://schemas.xmlsoap.org/soap/envelope/"
            prefix:  "SOAP-ENV"
                faultcode / faultstring / faultactor / detail
        SOAP_1_2 :
            namespace:  "http://www.w3.org/2003/05/soap-envelope"
            prefix:  "env"
                Code / Reason / Detail
        */

        $prefix = $this->dom->childNodes->item(0)->prefix;
        $soapVer = $prefix === 'env'
            ? SOAP_1_2
            : SOAP_1_1;
        $fault = $this->dom->getElementsByTagName('Fault');
        if ($fault->length) {
            $fault = $fault->item(0);
            $faultInfo = $soapVer === SOAP_1_2
                ? array(
                    'code' => $fault->getElementsByTagName('Code')->item(0)->textContent,
                    'reason' => $fault->getElementsByTagName('Reason')->item(0)->textContent,
                )
                : array(
                    'code' => $fault->getElementsByTagName('faultcode')->item(0)->textContent,
                    'reason' => $fault->getElementsByTagName('faultstring')->item(0)->textContent,
                );
        }
        return $this->dom->saveXML();
    }

    /**
     * Check if __call is in backtracew
     *
     * @return bool
     */
    private function isViaCall()
    {
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($backtrace as $frame) {
            $frame = \array_merge(array(
                'function' => null,
                'class' => null,
                'type' => null,
            ), $frame);
            $func = $frame['class'] . $frame['type'] . $frame['function'];
            if ($func === 'SoapClient->__call') {
                return true;
            }
        }
        return false;
    }

    /**
     * Log constructor
     *
     * @param string         $wsdl      URI of the WSDL file or NULL if working in non-WSDL mode.
     * @param array          $options   Array of options
     * @param Exception|null $exception Exception (if thrown)
     *
     * @return void
     */
    private function logConstruct($wsdl, $options, Exception $exception = null)
    {
        $this->debug->groupCollapsed('SoapClient::__construct', $wsdl ?: 'non-WSDL mode', $this->debug->meta('icon', $this->icon));
        if ($wsdl && !empty($options['list_functions'])) {
            $this->debug->log(
                'functions',
                $this->debug->abstracter->crateWithVals(
                    $this->debugGetFunctions(),
                    array(
                        'options' => array(
                            'showListKeys' => false,
                        ),
                    )
                )
            );
        }
        if ($wsdl && !empty($options['list_types'])) {
            $this->debug->log('types', $this->debugGetTypes());
        }
        if ($exception) {
            $this->debug->warn(\get_class($exception), \trim($exception->getMessage()));
        }
        $this->debug->groupEnd();
    }

    /**
     * Log SOAP request and response
     *
     * @param string         $action         Soap action
     * @param Exception|null $exception      Caught exception
     * @param bool           $logParsedFault Whether to add log entry for found Fault
     *
     * @return void
     */
    private function logReqRes($action, Exception $exception = null, $logParsedFault = false)
    {
        $fault = null;
        $xmlRequest = $this->debugGetXmlRequest($action);
        $xmlResponse = $this->debugGetXmlResponse($fault);
        $this->debug->groupCollapsed('soap', $action, $this->debug->meta('icon', $this->icon));
        if ($xmlRequest) {
            $this->debug->log('request headers', $this->__getLastRequestHeaders(), $this->debug->meta('redact'));
            $this->logXml('request body', $xmlRequest);
        }
        $responseHeaders = $this->__getLastResponseHeaders();
        if ($responseHeaders) {
            $this->debug->log('response headers', $responseHeaders, $this->debug->meta('redact'));
        }
        if ($xmlResponse) {
            $this->logXml('response body', $xmlResponse);
        }
        if ($exception) {
            $this->debug->warn(\get_class($exception), \trim($exception->getMessage()));
        } elseif ($logParsedFault && $fault) {
            $this->debug->warn('SoapFault', $fault['reason']);
        }
        $this->debug->groupEnd();
    }

    /**
     * Log XML request or response
     *
     * @param string $label log label
     * @param string $xml   XML
     *
     * @return void
     */
    private function logXml($label, $xml)
    {
        $this->debug->log(
            $label,
            new Abstraction(Abstracter::TYPE_STRING, array(
                'value' => $xml,
                'attribs' => array(
                    'class' => 'highlight language-xml',
                ),
                'addQuotes' => false,
                'visualWhiteSpace' => false,
            )),
            $this->debug->meta(array(
                'attribs' => array(
                    'class' => 'no-indent',
                ),
                'redact' => true,
            ))
        );
    }

    /**
     * Set last request so that __getLastRequest() avail from within __doRequest
     *
     * @param string $request XML request
     *
     * @return void
     */
    private function setLastRequest($request)
    {
        if (PHP_VERSION_ID >= 80100) {
            $lastRequestRef = new \ReflectionProperty('SoapClient', '__last_request');
            $lastRequestRef->setAccessible(true);
            $lastRequestRef->setValue($this, $request);
            return;
        }
        $this->__last_request = $request;
    }

    /**
     * Set last response so that __getLastResponse() avail from within __doRequest
     *
     * @param string $response XML response
     *
     * @return void
     */
    private function setLastResponse($response)
    {
        if (PHP_VERSION_ID >= 80100) {
            $lastResponseRef = new \ReflectionProperty('SoapClient', '__last_response');
            $lastResponseRef->setAccessible(true);
            $lastResponseRef->setValue($this, $response);
            return;
        }
        $this->__last_response = $response;
    }
}

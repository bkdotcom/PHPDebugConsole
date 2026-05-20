<?php

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Proxy\ListenerInterface;
use DOMDocument;
use ReflectionClass;
use SoapClient as SoapClientBase;

/**
 * Listener for SoapClient proxy
 */
class SoapClientProxyListener implements ListenerInterface
{
    /** @var Debug */
    private $debug;

    /** @var DOMDocument */
    private $dom;

    /** @var Exception|null */
    private $exception = null;

    /** @var array */
    private $initValues = array();

    /** @var string */
    protected $icon = ':send-receive:';

    /** @var SoapClientBase */
    private $subject;

    /**
     * Constructor
     *
     * @param Debug|null $debug (optional) $debug instance
     */
    public function __construct($debug = null)
    {
        if (!$debug) {
            $debug = Debug::getChannel('Soap', array('icon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Soap', array('icon' => $this->icon));
        }
        $this->debug = $debug;

        $this->dom = new DOMDocument();
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;
        $debug->rootInstance->addPlugin($debug->pluginHighlight, 'highlight');
    }

    /**
     * {@inheritDoc}
     */
    public function afterCall($methodName, array $arguments, $result, array $initValues, $exception = null)
    {
        $handlerMethod = 'afterCall' . \str_replace(' ', '', \ucwords(\str_replace('_', ' ', $methodName)));
        if (\method_exists($this, $handlerMethod)) {
            $this->initValues = $initValues;
            $this->exception = $exception;
            $this->$handlerMethod($arguments, $result);
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function init($subject, $proxy)
    {
        $this->subject = $subject;
        $refClass = new ReflectionClass($this->subject);
        if ($refClass->hasProperty('trace')) {
            $this->debug->reflection->propSet($this->subject, 'trace', true);
        }
    }

    /**
     * Log constructor
     *
     * @param array $args   Constructor arguments
     * @param array $result Constructor result (unused)
     *
     * @return void
     */
    public function afterCallConstruct(array $args, $result)
    {
        $wsdl = $args[0];
        $options = $args[1];

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
            $this->debug->log($this->debug->i18n->trans('word.types'), $this->debugGetTypes());
        }
        if ($this->exception) {
            $this->debug->warn(\get_class($this->exception), \trim($this->exception->getMessage()));
        }
        $this->debug->groupEnd();
    }

    protected function afterCallCall(array $args, $result)
    {
        $this->logReqRes($args[0]);
        return $result;
    }

    protected function afterCallDoRequest(array $args, $result)
    {
        $this->setLastRequest($args[0]);
        $this->setLastResponse($result);
        if ($this->isViaCall() === false) {
            // __doRequest called directly
            $this->logReqRes($args[2], true);
        }
        return $result;
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
        }, $this->subject->__getFunctions());
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
        foreach ($this->subject->__getTypes() as $val) {
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
        $requestXml = $this->subject->__getLastRequest();
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
        $responseXml = $this->subject->__getLastResponse();
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
     * Check if __call is in backtrace
     *
     * @return bool
     */
    private function isViaCall()
    {
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($backtrace as $frame) {
            $frame = \array_merge(array(
                'class' => null,
                'function' => null,
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
     * Log SOAP request and response
     *
     * @param string $action         Soap action
     * @param bool   $logParsedFault Whether to add log entry for found Fault
     *
     * @return void
     */
    private function logReqRes($action, $logParsedFault = false)
    {
        $fault = null;
        $xmlRequest = $this->debugGetXmlRequest($action);
        $xmlResponse = $this->debugGetXmlResponse($fault);
        $this->debug->groupCollapsed('soap', $action, $this->debug->meta('icon', $this->icon));
        $this->debug->time(\microtime(true) - $this->initValues['timeStart']);

        if ($xmlRequest) {
            $headers = $this->subject->__getLastRequestHeaders();
            $this->debug->log($this->debug->i18n->trans('request.headers'), $this->debug->redactHeaders($headers));
            $this->logXml($this->debug->i18n->trans('request.body'), $xmlRequest);
        }
        $responseHeaders = $this->subject->__getLastResponseHeaders();
        if ($responseHeaders) {
            $this->debug->log($this->debug->i18n->trans('response.headers'), $responseHeaders, $this->debug->meta('redact'));
        }
        if ($xmlResponse) {
            $this->logXml($this->debug->i18n->trans('response.body'), $xmlResponse);
        }
        if ($this->exception) {
            $this->debug->warn(\get_class($this->exception), \trim($this->exception->getMessage()));
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
            new Abstraction(Type::TYPE_STRING, array(
                'addQuotes' => false,
                'attribs' => array(
                    'class' => 'highlight language-xml',
                ),
                'value' => $xml,
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
        $classRef = new ReflectionClass('SoapClient');
        $classRef->hasProperty('__last_request') || PHP_VERSION_ID >= 80100
            ? $this->debug->reflection->propSet($this->subject, '__last_request', $request)
            : $this->subject->__last_request = $request;
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
        $classRef = new ReflectionClass('SoapClient');
        $classRef->hasProperty('__last_response') || PHP_VERSION_ID >= 80100
            ? $this->debug->reflection->propSet($this->subject, '__last_response', $response)
            : $this->subject->__last_response = $response;
    }
}

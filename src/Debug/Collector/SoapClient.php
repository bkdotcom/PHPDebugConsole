<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Plugin\Highlight;

/**
 * A replacement SoapClient which traces requests
 *
 * It's not possible to implement as a decorator as we need to make sure options['trace'] is true
 */
class SoapClient extends \SoapClient
{
    private $debug;
    protected $icon = 'fa fa-exchange';

    /** @var \DOMDocument */
    private $dom;

    /**
     * Constructor
     *
     * @param string $wsdl    URI of the WSDL file or NULL if working in non-WSDL mode.
     * @param array  $options Array of options
     * @param Debug  $debug   (optional) Specify PHPDebugConsole instance
     *                            if not passed, will create Soap channnel on singleton instance
     *                            if root channel is specified, will create a Soap channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($wsdl, $options = array(), Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('Soap', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Soap', array('channelIcon' => $this->icon));
        }
        $this->debug = $debug;
        $this->debug->addPlugin(new Highlight());
        $options['trace'] = true;
        parent::__construct($wsdl, $options);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function __doRequest($request, $location, $action, $version, $oneWay = 0)
    {
        $this->dom = new \DOMDocument();
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;

        $xmlResponse = parent::__doRequest($request, $location, $action, $version, $oneWay);
        $xmlRequest = $this->getDebugXmlRequest($action);

        $this->debug->groupCollapsed('soap', $action, $this->debug->meta('icon', $this->icon));
        $this->logRequest($xmlRequest);
        $this->logResponse($xmlResponse);
        $this->debug->groupEnd();

        return $xmlResponse;
    }

    /**
     * Get whitespace formatted request xml
     *
     * @param string $action The SOAP action
     *
     * @return string XML
     */
    private function getDebugXmlRequest(&$action)
    {
        $this->dom->loadXML($this->__getLastRequest());
        if (!$action) {
            $envelope = $this->dom->childNodes[0];
            $body = $envelope->childNodes[0]->localName !== 'Header'
                ? $envelope->childNodes[0]
                : $envelope->childNodes[1];
            $action = $body->childNodes[0]->localName;
        }
        return $this->dom->saveXML();
    }

    /**
     * Get whitespace formatted response xml
     *
     * @param string $response XML response
     *
     * @return string XML
     */
    private function getDebugXmlResponse($response)
    {
        if (!$response) {
            return '';
        }
        $this->dom->loadXML($response);
        $xmlResponse = $this->dom->saveXML();
        /*
            determine if soapFault
            a bit tricky from within __doRequest
        */
        $fault = $this->dom->getElementsByTagNameNS('http://schemas.xmlsoap.org/soap/envelope/', 'Fault');
        if ($fault->length) {
            $vals = array();
            foreach ($fault[0]->childNodes as $node) {
                $vals[$node->localName] = $node->nodeValue;
            }
            $this->debug->warn('soapFault', $vals);
        }
        return $xmlResponse;
    }

    /**
     * Log request headers and body
     *
     * @param string $xmlRequest XML
     *
     * @return void
     */
    private function logRequest($xmlRequest)
    {
        $this->debug->log('request headers', $this->__getLastRequestHeaders(), $this->debug->meta('redact'));
        $this->logXml('request body', $xmlRequest);
    }

    /**
     * Log response headers and body
     *
     * @param string $xmlResponse XML
     *
     * @return void
     */
    private function logResponse($xmlResponse)
    {
        $this->debug->log('response headers', $this->__getLastResponseHeaders(), $this->debug->meta('redact'));
        $xmlResponse = $this->getDebugXmlResponse($xmlResponse);
        $this->logXml('response body', $xmlResponse);
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
}

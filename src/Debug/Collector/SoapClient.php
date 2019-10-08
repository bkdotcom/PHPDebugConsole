<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Plugin\Prism;

/**
 * A replacement SoapClient which traces requests
 */
class SoapClient extends \SoapClient
{

    private $debug;
    protected $icon = 'fa fa-exchange';

    /**
     * Constructor
     *
     * @param string $wsdl    URI of the WSDL file or NULL if working in non-WSDL mode.
     * @param array  $options Array of options
     * @param Debug  $debug   (optional) Specify PHPDebugConsole instance
     *                            if not passed, will create Soap channnel on singleton instance
     *                            if root channel is specified, will create a Soap channel
     */
    public function __construct($wsdl, $options = array(), Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('Soap', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Soap', array('channelIcon' => $this->icon));
        }
        $this->debug = $debug;
        $this->debug->addPlugin(new Prism());
        $options['trace'] = true;
        parent::__construct($wsdl, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $return = parent::__doRequest($request, $location, $action, $version, $one_way);
        $debug = $this->debug;

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $dom->loadXML($this->__getLastRequest());
        $xmlRequest = $dom->saveXML();
        if (!$action) {
            $action = $dom->childNodes[0]->childNodes[0]->childNodes[0]->localName;
        }

        $dom->loadXML($return);
        $xmlResponse = $dom->saveXML();

        $debug->groupCollapsed('soap', $action, $debug->meta('icon', $this->icon));

        /*
            determine if soapFault
            a bit tricky from within __doRequest
        */
        $fault = $dom->getElementsByTagNameNS('http://schemas.xmlsoap.org/soap/envelope/', 'Fault');
        if ($fault->length) {
            $vals = array();
            foreach ($fault[0]->childNodes as $node) {
                $vals[$node->localName] = $node->nodeValue;
            }
            $debug->warn('soapFault', $vals);
        }

        $debug->log('request headers', $this->__getLastRequestHeaders(), $this->debug->meta('redact'));
        $debug->log(
            'request body',
            new Abstraction(array(
                'type' => 'string',
                'attribs' => array(
                    'class' => 'language-xml prism',
                ),
                'addQuotes' => false,
                'visualWhiteSpace' => false,
                'value' => $xmlRequest,
            )),
            $debug->meta(array(
                'attribs' => array(
                    'class' => 'no-indent',
                ),
                'redact' => true,
            ))
        );
        $debug->log('response headers', $this->__getLastResponseHeaders(), $this->debug->meta('redact'));
        $debug->log(
            'response body',
            new Abstraction(array(
                'type' => 'string',
                'attribs' => array(
                    'class' => 'language-xml prism',
                ),
                'addQuotes' => false,
                'visualWhiteSpace' => false,
                'value' => $xmlResponse,
            )),
            $debug->meta(array(
                'attribs' => array(
                    'class' => 'no-indent',
                ),
                'redact' => true,
            ))
        );
        $debug->groupEnd();
        return $return;
    }
}

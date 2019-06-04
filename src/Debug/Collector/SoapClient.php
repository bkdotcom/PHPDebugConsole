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

/**
 * A PDO proxy which traces statements
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
     *                            if root channel is specifyed, will create a Soap channel
     */
    public function __construct($wsdl, $options = array(), Debug $debug = null)
    {
        if (!$debug) {
            $debug = \bdk\Debug::_getChannel('Soap', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Soap', array('channelIcon' => $this->icon));
        }
        $this->debug = $debug;
        $options['trace'] = true;
        parent::__construct($wsdl, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $return = parent::__doRequest($request, $location, $action, $version, $one_way);

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

        $debug = $this->debug;
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

        $debug->log('request headers', $this->__getLastRequestHeaders());
        $debug->log('request body: <pre><code class="language-xml">'.\htmlspecialchars($xmlRequest).'</code></pre>');
        $debug->log('response headers', $this->__getLastResponseHeaders());
        $debug->log('response body: <pre><code class="language-xml">'.\htmlspecialchars($xmlResponse).'</code></pre>');
        $debug->groupEnd();
        return $return;
    }
}

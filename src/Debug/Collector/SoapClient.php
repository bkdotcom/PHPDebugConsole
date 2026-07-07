<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\SoapClientProxyListener;
use Exception;

\bdk\Debug::getInstance()->proxyManager->autoloadProxyClass('SoapClient');

/**
 * A replacement SoapClient which traces requests
 *
 * @mixin \bdk\Proxy\ProxyTrait
 * @mixin \SoapClient
 */
class SoapClient extends \SoapClientProxy
{
    /**
     * Constructor
     *
     * new options:
     *    list_functions: (false)
     *    list_types: (false)
     *
     * @param string     $wsdl    URI of the WSDL file or NULL if working in non-WSDL mode.
     * @param array      $options Array of options
     * @param Debug|null $debug   (optional) Specify Debug instance
     *                              if not passed, will create Soap channel on singleton instance
     *                              if root channel is specified, will create a Soap channel
     *
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($wsdl, $options = array(), $debug = null)
    {
        \bdk\Debug\Utility\PhpType::assertType($debug, 'bdk\Debug|null', 'debug');

        $listener = new SoapClientProxyListener($debug);
        $this->setSubject($this);
        $this->setListener($listener);

        $options['trace'] = true;
        \set_error_handler(static function () {
            // ignore errors from SoapClient constructor
        });
        $this->proxyCall(__FUNCTION__, [$wsdl, $options]);
        \restore_error_handler();
    }
}

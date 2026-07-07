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
use Curl\Curl;

\bdk\Debug::getInstance()->proxyManager->autoloadProxyClass('Curl\Curl');

/**
 * Extends auto-generated proxy class for Curl\Curl
 *
 * @mixin \bdk\Proxy\ProxyTrait
 * @mixin \Curl\Curl
 */
class PhpCurlClass extends \Curl_CurlProxy
{
    /**
     * Constructor
     *
     * Multiple signatures:
     *   __construct($base_url, $options) // Curl's constructor signature
     *   __construct($curl, $debug) // Pass an existing Curl instance
     *   __construct($options, $debug) // options for listener & debug instance
     *
     * @param string|array|Curl $baseUrl Base URL for Curl instance (or curl instance, or listener options)
     * @param array|Debug|null  $options Options for Curl instance (or debug instance)
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($baseUrl = null, $options = array())
    {
        $args = \array_replace([array(), null], \func_get_args());

        if ($baseUrl === null || \is_string($baseUrl)) {
            $subject = new Curl($baseUrl, $options);
            $listener = new PhpCurlClassProxyListener();
        } elseif ($args[0] instanceof Curl) {
            $subject = $args[0];
            $listener = new PhpCurlClassProxyListener(array(), $args[1]);
        } elseif (\is_array($args[0])) {
            $subject = new Curl();
            $listener = new PhpCurlClassProxyListener($args[0], $args[1]);
        }

        $this->setSubject($subject);
        $this->setListener($listener);
    }
}

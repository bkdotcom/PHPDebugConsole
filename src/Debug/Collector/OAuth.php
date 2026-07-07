<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b3
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use Oauth as OAuthBase;

\bdk\Debug::getInstance()->proxyManager->autoloadProxyClass('OAuth');

/**
 * OAuth client with debugging
 *
 * @mixin \bdk\Proxy\ProxyTrait
 * @mixin \OAuth
 */
class OAuth extends \OAuthProxy
{
    /**
     * Constructor
     *
     * @param string     $consumerKey     The consumer key provided by the service provider
     * @param string     $consumerSecret  The consumer secret provided by the service provide
     * @param string     $signatureMethod (OAUTH_SIG_METHOD_HMACSHA1) defines which signature method to use
     * @param int        $authType        (OAUTH_AUTH_TYPE_AUTHORIZATION) defines how to pass the OAuth parameters to a consumer
     * @param Debug|null $debug           (optional) $debug instance
     */
    public function __construct($consumerKey, $consumerSecret, $signatureMethod = OAUTH_SIG_METHOD_HMACSHA1, $authType = OAUTH_AUTH_TYPE_AUTHORIZATION, $debug = null)
    {
        $subject = new OAuthBase($consumerKey, $consumerSecret, $signatureMethod, $authType);
        $listener = new OAuthProxyListener($debug);

        $this->setSubject($subject);
        $this->setListener($listener);
    }
}

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
use Oauth as OAuthBase;
use OAuthException;

/**
 * OAuth client with debugging
 */
class OAuth extends OAuthBase
{
    protected $debugger;
    protected $icon = 'fa fa-handshake-o';
    private $elapsed;
    private $exception;

    /**
     * Constructor
     *
     * @param string $consumerKey     The consumer key provided by the service provider
     * @param string $consumerSecret  The consumer secret provided by the service provide
     * @param string $signatureMethod (OAUTH_SIG_METHOD_HMACSHA1) defines which signature method to use
     * @param int    $authType        (OAUTH_AUTH_TYPE_AUTHORIZATION) defines how to pass the OAuth parameters to a consumer
     * @param Debug  $debug           (optional) $debug instance
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($consumerKey, $consumerSecret, $signatureMethod = OAUTH_SIG_METHOD_HMACSHA1, $authType = OAUTH_AUTH_TYPE_AUTHORIZATION, $debug = null)
    {
        parent::__construct($consumerKey, $consumerSecret, $signatureMethod, $authType);
        if ($debug === null) {
            $debug = Debug::_getChannel('OAuth', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('OAuth', array('channelIcon' => $this->icon));
        }
        $this->enableDebug();
        $this->debugger = $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function fetch($protectedResourceUrl, $extraParameters = array(), $httpMethod = OAUTH_HTTP_METHOD_GET, $httpHeaders = array())
    {
        $return = $this->profileCall(__FUNCTION__, \func_get_args());
        if ($this->debug) {
            $this->debugger->groupCollapsed('OAuth::' . __FUNCTION__, $this->getHttpMethod(), $protectedResourceUrl);
            $this->logRequest($protectedResourceUrl);
            $this->debugger->groupEnd();
        }
        if ($this->exception) {
            throw $this->exception;
        }
        return $return;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccessToken($accessTokenUrl, $authSessionHandle = null, $verifierToken = null, $httpMethod = OAUTH_HTTP_METHOD_GET)
    {
        $return = $this->profileCall(__FUNCTION__, \func_get_args());
        if ($this->debug) {
            $this->debugger->groupCollapsed('OAuth::' . __FUNCTION__, $this->getHttpMethod(), $accessTokenUrl);
            $this->logRequest($accessTokenUrl);
            $this->debugger->groupEnd();
        }
        if ($this->exception) {
            throw $this->exception;
        }
        return $return;
    }

    /**
     * {@inheritDoc}
     */
    public function getRequestToken($requestTokenUrl, $callbackUrl = null, $httpMethod = OAUTH_HTTP_METHOD_GET)
    {
        $return = $this->profileCall(__FUNCTION__, \func_get_args());
        if ($this->debug) {
            $this->debugger->groupCollapsed('OAuth::' . __FUNCTION__, $this->getHttpMethod(), $requestTokenUrl);
            $this->debugger->log('callback url', $callbackUrl);
            $this->logRequest($requestTokenUrl);
            $this->debugger->groupEnd();
        }
        if ($this->exception) {
            throw $this->exception;
        }
        return $return;
    }

    /**
     * Get the http method used for last request
     *
     * @return string
     */
    private function getHttpMethod()
    {
        \preg_match('/^(\w+)/', $this->getDebugInfo()['sbs'], $matches);
        return $matches
            ? $matches[1]
            : '';
    }

    /**
     * Get debugInfo with default values
     *
     * @return array
     */
    private function getDebugInfo()
    {
        return \array_merge(array(
            'headers_sent' => '',
            'headers_recv' => '',
            'body_recv' => null,
            'body_sent' => null,
            'sbs' => '',
        ), $this->debugInfo ?: array());
    }

    /**
     * Get query params from request url
     *
     * @return array
     */
    private function getQueryParams()
    {
        $parts = \array_merge(array(
            'query' => '',
        ), \parse_url($this->getLastResponseInfo()['url']));
        $queryParams = array();
        \parse_str($parts['query'], $queryParams);
        return $queryParams;
    }

    /**
     * debugInfo + lastResponseInfo.
     * any values avail in headers or logged separately are omitted
     *
     * @param string $url requested url
     *
     * @return array
     */
    private function additionalInfo($url)
    {
        $debugInfo = \array_diff_key($this->getDebugInfo(), \array_flip(array(
            'headers_sent', 'body_sent', 'headers_recv', 'body_recv',
            // "sbs" may be only key remaining
        )));
        $lastResponseInfo = \array_merge(array(
            'size_download' => 0,
            'download_content_length' => 0,
            'url' => $url,
        ), $this->getLastResponseInfo() ?: array());
        $lastResponseInfo = \array_diff_key($lastResponseInfo, \array_filter(array(
            'content_type' => true,
            'download_content_length' => true,
            'http_code' => true,
            'size_download' => $lastResponseInfo['size_download'] === $lastResponseInfo['download_content_length'], // content length plus any overhead
            'size_upload' => isset($this->debugInfo['body_sent']) === false,
            'url' => $lastResponseInfo['url'] === $url,
        )));
        return $lastResponseInfo + $debugInfo;
    }

    /**
     * Log oauth request details
     *
     * @param string $url requested url
     *
     * @return void
     */
    private function logRequest($url)
    {
        $this->debugger->time($this->elapsed);
        $debugInfo = $this->getDebugInfo();
        // values available in the headers or elsewhere
        $this->debugger->log('OAuth Parameters', $this->oauthParams(), $this->debugger->meta('cfg', 'abstracter.stringMinLen.encoded', -1));
        $this->debugger->log('additional info', $this->additionalInfo($url));
        $this->debugger->log('request headers', $debugInfo['headers_sent'], $this->debugger->meta('icon', 'fa fa-arrow-right'));
        if (isset($debugInfo['body_sent'])) {
            $this->debugger->log('request body', $debugInfo['body_sent'], $this->debugger->meta(array(
                'icon' => 'fa fa-arrow-right',
                'redact' => true,
            )));
        }
        $this->debugger->log('response headers', $debugInfo['headers_recv'], $this->debugger->meta('icon', 'fa fa-arrow-left'));
        $this->debugger->log('response body', $debugInfo['body_recv'], $this->debugger->meta('icon', 'fa fa-arrow-left'));
        if ($this->exception) {
            $this->debugger->warn(\get_class($this->exception), $this->exception->getMessage());
        }
    }

    /**
     * Get the request's OAuth parameters
     *
     * @return array
     */
    private function oauthParams()
    {
        $oauthParamKeys = array(
            'oauth_consumer_key',
            'oauth_nonce',
            'oauth_signature',
            'oauth_signature_method',
            'oauth_timestamp',
            'oauth_token',
            'oauth_version',
        );
        $oauthParams = array();
        $debugInfo = $this->getDebugInfo();
        if (\preg_match('/^Authorization:\s+([^\r]+)/m', $debugInfo['headers_sent'], $matches)) {
            // if OAUTH_AUTH_TYPE_AUTHORIZATION, we can get params from header
            $authHeader = $matches[1];
            \preg_match_all('/(\w+)="([^"]+)"/', $authHeader, $matches, PREG_PATTERN_ORDER);
            $oauthParams = \array_map('urldecode', \array_combine($matches[1], $matches[2]));
        } elseif ($debugInfo['sbs']) {
            // get params from Signature Base String
            $sbsParsed = array();
            \parse_str(\urldecode($debugInfo['sbs']), $sbsParsed);
            $oauthParams = \array_intersect_key($sbsParsed + $this->getQueryParams(), \array_flip($oauthParamKeys));
        }
        \ksort($oauthParams);
        return $oauthParams;
    }

    /**
     * Call oauth method
     * capture elapsed time and any thrown exception
     *
     * @param string $method Method being called
     * @param array  $args   Arguments
     *
     * @return array|false
     */
    private function profileCall($method, $args)
    {
        $this->exception = null;
        $return = false;
        $this->debugger->time();
        try {
            $return = \call_user_func_array(array('OAuth', $method), $args);
        } catch (OAuthException $e) {
            $this->exception = $e;
        }
        $this->elapsed = $this->debugger->timeEnd($this->debugger->meta('silent'));
        return $return;
    }
}

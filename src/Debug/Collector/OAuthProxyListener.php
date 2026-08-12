<?php

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Plugin\Redaction;
use bdk\HttpMessage\Utility\Uri as UriUtils;
use bdk\Proxy\ListenerInterface;
use Exception;
use OAuth as OAuthBase;

/**
 * Listener for OAuth proxy
 *
 *     $oauth = new Oauth(...);
 *     $oauth = $debug->proxyManager->buildFromSubject($oauth);
 *     $oauth->setHandler(new OAuthProxyListener($debug));
 */
class OAuthProxyListener implements ListenerInterface
{
    const OAUTH_CLASSNAME = 'OAuth';

    /** @var string */
    protected $icon = ':authorize:';

    /** @var Debug */
    private $debug;

    /** @var Exception|null */
    private $exception = null;

    /** @var array */
    private $initValues = array();

    /** @var OAuth */
    private $subject;

    /**
     * Constructor
     *
     * @param Debug|null $debug (optional) $debug instance
     */
    public function __construct($debug = null)
    {
        $channelKey = self::OAUTH_CLASSNAME;
        $channelOptions = array(
            'channelIcon' => $this->icon,
            'channelName' => 'OAuth',
        );
        if ($debug === null) {
            $debug = Debug::getChannel($channelKey, $channelOptions);
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel($channelKey, $channelOptions);
        }
        $this->debug = $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function afterCall($methodName, array $arguments, $result, array $initValues, $exception = null)
    {
        $listenerMethod = 'afterCall' . \str_replace(' ', '', \ucwords(\str_replace('_', ' ', $methodName)));
        if (\method_exists($this, $listenerMethod)) {
            $this->initValues = $initValues;
            $this->exception = $exception;
            $this->$listenerMethod($arguments, $result);
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function init($subject, $proxy)
    {
        $this->subject = $subject;
        $this->subject->enableDebug();
    }

    /**
     * Log calls to OAuth::fetch
     *
     * @param array $arguments Arguments passed to OAuth::fetch
     *
     * @return void
     */
    protected function afterCallFetch(array $arguments)
    {
        $this->debug->groupCollapsed(self::OAUTH_CLASSNAME . '::fetch', $this->getHttpMethod(), $arguments[0]);
        $this->logRequest($arguments[0]);
        $this->debug->groupEnd();
    }

    /**
     * Log calls to OAuth::getAccessToken
     *
     * @param array $arguments Arguments passed to OAuth::getAccessToken
     *
     * @return void
     */
    protected function afterCallGetAccessToken(array $arguments)
    {
        $this->debug->groupCollapsed(self::OAUTH_CLASSNAME . '::getAccessToken', $this->getHttpMethod(), $arguments[0]);
        $this->logRequest($arguments[0]);
        $this->debug->groupEnd();
    }

    /**
     * Log calls to OAuth::getRequestToken
     *
     * @param array $arguments Arguments passed to OAuth::getRequestToken
     *
     * @return void
     */
    protected function afterCallGetRequestToken(array $arguments)
    {
        $this->debug->groupCollapsed(self::OAUTH_CLASSNAME . '::getRequestToken', $this->getHttpMethod(), $arguments[0]);
        $this->debug->log($this->debug->i18n->trans('oauth.callback_url'), $arguments[1]);
        $this->logRequest($arguments[0]);
        $this->debug->groupEnd();
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
            'body_recv' => null,
            'body_sent' => null,
            'headers_recv' => '',
            'headers_sent' => '',
            'sbs' => '',
        ), $this->subject->debugInfo ?: array());
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
        ), UriUtils::parseUrl($this->subject->getLastResponseInfo()['url']));
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
        $debugInfoAll = $this->getDebugInfo();
        $debugInfo = \array_diff_key($debugInfoAll, \array_flip([
            'headers_sent', 'body_sent', 'headers_recv', 'body_recv',
            // "sbs" may be only key remaining
        ]));
        $lastResponseInfo = \array_merge(array(
            'download_content_length' => 0,
            'size_download' => 0,
            'url' => $url,
        ), $this->subject->getLastResponseInfo() ?: array());
        $lastResponseInfo = \array_diff_key($lastResponseInfo, \array_filter(array(
            'content_type' => true,
            'download_content_length' => true,
            'http_code' => true,
            'size_download' => $lastResponseInfo['size_download'] === $lastResponseInfo['download_content_length'], // content length plus any overhead
            'size_upload' => isset($debugInfoAll['body_sent']) === false,
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
        $this->debug->time(\microtime(true) - $this->initValues['timeStart']);
        $debugInfo = $this->getDebugInfo();
        // values available in the headers or elsewhere
        $this->debug->log(self::OAUTH_CLASSNAME . ' ' . $this->debug->i18n->trans('word.parameters'), $this->oauthParams(), $this->debug->meta('cfg', 'abstracter.stringMinLen.encoded', -1));
        $this->debug->log($this->debug->i18n->trans('info.additional'), $this->additionalInfo($url));
        $this->debug->log($this->debug->i18n->trans('request.headers'), $this->debug->redactHeaders($debugInfo['headers_sent']), $this->debug->meta('icon', ':send:'));
        if (isset($debugInfo['body_sent'])) {
            $this->debug->log($this->debug->i18n->trans('request.body'), $debugInfo['body_sent'], $this->debug->meta(array(
                'icon' => ':send:',
                'redact' => true,
            )));
        }
        $this->debug->log($this->debug->i18n->trans('response.headers'), $debugInfo['headers_recv'], $this->debug->meta('icon', ':receive:'));
        $this->debug->log($this->debug->i18n->trans('response.body'), $debugInfo['body_recv'], $this->debug->meta('icon', ':receive:'));
        if ($this->exception) {
            $this->debug->error(\get_class($this->exception), $this->exception->getMessage());
        }
    }

    /**
     * Get the request's OAuth parameters
     *
     * @return array
     */
    private function oauthParams()
    {
        $oauthParamKeys = [
            'oauth_consumer_key',
            'oauth_nonce',
            'oauth_signature',
            'oauth_signature_method',
            'oauth_timestamp',
            'oauth_token',
            'oauth_version',
        ];
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
        if (isset($oauthParams['oauth_signature'])) {
            $oauthParams['oauth_signature'] = Redaction::REPLACEMENT;
        }
        \ksort($oauthParams);
        return $oauthParams;
    }
}

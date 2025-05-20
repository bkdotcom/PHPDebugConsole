<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug\Collector\AbstractAsyncMiddleware;
use bdk\HttpMessage\Request;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\Stream;
use bdk\HttpMessage\Utility\ContentType;
use bdk\PubSub\SubscriberInterface;

/**
 * Capture WordPress HTTP requests
 */
class WpHttp extends AbstractAsyncMiddleware implements SubscriberInterface
{
    const I18N_DOMAIN = 'wordpress';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(array(
            'enabled' => true,
            'idPrefix' => 'wphttp_',
            'inclRequestBody' => true,
            'inclResponseBody' => true,
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array();
    }

    /**
     * Handle wordpress http_request_args filter
     *
     * @param array  $args request args/params
     * @param string $url  request url
     *
     * @return array
     */
    public function onRequest(array $args, $url)
    {
        $args['time_start'] = \microtime(true);

        $request = $this->buildRequest($args, $url);
        $this->logRequest($request, array(
            'isAsynchronous' => $args['blocking'] === false,
            'requestId' => \md5($args['time_start']),
        ));

        return $args;
    }

    /**
     * Handle wordpress http_api_debug filter
     *
     * @param array|WP_Error $responseInfo HTTP response or WP_Error object
     * @param string         $type         Context under which the hook is fired
     * @param string         $class        HTTP transport used
     * @param array          $args         HTTP request arguments.
     * @param string         $url          The request URL
     *
     * @return void
     */
    public function onResponse($responseInfo, $type, $class, $args, $url)
    {
        if ($type !== 'response') {
            return;
        }

        if ($this->isResponseError($responseInfo)) {
            $this->debug->error('error', $responseInfo['http_response']);
            return;
        }

        if ($args['blocking'] === false) {
            $this->debug->info($this->debug->i18n->trans('http.async-response', [], self::I18N_DOMAIN), $this->debug->meta(
                'appendGroup',
                $this->cfg['idPrefix'] . \md5($args['time_start'])
            ));
            return;
        }

        $response = $this->buildResponse($responseInfo, $args['httpversion']);
        $this->onFulfilled($response, array(
            'isAsynchronous' => $args['blocking'] === false,
            'requestId' => \md5($args['time_start']),
        ));
    }

    /**
     * Build PSR-7 request from wordpress request args
     *
     * @param array  $args Request arguments
     * @param string $url  Request URL
     *
     * @return Request
     */
    private function buildRequest(array $args, $url)
    {
        $request = new Request($args['method'], $url);
        $request = $request->withProtocolVersion($args['httpversion']);
        $request = $request->withHeader('User-Agent', $args['user-agent']);
        foreach ($args['headers'] as $key => $value) {
            $request = $request->withHeader($key, $value);
        }
        if (!empty($args['body'])) {
            $stream = \is_array($args['body'])
                ? new Stream(\http_build_query($args['body']))
                : new Stream($args['body']);
            $request = $request->withBody($stream);
            if ($request->hasHeader('Content-Type') === false) {
                $request = $request->withHeader('Content-Type', ContentType::FORM);
            }
        }
        return $request;
    }

    /**
     * Build PSR-7 response from wordpress response info
     *
     * @param array  $responseInfo WordPress HTTP response info
     * @param string $httpVersion  Http version
     *
     * @return Response
     */
    private function buildResponse($responseInfo, $httpVersion)
    {
        $response = new Response($responseInfo['response']['code'], $responseInfo['response']['message']);
        $response = $response->withProtocolVersion($httpVersion);
        $stream = new Stream($responseInfo['body']);
        $response = $response->withBody($stream);
        foreach ($responseInfo['headers'] as $key => $value) {
            $response = $response->withHeader($key, $value);
        }
        return $response;
    }

    /**
     * Does this resposne represent an error?
     *
     * @param mixed $response WordPress' respose info
     *
     * @return bool
     */
    private function isResponseError($response)
    {
        return empty($response)
            || \is_wp_error($response)
            || $response['response']['code'] >= 400;
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        $isFirstConfig = empty($this->cfg['configured']);
        $enabledChanged = isset($cfg['enabled']) && $cfg['enabled'] !== $prev['enabled'];
        if ($enabledChanged === false && $isFirstConfig === false) {
            return;
        }
        $this->cfg['configured'] = true;
        if ($cfg['enabled']) {
            \add_filter('http_request_args', [$this, 'onRequest'], 0, 3);
            \add_filter('http_api_debug', [$this, 'onResponse'], 0, 5);
            return;
        }
        \remove_filter('http_request_args', [$this, 'onRequest'], 0);
        \remove_filter('http_api_debug', [$this, 'onResponse'], 0);
    }
}

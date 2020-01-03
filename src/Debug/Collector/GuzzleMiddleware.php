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

use Exception;
use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PHPDebugConsole Middleware for Guzzle
 */
class GuzzleMiddleware
{

    private $debug;
    private $icon = 'fa fa-exchange';
    private $nextHandler;

    private $cfg = array(
        'inclRequestBody' => false,
        'inclResponseBody' => false,
        'prettyRequestBody' => true,
        'prettyResponseBody' => true,
    );

    /**
     * Constructor
     *
     * @param array $cfg   configuration
     * @param Debug $debug (optional) Specify PHPDebugConsole instance
     *                       if not passed, will create Guzzle channnel on singleton instance
     *                       if root channel is specified, will create a Guzzle channel
     */
    public function __construct($cfg = array(), Debug $debug = null)
    {
        $this->cfg = \array_merge($this->cfg, $cfg);
        if (!$debug) {
            $debug = Debug::_getChannel('Guzzle', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Guzzle', array('channelIcon' => $this->icon));
        }
        $this->debug = $debug;
    }

    /**
     * @param callable $nextHandler next handler in stack
     *
     * @return callable
     */
    public function __invoke(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
        return array($this, 'onRequest');
    }

    /**
     * Log Request Begin
     *
     * @param RequestInterface $request Guzzle request
     * @param array            $options opts
     *
     * @return PromiseInterface
     */
    public function onRequest(RequestInterface $request, array $options)
    {
        $this->debug->groupCollapsed(
            'Guzzle',
            $request->getMethod(),
            (string) $request->getUri(),
            $this->debug->meta('icon', $this->icon)
        );
        $this->debug->log('request headers', $this->buildRequestHeadersString($request), $this->debug->meta('redact'));
        if ($this->cfg['inclRequestBody']) {
            $body = $this->getBody($request);
            $this->debug->log(
                'request body %c%s',
                'font-style: italic; opacity: 0.8;',
                $body instanceof Abstraction
                    ? '(prettified)'
                    : '',
                $body,
                $this->debug->meta('redact')
            );
        }
        $func = $this->nextHandler;
        return $func($request, $options)->then(
            array($this, 'onFulfilled'),
            array($this, 'onRejected')
        );
    }

    /**
     * Fulfilled Request handler
     *
     * @param ResponseInterface $response Guzzle response
     *
     * @return ResponseInterface
     */
    public function onFulfilled(ResponseInterface $response)
    {
        $this->debug->log('response headers', $this->buildResponseHeadersString($response), $this->debug->meta('redact'));
        if ($this->cfg['inclResponseBody']) {
            $body = $this->getBody($response);
            $this->debug->log(
                'response body %c%s',
                'font-style: italic; opacity: 0.8;',
                $body instanceof Abstraction
                    ? '(prettified)'
                    : '',
                $body,
                $this->debug->meta('redact')
            );
        }
        $this->debug->groupEnd();
        return $response;
    }

    /**
     * Rejected Request handler
     *
     * @param mixed $reason Reject reason
     *
     * @return PromiseInterface
     */
    public function onRejected($reason)
    {
        $response = null;
        if ($reason instanceof Exception) {
            $this->debug->warn($reason->getCode(), $reason->getMessage());
        }
        if ($reason instanceof RequestException) {
            $response = $reason->getResponse();
        }
        if ($response) {
            $this->debug->log('response headers', $this->buildResponseHeadersString($response));
        }
        $this->debug->groupEnd();
        return \GuzzleHttp\Promise\rejection_for($reason);
    }

    /**
     * Build request header string
     *
     * @param RequestInterface $message Request or Response
     *
     * @return string
     */
    private function buildRequestHeadersString(RequestInterface $message)
    {
        $result = \trim($message->getMethod()
            . ' ' . $message->getRequestTarget())
            . ' HTTP/' . $message->getProtocolVersion() . "\r\n";
        foreach ($message->getHeaders() as $name => $values) {
            $result .= $name . ': ' . \implode(', ', $values) . "\r\n";
        }
        return \rtrim($result);
    }

    /**
     * Build response header string
     *
     * @param ResponseInterface $message Request or Response
     *
     * @return string
     */
    private function buildResponseHeadersString(ResponseInterface $message)
    {
        $result = 'HTTP/'
            . ' ' . $message->getProtocolVersion()
            . ' ' . $message->getStatusCode()
            . ' ' . $message->getReasonPhrase()
            . "\r\n";
        foreach ($message->getHeaders() as $name => $values) {
            $result .= $name . ': ' . \implode(', ', $values) . "\r\n";
        }
        return \rtrim($result);
    }

    /**
     * Get the request/response body
     *
     * Will return formatted Abstraction if html/json/xml
     *
     * @param MessageInterface $msg request or response
     *
     * @return Abstraction|string|null
     */
    private function getBody(MessageInterface $msg)
    {
        $bodySize = $msg->getBody()->getSize();
        if ($bodySize === 0) {
            return null;
        }
        $contentType = $msg->getHeader('Content-Type');
        $contentType = $contentType
            ? $contentType[0]
            : null;
        $body = $this->debug->utilities->getStreamContents($msg->getBody());
        $prettify = $msg instanceof RequestInterface
            ? $this->cfg['prettyRequestBody']
            : $this->cfg['prettyResponseBody'];
        if (\strlen($body) === 0) {
            return null;
        }
        if ($prettify) {
            $event = $this->debug->rootInstance->eventManager->publish('debug.prettify', $msg, array(
                'value' => $body,
                'contentType' => $contentType,
            ));
            return $event['value'];
        }
        return $body;
    }
}

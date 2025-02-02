<?php

/**
 * @package   bdk\curlhttpmessage
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\CurlHttpMessage;

use BadMethodCallException;
use bdk\CurlHttpMessage\CurlReqRes;
use bdk\CurlHttpMessage\Handler\Curl;
use bdk\CurlHttpMessage\Handler\CurlMulti;
use bdk\CurlHttpMessage\HandlerStack;
use bdk\CurlHttpMessage\Middleware;
use bdk\HttpMessage\Request;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\Stream;
use JsonSerializable;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * factory methods
 */
class Factory
{
    /** @var array<string,callable> */
    protected $factories = array();

    /** @var array<string,string> */
    protected $types = array(
        'form' => 'application/x-www-form-urlencoded',
        'json' => 'application/json; charset=utf-8',
        // multipart/form-data
    );

    /**
     * Constructor
     *
     * @param array $factories factory definitions
     */
    public function __construct($factories = array())
    {
        $this->factories = \array_merge(array(
            'curlReqRes' => [$this, 'buildCurlReqRes'],
            'request' => [$this, 'buildRequest'],
            'response' => [$this, 'buildResponse'],
            'stack' => [$this, 'buildStack'],
            'stream' => [$this, 'buildStream'],
        ), $factories);
    }

    /**
     * Magic method... inaccessible method called.
     *
     * @param string $method Inaccessible method name
     * @param array  $args   Arguments passed to method
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call($method, array $args)
    {
        if (isset($this->factories[$method])) {
            return \call_user_func_array($this->factories[$method], $args);
        }
        throw new BadMethodCallException('method ' . __CLASS__ . '::' . $method . ' is not defined');
    }

    /**
     * Build CurlReqRes instance
     *
     * @param RequestInterface $request Request instance
     * @param array            $options options
     *
     * @return CurlReqRes
     */
    public function buildCurlReqRes(RequestInterface $request, $options = array())
    {
        $curlReqRes = new CurlReqRes($request, $this->factories['response']);
        $curlReqRes->setOptions($options);
        return $curlReqRes;
    }

    /**
     * Build Request instance
     *
     * @param string                 $method  HTTP METHOD
     * @param UriInterface|string    $uri     Request URI
     * @param array<string,string[]> $headers Headers to add
     * @param mixed                  $body    Message body
     *                                   StreamInterface | JsonSerializable | array | string | null
     *
     * @return Request
     */
    public function buildRequest($method, $uri, $headers = array(), $body = null)
    {
        $request = new Request($method, $uri);
        $request = $this->withHeaders($request, $headers);
        $request = $this->withBody($request, $body);
        return $request;
    }

    /**
     * Build Response instance
     *
     * @param int                    $code         (200) HTTP response code
     * @param string                 $reasonPhrase ('') HTTP reason phrase.   will default to phrase matching code
     * @param array<string,string[]> $headers      Headers to add
     * @param mixed                  $body         Message body
     *                                   StreamInterface | JsonSerializable | array | string | null
     *
     * @return Response
     */
    public function buildResponse($code = 200, $reasonPhrase = '', $headers = array(), $body = null)
    {
        $response = new Response($code, (string) $reasonPhrase);
        $response = $this->withHeaders($response, $headers);
        $response = $this->withBody($response, $body);
        return $response;
    }

    /**
     * Build HandlerStack instance
     *
     * @param callable|null $handler Handler callable
     *
     * @return HandlerStack
     */
    public static function buildStack($handler = null)
    {
        \bdk\Debug\Utility::assertType($handler, 'callable');
        if ($handler === null) {
            $syncHandler = new Curl();
            $asyncHandler = new CurlMulti();
            $handler = static function (CurlReqRes $curlReqRes) use ($syncHandler, $asyncHandler) {
                $options = $curlReqRes->getOptions();
                return $options['isAsynchronous']
                    ? $asyncHandler($curlReqRes)
                    : $syncHandler($curlReqRes);
            };
        }
        $stack = new HandlerStack($handler);
        $stack->push(new Middleware\Status(), 'status');
        $stack->push(new Middleware\FollowLocation(), 'followLocation');
        return $stack;
    }

    /**
     * Build Stream instance
     *
     * @param string $content Stream content
     *
     * @return Stream
     */
    public static function buildStream($content = '')
    {
        $resource = \fopen('php://temp', 'wb+');
        \fwrite($resource, $content);
        \rewind($resource);
        return new Stream($resource);
    }

    /**
     * Set message body
     *
     * @param MessageInterface $message MessageInterface instance
     * @param mixed            $body    Message body
     *                                    StreamInterface | JsonSerializable | array | string | null
     *
     * @return MessageInterface
     */
    public function withBody(MessageInterface $message, $body)
    {
        if ($body === null) {
            return $message;
        }
        $bodyIsEncoded = \is_string($body) || $body instanceof StreamInterface;
        $type = $this->inferContentType($message, $body);
        if ($type) {
            $message = $message->withHeader('Content-Type', $type);
            $type = \preg_replace('/;.*$/', '', $type);
        }
        if ($type === 'application/json' && $bodyIsEncoded === false) {
            $body = \json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } elseif ($type === 'application/x-www-form-urlencoded' && $bodyIsEncoded === false) {
            $body = \http_build_query($body);
        }
        $stream = $body instanceof StreamInterface
            ? $body
            : $this->stream($body);
        return $message->withBody($stream);
    }

    /**
     * Add headers to message
     *
     * @param MessageInterface       $message MessageInterface instance
     * @param array<string,string[]> $headers Headers to add
     *
     * @return MessageInterface
     */
    protected function withHeaders(MessageInterface $message, $headers)
    {
        foreach ($headers as $name => $values) {
            if (\strtolower($name) === 'host') {
                $message = $message->withHeader($name, $values);
                continue;
            }
            $message = $message->withAddedHeader($name, $values);
        }
        return $message;
    }

    /**
     * Determine message's Content-Type
     *
     * If message has Content-Type header, it will be returned
     * otherwise we'll inspect the body
     *
     * @param MessageInterface $message MessageInterface instance
     * @param mixed            $body    Message body
     *                                    StreamInterface | JsonSerializable | array | string | null
     *
     * @return string
     */
    private function inferContentType(MessageInterface $message, $body)
    {
        $contentType = $message->getHeaderLine('Content-Type');
        if ($contentType) {
            return $contentType;
        }
        if ($body instanceof JsonSerializable) {
            return $this->types['json'];
        }
        if (\is_array($body)) {
            return $this->types['form'];
        }
        if ($body instanceof StreamInterface) {
            $body = (string) $body;
        }
        if (\is_string($body) && \bdk\Debug\Utility\StringUtil::isJson($body)) {
            return $this->types['json'];
        }
        return '';
    }
}

<?php

namespace bdk\CurlHttpMessage;

use bdk\CurlHttpMessage\Factory;
use bdk\CurlHttpMessage\HandlerStack;
use bdk\Promise\PromiseInterface;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

/**
 * Lightweight PSR-7 (HttpMessage) based cURL client
 */
class Client
{
    const VERSION = '0.1b1';

    /** @var array<string, mixed> */
    protected $options = array(
        'curl' => array(),
        'factories' => array(), // temporary
        'handler' => null,      // temporary
        'headers' => array(),
        'isAsyncronous' => false,
        'maxRedirect' => 5,
        'onRedirect' => null,
    );

    /** @var Factory */
    protected $factory;

    /** @var HandlerStack */
    protected $stack;

    private $isTempCookieJar = true;

    /**
     * Constructor
     *
     * @param array $options curl options, factories, and other request options
     *
     * @throws InvalidArgumentException
     */
    public function __construct($options = array())
    {
        $cookieJarDefault = \tempnam(\sys_get_temp_dir(), 'curlHttpMessageCookies_') . '.txt';
        $this->isTempCookieJar = isset($options['curl'][CURLOPT_COOKIEJAR]) === false;
        $this->options = \array_replace_recursive(
            $this->options,
            array(
                'curl' => array(
                    CURLOPT_COOKIEFILE => $cookieJarDefault,
                    CURLOPT_COOKIEJAR => $cookieJarDefault,
                    CURLOPT_USERAGENT => 'bdk\CurlHttpMessage v' . self::VERSION,
                ),
            ),
            $options
        );
        $this->factory = new Factory($this->options['factories']);
        if ($this->options['handler'] && \is_callable($this->options['handler']) === false) {
            throw new InvalidArgumentException('handler must be callable');
        }
        $this->stack = $this->factory->stack($this->options['handler']);
        unset($this->options['factories'], $this->options['handler']);
    }

    /**
     * Clear resources
     */
    public function __destruct()
    {
        $filepath = $this->options['curl'][CURLOPT_COOKIEFILE];
        if ($this->isTempCookieJar && \is_file($filepath)) {
            \unlink($filepath);
        }
    }

    /**
     * Send a DELETE request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     * @param mixed               $body    (optional) request body or data
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function delete($uri, $headers = array(), $body = null)
    {
        return $this->request('DELETE', $uri, array(
            'body' => $body,
            'headers' => $headers,
        ));
    }

    /**
     * Send a GET request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function get($uri, $headers = array())
    {
        return $this->request('GET', $uri, array(
            'headers' => $headers,
        ));
    }

    /**
     * Send a HEAD request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function head($uri, $headers = array())
    {
        return $this->request('HEAD', $uri, array(
            'headers' => $headers,
        ));
    }

    /**
     * Send an OPTIONS request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     * @param mixed               $body    (optional) request body or data
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function options($uri, $headers = array(), $body = null)
    {
        return $this->request('OPTIONS', $uri, array(
            'body' => $body,
            'headers' => $headers,
        ));
    }

    /**
     * Send a PATCH request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     * @param mixed               $body    (optional) request body or data
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function patch($uri, $headers = array(), $body = null)
    {
        return $this->request('PATCH', $uri, array(
            'body' => $body,
            'headers' => $headers,
        ));
    }

    /**
     * Send a POST request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     * @param mixed               $body    (optional) request body or data
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function post($uri, $headers = array(), $body = null)
    {
        return $this->request('POST', $uri, array(
            'body' => $body,
            'headers' => $headers,
        ));
    }

    /**
     * Send a PUT request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     * @param mixed               $body    (optional) request body or data
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function put($uri, $headers = array(), $body = null)
    {
        return $this->request('PUT', $uri, array(
            'body' => $body,
            'headers' => $headers,
        ));
    }

    /**
     * Send a TRACE request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function trace($uri, $headers = array())
    {
        return $this->request(
            'TRACE',
            $uri,
            array(
                'headers' => $headers,
            )
        );
    }

    /**
     * Get the stack handler so that we can add / remove middleware
     *
     * @return HandlerStack
     */
    public function getStack()
    {
        return $this->stack;
    }

    /**
     * Handle a PSR-7 Request
     *
     * @param RequestInterface $request RequestInterface instance
     * @param array            $options Options
     *
     * @return PromiseInterface|ResponseInterface
     *
     * @throws NetworkException     Invalid request due to network issue
     * @throws RequestException     Invalid request
     * @throws BadResponseException HTTP error (4xx or 5xx response)
     * @throws RuntimeException     Failure to create stream
     */
    public function handle(RequestInterface $request, $options = array())
    {
        $options = $this->mergeOptions($options);
        $request = $this->applyOptions($request, $options);

        $curlReqRes = $this->factory->curlReqRes($request, $options);

        $handler = $this->stack;
        $promise = $handler($curlReqRes);

        return $options['isAsyncronous']
            ? $promise
            : $promise->wait();
    }

    /**
     * Create and send an HTTP request.
     *
     * @param string              $method  HTTP method.
     * @param string|UriInterface $uri     URI object or string.
     * @param array               $options Options including headers, body, & curl
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function request($method, $uri, $options = array())
    {
        $request = $this->factory->request($method, $uri);
        return $this->handle($request, $options);
    }

    /**
     * Merge specified options with default client options
     *
     * @param array $options Request options
     *
     * @return array
     */
    private function mergeOptions($options)
    {
        $defaultOpts = $this->options;

        $defaultOpts['defaultHeaders'] = $defaultOpts['headers'];
        unset($defaultOpts['headers']);

        $options = \array_replace_recursive(
            array(
                'body' => null,
                'headers' => array(),
            ),
            $defaultOpts,
            $options
        );

        if ($options['headers'] === null) {
            $options['headers'] = array();
            $options['defaultHeaders'] = array();
        }

        return $options;
    }

    /**
     * apply options to request
     *
     * @param RequestInterface $request Request instance
     * @param array            $options [description]
     *
     * @return RequestInterface
     *
     * @throws InvalidArgumentException
     */
    private function applyOptions(RequestInterface $request, $options)
    {
        if (\is_array($options['headers']) !== true) {
            throw new InvalidArgumentException('headers must be an array');
        }
        if (\array_keys($options['headers']) === \range(0, \count($options['headers']) - 1)) {
            throw new InvalidArgumentException('headers array must have header name as keys');
        }

        foreach ($options['headers'] as $name => $val) {
            $request = $request->withHeader($name, $val);
        }

        foreach ($options['defaultHeaders'] as $name => $val) {
            if ($request->hasHeader($name) === false) {
                $request = $request->withHeader($name, $val);
            }
        }

        if ($request->hasHeader('User-Agent') === false) {
            $request = $request->withHeader('User-Agent', $options['curl'][CURLOPT_USERAGENT]);
        }

        if ($options['body']) {
            $request = $this->factory->withBody($request, $options['body']);
        }

        return $request;
    }
}

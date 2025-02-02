<?php

/**
 * @package   bdk\curlhttpmessage
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\CurlHttpMessage;

use bdk\CurlHttpMessage\Exception\BadResponseException;
use bdk\CurlHttpMessage\Exception\NetworkException;
use bdk\CurlHttpMessage\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Lightweight PSR-7 (HttpMessage) based cURL client
 */
class Client extends AbstractClient
{
    /**
     * Send a DELETE request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     * @param mixed               $body    (optional) request body or data
     *
     * @return ResponseInterface
     */
    public function delete($uri, array $headers = array(), $body = null) // @phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
    {
        return parent::delete($uri, $headers, $body);
    }

    /**
     * Send a GET request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array|null          $headers Request headers
     *
     * @return ResponseInterface
     */
    public function get($uri, $headers = array()) // @phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
    {
        return parent::get($uri, $headers);
    }

    /**
     * Send a HEAD request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     *
     * @return ResponseInterface
     */
    public function head($uri, array $headers = array()) // @phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
    {
        return parent::head($uri, $headers);
    }

    /**
     * Send an OPTIONS request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     * @param mixed               $body    (optional) request body or data
     *
     * @return ResponseInterface
     */
    public function options($uri, array $headers = array(), $body = null) // @phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
    {
        return parent::options($uri, $headers, $body);
    }

    /**
     * Send a PATCH request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     * @param mixed               $body    (optional) request body or data
     *
     * @return ResponseInterface
     */
    public function patch($uri, array $headers = array(), $body = null) // @phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
    {
        return parent::patch($uri, $headers, $body);
    }

    /**
     * Send a POST request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     * @param mixed               $body    (optional) request body or data
     *
     * @return ResponseInterface
     */
    public function post($uri, array $headers = array(), $body = null) // @phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
    {
        return parent::post($uri, $headers, $body);
    }

    /**
     * Send a PUT request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     * @param mixed               $body    (optional) request body or data
     *
     * @return ResponseInterface
     */
    public function put($uri, array $headers = array(), $body = null) // @phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
    {
        return parent::put($uri, $headers, $body);
    }

    /**
     * Send a TRACE request
     *
     * @param UriInterface|string $uri     UriInterface or string
     * @param array               $headers Request headers
     *
     * @return ResponseInterface
     */
    public function trace($uri, array $headers = array()) // @phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
    {
        return parent::trace($uri, $headers);
    }

    /**
     * Handle a PSR-7 Request
     *
     * @param RequestInterface $request RequestInterface instance
     * @param array            $options Options
     *
     * @return RequestInterface
     *
     * @throws NetworkException     Invalid request due to network issue
     * @throws RequestException     Invalid request
     * @throws BadResponseException HTTP error (4xx or 5xx response)
     * @throws RuntimeException     Failure to create stream
     */
    public function handle(RequestInterface $request, array $options = array()) // @phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
    {
        $options['isAsynchronous'] = false;
        $promise = parent::handle($request, $options);
        return $promise->wait();
    }

    /**
     * Create and send an HTTP request.
     *
     * @param string              $method  HTTP method.
     * @param string|UriInterface $uri     URI object or string.
     * @param array               $options Options including headers, body, & curl
     *
     * @return RequestInterface
     */
    public function request($method, $uri, array $options = array()) // @phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
    {
        return parent::request($method, $uri, $options);
    }
}

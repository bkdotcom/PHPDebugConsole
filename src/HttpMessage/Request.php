<?php

/**
 * This file is part of HttpMessage
 *
 * @package   bdk/http-message
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v1.0
 */

namespace bdk\HttpMessage;

use bdk\HttpMessage\Message;
use bdk\HttpMessage\Uri;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Http Requeset
 *
 * @psalm-consistent-constructor
 */
class Request extends Message implements RequestInterface
{
    /** @var string */
    private $method = 'GET';

    /** @var string|null */
    private $requestTarget;

    /** @var UriInterface */
    private $uri;

    /**
     * Constructor
     *
     * @param string              $method The HTTP method associated with the request.
     * @param UriInterface|string $uri    The URI associated with the request.
     *
     * @throws InvalidArgumentException
     */
    public function __construct($method = 'GET', $uri = '')
    {
        $this->assertMethod($method);
        $this->method = \strtoupper($method);
        if (\is_string($uri) || $uri === null) {
            $uri = new Uri($uri);
        } elseif (!($uri instanceof UriInterface)) {
            throw new InvalidArgumentException('uri must be a string or instance of UriInterface');
        }
        $this->uri = $uri;
        // set host header if we have a hostname
        $new = $this->updateHostHeader();
        $headers = $new->getHeaders();
        $this->setHeaders($headers);
    }

    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget()
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }
        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }
        return $target;
    }

    /**
     * Return an instance with the specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request target.
     *
     * @param mixed $requestTarget new request target
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7230#section-5.3 (for the various
     *     request-target forms allowed in request messages)
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function withRequestTarget($requestTarget)
    {
        if (\is_string($requestTarget) === false) {
            throw new InvalidArgumentException(
                'Request target must be a string.'
            );
        }
        if (\preg_match('#\s#', $requestTarget)) {
            throw new InvalidArgumentException(
                'Request target cannot contain whitespace'
            );
        }
        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string Returns the request method.
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Return an instance with the provided HTTP method.
     *
     * Standard methods include:
     *   HEAD:  Asks for a response identical to that of a GET request,
     *            but without the response body.
     *   GET:   Requests a representation of the specified resource
     *            Requests using GET should only retrieve data.
     *   POST:  Submit an entity to the specified resource,
     *            Often causing a change in state or side effects on the server.
     *   PUT:  Replaces all current representations of the target resource with the request payload.
     *   DELETE:  Deletes the specified resource.
     *   PATCH:  Used to apply partial modifications to a resource.
     *   CONNECT:  Establishes a tunnel to the server identified by the target resource.
     *   OPTIONS:  Used to describe the communication options for the target resource.
     *   TRACE:  Performs a message loop-back test along the path to the target resource.
     *
     * @param string $method Case-sensitive method.
     *
     * @return static
     * @throws InvalidArgumentException for invalid HTTP methods.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7231
     */
    public function withMethod($method)
    {
        $this->assertMethod($method);
        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    /**
     * Retrieves the URI instance.
     *
     * This method MUST return a UriInterface instance.
     *
     * @return UriInterface Returns a UriInterface instance
     *     representing the URI of the request.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.3
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Returns an instance with the provided URI.
     *
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     *
     * - If the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @param UriInterface $uri          New request URI to use.
     * @param bool         $preserveHost Preserve the original state of the Host header.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.3
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        if ($uri === $this->uri) {
            return $this;
        }
        $new = clone $this;
        $new->uri = $uri;
        return $preserveHost && $this->hasHeader('Host')
            ? $new
            : $new->updateHostHeader();
    }

    /**
     * {@inheritDoc}
     */
    public function withoutHeader($name)
    {
        $new = parent::withoutHeader($name);
        return \strtolower($name) === 'host'
            ? $new->updateHostHeader()
            : $new;
    }

    /**
     * if uri has non-empty host
     *    then Return the new Request with updated header
     *    otherwise return static
     *
     * @return static
     */
    private function updateHostHeader()
    {
        $uri = $this->getUri();
        $host = $uri->getHost();
        if ($host === '') {
            return $this;
        }
        $port = $uri->getPort();
        if ($port !== null) {
            $host .= ':' . $port;
        }
        return $this->withHeader('Host', $host);
    }
}

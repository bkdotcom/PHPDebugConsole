<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\psr7lite;

use bdk\Debug\psr7lite\Stream;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * INTERNAL USE ONLY
 *
 * For the most part, this implements Psr\Http\Message\ServerRequestInterface;
 *
 * Just looking to encapsulate the superglobals... not backbone an application or create a dependency on psr/http-message
 */
class ServerRequest
{

    /**
     * @var Stream
     */
    private $body;

    /**
     * @var array $_COOKIE
     */
    private $cookie = array();

    /**
     * @var array $_FILES
     */
    private $files = array();

    /**
     * @var array $_GET
     */
    private $get = array();

    /**
     * @var array Map of all registered headers, as name => array of values
     */
    private $headers = array();

    /**
     * @var array Map of lowercase header name => original name at registration
     */
    private $headerNames = array();

    /**
     * @var string
     */
    private $method = 'GET';

    /**
     * @var array $_POST
     */
    private $post = array();

    /**
     * @var array $_SERVER
     */
    private $server = array();

    /**
     * Constructor
     *
     * @param array $headers headers
     * @param array $server  $_SERVER superglobal
     */
    public function __construct($headers = array(), $server = array())
    {
        $this->setHeaders($headers);
        $this->server = $server;
        $this->method = isset($server['REQUEST_METHOD'])
            ? $server['REQUEST_METHOD']
            : 'GET';
    }

    /**
     * Instantiate self from superglobals
     *
     * @return static
     */
    public static function fromGlobals()
    {
        $headers = static::getAllHeaders($_SERVER);
        $serverRequest = new static($headers, $_SERVER);
        return $serverRequest
            ->withBody(Stream::factory(\fopen('php://input', 'r+')))
            ->withCookieParams($_COOKIE)
            ->withParsedBody($_POST)
            ->withQueryParams($_GET)
            ->withUploadedFiles($_FILES);
    }

    /**
     * Gets the body of the message.
     *
     * @return Stream The body as a stream.
     */
    public function getBody()
    {
        if (!$this->body) {
            $this->body = Stream::factory('');
        }
        return $this->body;
    }

    /**
     * Get Cookie values
     *
     * @return array
     */
    public function getCookieParams()
    {
        return $this->cookie;
    }

    /**
     * @param string $name header name
     *
     * @return string[] An array of string values as provided for the given
     *    header. If the header does not appear in the message, an empty array is returned.
     */
    public function getHeader($name)
    {
        $nameLower = \strtolower($name);
        if (!isset($this->headerNames[$nameLower])) {
            return array();
        }
        $name = $this->headerNames[$nameLower];
        return $this->headers[$name];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method will return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     *
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method will return an empty string.
     */
    public function getHeaderLine($name)
    {
        return \implode(', ', $this->getHeader($name));
    }

    /**
     * @return string[][] Returns an associative array of the message's headers. Each
     *     key is a header name, and each value is an array of strings for that header.
     */
    public function getHeaders()
    {
        return $this->headers;
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
     * Get $_POST data
     *
     * @return array
     */
    public function getParsedBody()
    {
        return $this->post;
    }

    /**
     * Get $_GET data
     *
     * @return array
     */
    public function getQueryParams()
    {
        return $this->get;
    }

    /**
     * Get $_SERVER values
     *
     * @return array
     */
    public function getServerParams()
    {
        return $this->server;
    }

    /**
     * Get $_FILES data
     *
     * @return array
     */
    public function getUploadedFiles()
    {
        return $this->files;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name.
     *
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($name)
    {
        $nameLower = \strtolower($name);
        return isset($this->headerNames[$nameLower]);
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body Body
     *
     * @return static
     * @throws \InvalidArgumentException
     */
    public function withBody($body)
    {
        if (!($body instanceof StreamInterface) && !($body instanceof Stream)) {
            throw new \InvalidArgumentException('body must be an instance of StreamInterface');
        }
        if ($body === $this->body) {
            return $this;
        }
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    /**
     * @param array $cookies $_COOKIE
     *
     * @return static
     */
    public function withCookieParams($cookies)
    {
        $new = clone $this;
        $new->cookie = $cookies;
        return $new;
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * @param string          $name  Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     *
     * @return static
     * @throws InvalidArgumentException for invalid header names or values.
     */
    public function withHeader($name, $value)
    {
        $this->assertHeader($name);
        $value = $this->normalizeHeaderValue($value);
        $nameLower = \strtolower($name);
        $new = clone $this;
        if (isset($new->headerNames[$nameLower])) {
            // remove previous header-name
            $namePrev = $new->headerNames[$nameLower];
            unset($new->headers[$namePrev]);
        }
        $new->headerNames[$nameLower] = $name;
        $new->headers[$name] = $value;
        return $new;
    }

    /**
     * Return an instance without the specified header.
     *
     * @param string $name Case-insensitive header field name to remove.
     *
     * @return static
     */
    public function withoutHeader($name)
    {
        $nameLower = \strlower($name);
        if (!isset($this->headerNames[$nameLower])) {
            return $this;
        }
        $new = clone $this;
        unset($new->headers[$name], $new->headerNames[$nameLower]);
        return $new;
    }

    /**
     * Return an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * @param string $method Case-sensitive method.
     *
     * @return static
     * @throws InvalidArgumentException for invalid HTTP methods.
     */
    public function withMethod($method)
    {
        if (!\is_string($method) || $method === '') {
            throw new InvalidArgumentException('Method must be a non-empty string.');
        }
        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    /**
     * @param array $post $_POST
     *
     * @return static
     */
    public function withParsedBody($post)
    {
        $new = clone $this;
        $new->post = $post;
        return $new;
    }

    /**
     * @param array $get $_GET
     *
     * @return static
     */
    public function withQueryParams($get)
    {
        $new = clone $this;
        $new->get = $get;
        return $new;
    }

    /**
     * @param array $files $_FILES
     *
     * @return static
     */
    public function withUploadedFiles($files)
    {
        $new = clone $this;
        $new->files = $files;
        return $new;
    }

    /**
     * Test valid header name
     *
     * @param string $header header name
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private static function assertHeader($header)
    {
        if (!\is_string($header)) {
            throw new InvalidArgumentException(\sprintf(
                'Header name must be a string but %s provided.',
                \is_object($header) ? \get_class($header) : \gettype($header)
            ));
        }
        if ($header === '') {
            throw new InvalidArgumentException('Header name can not be empty.');
        }
    }

    /**
     * Get all HTTP header key/values as an associative array for the current request.
     *
     * Uses getallheaders (aka apache_request_headers) if avail / falls back to $_SERVER vals
     *
     * @param array $serverParams $_SERVER
     *
     * @return string[string] The HTTP header key/value pairs.
     */
    private static function getAllHeaders($serverParams)
    {
        if (\function_exists('getallheaders')) {
            return \getallheaders();
        }
        $headers = array();
        $keysSansHttp = array(
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        );
        foreach ($serverParams as $key => $value) {
            if (\substr($key, 0, 5) === 'HTTP_') {
                $key = \substr($key, 5);
                if (!isset($keysSansHttp[$key]) || !isset($serverParams[$key])) {
                    $key = \str_replace(' ', '-', \ucwords(\strtolower(\str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } elseif (isset($keysSansHttp[$key])) {
                $headers[$keysSansHttp[$key]] = $value;
            }
        }
        if (!isset($headers['Authorization'])) {
            $auth = self::getAuthorizationHeader();
            if ($auth) {
                $headers['Authorization'] = $auth;
            }
        }
        return $headers;
    }

    /**
     * Build Authorization header from $_SERVER values
     *
     * @param array $serverParams $_SERVER vals
     *
     * @return null|string
     */
    private static function getAuthorizationHeader($serverParams)
    {
        $auth = null;
        if (isset($serverParams['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $serverParams['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (isset($serverParams['PHP_AUTH_USER'])) {
            $basicPass = isset($serverParams['PHP_AUTH_PW']) ? $serverParams['PHP_AUTH_PW'] : '';
            $auth = 'Basic ' . \base64_encode($serverParams['PHP_AUTH_USER'] . ':' . $basicPass);
        } elseif (isset($serverParams['PHP_AUTH_DIGEST'])) {
            $auth = $serverParams['PHP_AUTH_DIGEST'];
        }
        return $auth;
    }

    /**
     * Trim header value(s)
     *
     * @param string|array $value header value
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private static function normalizeHeaderValue($value)
    {
        if (!\is_array($value)) {
            return self::trimHeaderValues([$value]);
        }
        if (\count($value) === 0) {
            throw new InvalidArgumentException('Header value can not be an empty array.');
        }
        return self::trimHeaderValues($value);
    }

    /**
     * Set header values
     *
     * @param array $headers header name/value pairs
     *
     * @return void
     */
    private function setHeaders($headers = array())
    {
        foreach ($headers as $name => $value) {
            if (\is_int($name)) {
                // Numeric array keys are converted to int by PHP but having a header name '123' is not forbidden by the spec
                // and also allowed in withHeader(). So we need to cast it to string again for the following assertion to pass.
                $name = (string) $name;
            }
            self::assertHeader($name);
            $value = $this->normalizeHeaderValue($value);
            $nameLower = \strtolower($name);
            if (isset($this->headerNames[$nameLower])) {
                $name = $this->headerNames[$nameLower];
                $this->headers[$name] = \array_merge($this->headers[$name], $value);
                continue;
            }
            $this->headerNames[$nameLower] = $name;
            $this->headers[$name] = $value;
        }
    }

    /**
     * Trims whitespace from the header values.
     *
     * Spaces and tabs ought to be excluded by parsers when extracting the field value from a header field.
     *
     * header-field = field-name ":" OWS field-value OWS
     * OWS          = *( SP / HTAB )
     *
     * @param string[] $values Header values
     *
     * @return string[] Trimmed header values
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     */
    private static function trimHeaderValues(array $values)
    {
        return \array_map(function ($value) {
            if (!\is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException(\sprintf(
                    'Header value must be scalar or null but %s provided.',
                    \is_object($value) ? \get_class($value) : \gettype($value)
                ));
            }
            return \trim((string) $value, " \t");
        }, $values);
    }
}

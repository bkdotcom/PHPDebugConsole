<?php

/**
 * This file is part of HttpMessage
 *
 * @package   bdk/http-message
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v1.0
 */

namespace bdk\HttpMessage;

use bdk\HttpMessage\Request;
use bdk\HttpMessage\Utility\ParseStr;
use bdk\HttpMessage\Utility\ServerRequest as ServerRequestUtil;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Http ServerRequest
 *
 * @psalm-consistent-constructor
 */
class ServerRequest extends Request implements ServerRequestInterface
{
    /** @var array */
    private $attributes = array();

    /** @var array $_COOKIE */
    private $cookie = array();

    /** @var array */
    private $files = array();

    /** @var array $_GET */
    private $get = array();

    /** @var null|array|object $_POST */
    private $post = null;

    /** @var array $_SERVER */
    private $server = array();

    /**
     * Constructor
     *
     * @param string              $method       The HTTP method associated with the request.
     * @param UriInterface|string $uri          The URI associated with the request.
     * @param array               $serverParams An array of Server API (SAPI) parameters with
     *     which to seed the generated request instance. (and headers)
     */
    public function __construct($method = 'GET', $uri = '', array $serverParams = array())
    {
        parent::__construct($method, $uri);
        $headers = $this->getHeadersViaServer($serverParams);
        $query = $this->getUri()->getQuery();
        $this->get = $query !== ''
            ? ParseStr::parse($query)
            : array();
        $this->server = \array_merge(array(
            'REQUEST_METHOD' => $method,
        ), $serverParams);
        $this->protocolVersion = isset($serverParams['SERVER_PROTOCOL'])
            ? \str_replace('HTTP/', '', (string) $serverParams['SERVER_PROTOCOL'])
            : '1.1';
        $this->setHeaders($headers);
    }

    /**
     * Instantiate self from superglobals
     *
     * @param array $parseStrOpts Parse options (default: {convDot:false, convSpace:false})
     *
     * @return self
     */
    public static function fromGlobals($parseStrOpts = array())
    {
        return ServerRequestUtil::fromGlobals($parseStrOpts);
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
     * Get Cookie values
     *
     * @return array
     */
    public function getCookieParams()
    {
        return $this->cookie;
    }

    /**
     * Return an instance with the specified cookies.
     *
     * @param array $cookies $_COOKIE
     *
     * @return static
     */
    public function withCookieParams(array $cookies)
    {
        $this->assertCookieParams($cookies);
        $new = clone $this;
        $new->cookie = $cookies;
        return $new;
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
     * Return an instance with the specified query string arguments.
     *
     * @param array $query $_GET params
     *
     * @return static
     */
    public function withQueryParams(array $query)
    {
        $this->assertQueryParams($query);
        $new = clone $this;
        $new->get = $query;
        return $new;
    }

    /**
     * Retrieve normalized file upload data.
     *
     * This method returns upload metadata in a normalized tree, with each leaf
     * an instance of Psr\Http\Message\UploadedFileInterface.
     *
     * @return array An array tree of UploadedFileInterface instances (or an empty array)
     */
    public function getUploadedFiles()
    {
        return $this->files;
    }

    /**
     * Create a new instance with the specified uploaded files.
     *
     * @param array $uploadedFiles An array tree of UploadedFileInterface instances.
     *
     * @return static
     * @throws InvalidArgumentException if an invalid structure is provided.
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $this->assertUploadedFiles($uploadedFiles);
        $new = clone $this;
        $new->files = $uploadedFiles;
        return $new;
    }

    /**
     * Get $_POST data
     *
     * @return null|array|object
     */
    public function getParsedBody()
    {
        return $this->post;
    }

    /**
     * Return an instance with the specified body parameters.
     *
     * @param null|array|object $data The deserialized body data ($_POST).
     *                                  This will typically be in an array or object
     *
     * @return static
     */
    public function withParsedBody($data)
    {
        $this->assertParsedBody($data);
        $new = clone $this;
        $new->post = $data;
        return $new;
    }

    /**
     * Retrieve attributes derived from the request.
     *
     * The request "attributes" may be used to allow injection of any
     * parameters derived from the request: e.g., the results of path
     * match operations; the results of decrypting cookies; the results of
     * deserializing non-form-encoded message bodies; etc. Attributes
     * will be application and request specific, and CAN be mutable.
     *
     * @return mixed[] Attributes derived from the request.
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Retrieve a single derived request attribute.
     *
     * @param string $name    The attribute name.
     * @param mixed  $default Default value to return if the attribute does not exist.
     *
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        if (\array_key_exists($name, $this->attributes) === false) {
            return $default;
        }
        return $this->attributes[$name];
    }

    /**
     * Return an instance with the specified derived request attribute.
     *
     * @param string $name  attribute name
     * @param mixed  $value value
     *
     * @return static
     */
    public function withAttribute($name, $value)
    {
        $this->assertAttributeName($name);
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    /**
     * Return an instance that removes the specified derived request attribute.
     *
     * @param string $name attribute name
     *
     * @return static
     */
    public function withoutAttribute($name)
    {
        if ($this->assertAttributeName($name, false) === false) {
            return $this;
        }
        if (\array_key_exists($name, $this->attributes) === false) {
            return $this;
        }
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    /**
     * Get all HTTP header key/values as an associative array for the current request.
     *
     * See also the php function `getallheaders`
     *
     * @param array $serverParams $_SERVER
     *
     * @return array<string, string> The HTTP header key/value pairs.
     */
    protected function getHeadersViaServer(array $serverParams)
    {
        $headers = array();
        $keysSansHttp = array(
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
            'CONTENT_TYPE'   => 'Content-Type',
        );
        $auth = $this->getAuthorizationHeader($serverParams);
        if (\strlen($auth)) {
            // set default...   can be overwritten by HTTP_AUTHORIZATION
            $headers['Authorization'] = $auth;
        }
        /** @var mixed $value */
        foreach ($serverParams as $key => $value) {
            $key = (string) $key;
            if (isset($keysSansHttp[$key])) {
                $key = $keysSansHttp[$key];
                $headers[$key] = (string) $value;
            } elseif (\substr($key, 0, 5) === 'HTTP_') {
                $key = \substr($key, 5);
                $key = \strtolower($key);
                $key = \str_replace(' ', '-', \ucwords(\str_replace('_', ' ', $key)));
                $headers[$key] = (string) $value;
            }
        }
        return $headers;
    }

    /**
     * Build Authorization header value from $_SERVER values
     *
     * @param array $serverParams $_SERVER vals
     *
     * @return string (empty string if no auth)
     */
    private function getAuthorizationHeader(array $serverParams)
    {
        if (isset($serverParams['REDIRECT_HTTP_AUTHORIZATION'])) {
            return (string) $serverParams['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (isset($serverParams['PHP_AUTH_USER'])) {
            $user = (string) $serverParams['PHP_AUTH_USER'];
            $pass = isset($serverParams['PHP_AUTH_PW']) ? (string) $serverParams['PHP_AUTH_PW'] : '';
            return 'Basic ' . \base64_encode($user . ':' . $pass);
        }
        if (isset($serverParams['PHP_AUTH_DIGEST'])) {
            return (string) $serverParams['PHP_AUTH_DIGEST'];
        }
        return '';
    }
}

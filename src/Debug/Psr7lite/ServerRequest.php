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

namespace bdk\Debug\Psr7lite;

use bdk\Debug\Psr7lite\Request;
use bdk\Debug\Psr7lite\Stream;
use bdk\Debug\Psr7lite\UploadedFile;
use bdk\Debug\Psr7lite\Uri;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * INTERNAL USE ONLY
 *
 * @psalm-consistent-constructor
 */
class ServerRequest extends Request
{

    /** @var array */
    private $attributes = array();

    /**
     * @var array $_COOKIE
     */
    private $cookie = array();

    /** @var array */
    private $files = array();

    /**
     * @var array $_GET
     */
    private $get = array();

    /**
     * @var null|array|object $_POST
     */
    private $post = null;

    /**
     * @var array $_SERVER
     */
    private $server = array();

    /**
     * Constructor
     *
     * @param string                                    $method       The HTTP method associated with the request.
     * @param \Psr\Http\Message\UriInterface|Uri|string $uri          The URI associated with the request.
     * @param array                                     $serverParams An array of Server API (SAPI) parameters with
     *     which to seed the generated request instance. (and headers)
     */
    public function __construct($method = 'GET', $uri = '', $serverParams = array())
    {
        parent::__construct($method, $uri);
        $headers = $this->getAllHeaders($serverParams);
        $this->server = $serverParams;
        $this->protocolVersion = isset($serverParams['SERVER_PROTOCOL'])
            ? \str_replace('HTTP/', '', $serverParams['SERVER_PROTOCOL'])
            : '1.1';
        $this->setHeaders($headers);
    }

    /**
     * Instantiate self from superglobals
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function fromGlobals()
    {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? $_SERVER['REQUEST_METHOD']
            : 'GET';
        $uri = self::uriFromGlobals();
        $files = self::filesFromGlobals($_FILES);
        $serverRequest = new static($method, $uri, $_SERVER);
        $contentType = $serverRequest->getHeaderLine('Content-Type');
        $parsedBody = self::postFromInput($method, $contentType);
        $query = $uri->getQuery();
        $queryParams = $query !== ''
            ? self::parseStr($query)
            : $_GET;
        return $serverRequest
            ->withBody(new Stream(\fopen('php://input', 'r+')))
            ->withCookieParams($_COOKIE)
            ->withParsedBody($parsedBody)
            ->withQueryParams($queryParams)
            ->withUploadedFiles($files);
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
     * Get $_GET data
     *
     * @return array
     */
    public function getQueryParams()
    {
        return $this->get;
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
    public function withUploadedFiles($uploadedFiles)
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
     * @param null|array|object $post The deserialized body data ($_POST).
     *                                  This will typically be in an array or object
     *
     * @return static
     */
    public function withParsedBody($post)
    {
        $this->assertParsedBody($post);
        $new = clone $this;
        $new->post = $post;
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
     * Return an instance that removes the specified derived request attribute.
     *
     * @param string $name  attribute name
     * @param mixed  $value value
     *
     * @return static
     */
    public function withAttribute($name, $value)
    {
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
        if (\array_key_exists($name, $this->attributes) === false) {
            return $this;
        }
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    /**
     * Throw an exception if an unsupported argument type is provided.
     *
     * @param array|object|null $data The deserialized body data. This will
     *     typically be in an array or object.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertParsedBody($data)
    {
        if (
            $data === null ||
            \is_array($data) ||
            \is_object($data)
        ) {
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            'Only accepts array, object and null, but %s provided.',
            \gettype($data)
        ));
    }

    /**
     * Recursively validate the structure in an uploaded files array.
     *
     * @param array $uploadedFiles uploaded files tree
     *
     * @return void
     *
     * @throws InvalidArgumentException if any leaf is not an UploadedFileInterface instance.
     */
    private function assertUploadedFiles($uploadedFiles)
    {
        if (!\is_array($uploadedFiles)) {
            throw new InvalidArgumentException(\sprintf(
                'Uploaded files - expected array, but %s provided',
                self::debugType($uploadedFiles)
            ));
        }
        foreach ($uploadedFiles as $file) {
            if (\is_array($file)) {
                $this->assertUploadedFiles($file);
                continue;
            }
            if ($file instanceof UploadedFileInterface) {
                continue;
            }
            if ($file instanceof UploadedFile) {
                continue;
            }
            throw new InvalidArgumentException(
                'Invalid leaf in uploaded files structure'
            );
        }
    }

    /**
     * Create UploadedFile(s) from $_FILES entry
     *
     * @param array $fileInfo $_FILES entry
     *
     * @return UploadedFile|array
     */
    private static function createUploadedFile($fileInfo)
    {
        if (\is_array($fileInfo['tmp_name'])) {
            $files = array();
            foreach (\array_keys($fileInfo['tmp_name']) as $key) {
                $fileInfoNew = [
                    'tmp_name' => $fileInfo['tmp_name'][$key],
                    'size'     => $fileInfo['size'][$key],
                    'error'    => $fileInfo['error'][$key],
                    'name'     => $fileInfo['name'][$key],
                    'type'     => $fileInfo['type'][$key],
                ];
                $files[$key] = self::createUploadedFile($fileInfoNew);
            }
            return $files;
        }
        return new UploadedFile(
            $fileInfo['tmp_name'],
            (int) $fileInfo['size'],
            (int) $fileInfo['error'],
            $fileInfo['name'],
            $fileInfo['type']
        );
    }

    /**
     * Create UploadedFiles tree from $_FILES
     *
     * @param array $phpFiles $_FILES type array
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private static function filesFromGlobals($phpFiles)
    {
        $files = array();
        foreach ($phpFiles as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $files[$key] = $value;
                continue;
            }
            if ($value instanceof UploadedFile) {
                $files[$key] = $value;
                continue;
            }
            if (\is_array($value)) {
                $files[$key] = isset($value['tmp_name'])
                    ? self::createUploadedFile($value)
                    : self::filesFromGlobals($value);
                continue;
            }
            throw new InvalidArgumentException('Invalid value in files specification');
        }
        return $files;
    }

    /**
     * Get host and port from $_SERVER vals
     *
     * @return array host & port
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private static function getHostPortFromGlobals()
    {
        $host = '';
        $port = null;
        if (isset($_SERVER['HTTP_HOST'])) {
            $url = 'http://' . $_SERVER['HTTP_HOST'];
            $parts = \parse_url($url);
            if ($parts === false) {
                return [null, null];
            }
            $host = isset($parts['host']) ? $parts['host'] : '';
            $port = isset($parts['port']) ? $parts['port'] : null;
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'];
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $host = $_SERVER['SERVER_ADDR'];
        }
        if ($port === null && isset($_SERVER['SERVER_PORT'])) {
            $port = $_SERVER['SERVER_PORT'];
        }
        return array($host, $port);
    }

    /**
     * like PHP's parse_str()
     *   key difference: by default this does not convert root key dots and spaces to '_'
     *
     * @param string $str  input string
     * @param array  $opts parse options
     *
     * @return array
     *
     * @see https://github.com/api-platform/core/blob/master/src/Util/RequestParser.php#L50
     */
    private static function parseStr($str, $opts = array())
    {
        $params = array();
        $opts = \array_merge(array(
            'convDot' => false,
            'convSpace' => false,
        ), $opts);
        $useParseStr = (!$opts['convDot'] || \strpos($str, '.') === false)
            && (!$opts['convSpace'] || \strpos($str, ' ') === false);
        if ($useParseStr) {
            // there are no spaces or dots in serialized data
            //   and/or we're not interested in converting them
            // just use parse_str
            \parse_str($str, $params);
            return $params;
        }

        // Use a regex to replace keys with a bin2hex'd version
        // this will prevent parse_str from modifying the keys
        // '[' is urlencoded ('%5B') in the input, but we must urldecode it in order
        // to find it when replacing names with the regexp below.
        $str = \str_replace('%5B', '[', $str);
        $str = \preg_replace_callback(
            '/(^|(?<=&))[^=[&]+/',
            function ($matches) {
                return \bin2hex(\urldecode($matches[0]));
            },
            $str
        );
        \parse_str($str, $params);

        $replace = array();
        if ($opts['convDot']) {
            $replace['.'] = '_';
        }
        if ($opts['convSpace']) {
            $replace[' '] = '_';
        }
        $keys = \array_map(function ($key) use ($replace) {
            return \strtr(\hex2bin($key), $replace);
        }, \array_keys($params));
        return \array_combine($keys, $params);
    }

    /**
     * Confirm the content type and post values whether fit the requirement.
     *
     * @param string $method      HTTP method
     * @param string $contentType Content-Type header value
     * @param string $rawBody     @internal for unit-testing
     *
     * @return null|array
     */
    protected static function postFromInput($method, $contentType, $rawBody = null)
    {
        $contentType = \preg_replace('/\s*[;,]\s*.*$/', '', $contentType);
        $contentType = \strtolower($contentType);
        if ($method === 'GET') {
            return null;
        }
        if ($rawBody === null) {
            $rawBody = \file_get_contents('php://input');
        }
        if ($rawBody === '') {
            return null;
        }
        $formContentTypes = array(
            'application/x-www-form-urlencoded',
            'multipart/form-data',
        );
        if (\in_array($contentType, $formContentTypes)) {
            return self::parseStr($rawBody);
        }
        if ($contentType === 'application/json') {
            $jsonParsedBody = \json_decode($rawBody, true);
            return \json_last_error() === JSON_ERROR_NONE
                ? $jsonParsedBody
                : null;
        }
        return self::parseStr($rawBody);
    }

    /**
     * Get a Uri populated with values from $_SERVER.
     *
     * @return Uri
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private static function uriFromGlobals()
    {
        $uri = new Uri('');
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
            ? 'https'
            : 'http';
        $uri = $uri->withScheme($scheme);

        list($host, $port) = self::getHostPortFromGlobals();
        if ($host) {
            $uri = $uri->withHost($host);
        }
        if ($port) {
            $uri = $uri->withPort($port);
        }
        $query = null;
        if (isset($_SERVER['REQUEST_URI'])) {
            $requestUriParts = \explode('?', $_SERVER['REQUEST_URI'], 2);
            $uri = $uri->withPath($requestUriParts[0]);
            if (isset($requestUriParts[1])) {
                $query = $requestUriParts[1];
                $uri = $uri->withQuery($query);
            }
        }
        if ($query === null && isset($_SERVER['QUERY_STRING'])) {
            $query = $_SERVER['QUERY_STRING'];
            $uri = $uri->withQuery($query);
        }
        return $uri;
    }
}

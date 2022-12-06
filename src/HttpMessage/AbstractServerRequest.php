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

use bdk\HttpMessage\Request;
use bdk\HttpMessage\UploadedFile;
use bdk\HttpMessage\Uri;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Extended by ServerRequest
 *
 * @psalm-consistent-constructor
 */
abstract class AbstractServerRequest extends Request
{
    private static $parseStrOpts = array(
        'convDot' => false,     // whether to convert '.' to '_'  (php's default is true)
        'convSpace' => false,   // whether to convert ' ' to '_'  (php's default is true)
    );

    /**
     * like PHP's parse_str()
     *   key difference: by default this does not convert root key dots and spaces to '_'
     *
     * @param string $str  input string
     * @param array  $opts parse options
     *
     * @return array
     *
     * @see https://github.com/api-platform/core/blob/main/src/Core/Util/RequestParser.php#L50
     */
    public static function parseStr($str, $opts = array())
    {
        $opts = \array_merge(self::$parseStrOpts, $opts);
        $useParseStr = ($opts['convDot'] || \strpos($str, '.') === false)
            && ($opts['convSpace'] || \strpos($str, ' ') === false);
        if ($useParseStr) {
            // there are no spaces or dots in serialized data
            //   and/or we're not interested in converting them
            // just use parse_str
            $params = array();
            \parse_str($str, $params);
            return $params;
        }
        return self::parseStrCustom($str, $opts);
    }

    /**
     * Set default parseStr option(s)
     *
     *    parseStrOpts('key', 'value')
     *    parseStrOpts(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param array|string $mixed key=>value array or key
     * @param mixed        $val   new value
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public static function parseStrOpts($mixed, $val = null)
    {
        if (\is_string($mixed)) {
            $mixed = array($mixed => $val);
        }
        if (\is_array($mixed) === false) {
            throw new InvalidArgumentException(\sprintf(
                'parseStrOpts expects string or array but %s provided.',
                self::getTypeDebug($mixed)
            ));
        }
        $mixed = \array_intersect_key($mixed, self::$parseStrOpts);
        self::$parseStrOpts = \array_merge(self::$parseStrOpts, $mixed);
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
    protected static function filesFromGlobals($phpFiles)
    {
        $files = array();
        foreach ($phpFiles as $key => $value) {
            $files[$key] = self::fileFromGlobal($value);
        }
        return $files;
    }

    /**
     * Get all HTTP header key/values as an associative array for the current request.
     *
     * See also the php function `getallheaders`
     *
     * @param array $serverParams $_SERVER
     *
     * @return string[string] The HTTP header key/value pairs.
     */
    protected function getHeadersViaServer($serverParams)
    {
        $headers = array();
        $keysSansHttp = array(
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        );
        $auth = $this->getAuthorizationHeader($serverParams);
        if ($auth) {
            // set default...   can be overwritten by HTTP_AUTHORIZATION
            $headers['Authorization'] = $auth;
        }
        foreach ($serverParams as $key => $value) {
            if (isset($keysSansHttp[$key])) {
                $key = $keysSansHttp[$key];
                $headers[$key] = $value;
            } elseif (\substr($key, 0, 5) === 'HTTP_') {
                $key = \substr($key, 5);
                $key = \strtolower($key);
                $key = \str_replace(' ', '-', \ucwords(\str_replace('_', ' ', $key)));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }

    /**
     * Get parsed body (POST data)
     *
     * Note: this will return null if content-type = "multipart/form-data" and input = "php://input"
     *
     * @param string $contentType Content-Type header value
     * @param string $input       ('php://input') specify input
     *
     * @return array|null
     */
    protected static function postFromInput($contentType, $input = 'php://input')
    {
        $contentType = \preg_replace('/\s*[;,].*$/', '', $contentType);
        $contentType = \strtolower($contentType);
        if (self::isContentTypeParseable($contentType) === false) {
            return null;
        }
        $rawBody = \file_get_contents($input);
        if ($rawBody === '') {
            return null;
        }
        if ($contentType !== 'application/json') {
            return self::parseStr($rawBody);
        }
        $jsonParsedBody = \json_decode($rawBody, true);
        return \json_last_error() === JSON_ERROR_NONE
            ? $jsonParsedBody
            : null;
    }

    /**
     * Get a Uri populated with values from $_SERVER.
     *
     * @return Uri
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected static function uriFromGlobals()
    {
        $uri = new Uri('');
        $parts = \array_merge(
            array(
                'scheme' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                    ? 'https'
                    : 'http',
            ),
            self::uriHostPortFromGlobals(),
            self::uriPathQueryFromGlobals()
        );
        $methods = array(
            'scheme' => 'withScheme',
            'host' => 'withHost',
            'port' => 'withPort',
            'path' => 'withPath',
            'query' => 'withQuery',
        );
        foreach ($parts as $name => $value) {
            if ($value) {
                $method = $methods[$name];
                $uri = $uri->{$method}($value);
            }
        }
        return $uri;
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
            return self::createUploadedFileArray($fileInfo);
        }
        return new UploadedFile($fileInfo);
    }

    /**
     * Convert php's uploaded file array to UploadedFile[]
     *
     * @param array $fileInfo PHP's uploaded file info where each key is an array
     *
     * @return UploadedFile[]
     */
    private static function createUploadedFileArray($fileInfo)
    {
        $files = array();
        $keys = \array_keys($fileInfo['tmp_name']);
        // don't use array_map...  callback does not have access to self::createUploadedFile
        foreach ($keys as $key) {
            $files[$key] = self::createUploadedFile([
                'tmp_name' => $fileInfo['tmp_name'][$key],
                'size'     => $fileInfo['size'][$key],
                'error'    => $fileInfo['error'][$key],
                'name'     => $fileInfo['name'][$key],
                'type'     => $fileInfo['type'][$key],
                'full_path' => isset($fileInfo['full_path'][$key])
                    ? $fileInfo['full_path'][$key]
                    : null,
            ]);
        }
        return $files;
    }

    /**
     * Convert php's upload-file info array to UploadedFile instance
     *
     * @param array $phpFile uploaded-file info
     *
     * @return UploadedFileInterface|array
     *
     * @throws InvalidArgumentException
     */
    private static function fileFromGlobal($phpFile)
    {
        if ($phpFile instanceof UploadedFileInterface) {
            return $phpFile;
        }
        if (\is_array($phpFile)) {
            return isset($phpFile['tmp_name'])
                ? self::createUploadedFile($phpFile)
                : self::filesFromGlobals($phpFile);
        }
        throw new InvalidArgumentException('Invalid value in files specification');
    }

    /**
     * Build Authorization header value from $_SERVER values
     *
     * @param array $serverParams $_SERVER vals
     *
     * @return null|string
     */
    private function getAuthorizationHeader($serverParams)
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
     * Is the given Content-Type parsable
     *
     * @param string $contentType Content-Type / Mime-Type
     *
     * @return bool
     */
    private static function isContentTypeParseable($contentType)
    {
        $parsableTypes = array(
            'application/json',
            'application/x-www-form-urlencoded',
            'multipart/form-data', // would be parsable... but php doesn't make available
        );
        return \in_array($contentType, $parsableTypes, true);
    }

    /**
     * Parses request parameters from the specified string
     *
     * @param string $str  input string
     * @param array  $opts parse options
     *
     * @return array
     */
    private static function parseStrCustom($str, $opts)
    {
        // Use a regex to replace keys with a bin2hex'd version
        // this will prevent parse_str from modifying the keys
        // '[' is urlencoded ('%5B') in the input, but we must urldecode it in order
        // to find it when replacing names with the regexp below.
        $str = \str_replace('%5B', '[', $str);
        $str = \preg_replace_callback(
            '/(^|(?<=&))[^=[&]+/',
            static function ($matches) {
                return \bin2hex(\urldecode($matches[0]));
            },
            $str
        );

        // parse_str urldecodes both keys and values in resulting array
        \parse_str($str, $params);

        $replace = array();
        if ($opts['convDot']) {
            $replace['.'] = '_';
        }
        if ($opts['convSpace']) {
            $replace[' '] = '_';
        }
        $keys = \array_map(static function ($key) use ($replace) {
            return \strtr(\hex2bin($key), $replace);
        }, \array_keys($params));
        return \array_combine($keys, $params);
    }

    /**
     * Get host and port from $_SERVER vals
     *
     * @return array host & port
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private static function uriHostPortFromGlobals()
    {
        $hostPort = array(
            'host' => null,
            'port' => null,
        );
        if (isset($_SERVER['HTTP_HOST'])) {
            $hostPort = self::uriHostPortFromHttpHost($_SERVER['HTTP_HOST']);
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $hostPort['host'] = $_SERVER['SERVER_NAME'];
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $hostPort['host'] = $_SERVER['SERVER_ADDR'];
        }
        if ($hostPort['port'] === null && isset($_SERVER['SERVER_PORT'])) {
            $hostPort['port'] = $_SERVER['SERVER_PORT'];
        }
        return $hostPort;
    }

    /**
     * Get host & port from `$_SERVER['HTTP_HOST']`
     *
     * @param string $httpHost `$_SERVER['HTTP_HOST']` value
     *
     * @return array host & port
     */
    private static function uriHostPortFromHttpHost($httpHost)
    {
        $url = 'http://' . $httpHost;
        $partsDefault = array(
            'host' => null,
            'port' => null,
        );
        $parts = \parse_url($url) ?: array();
        $parts = \array_merge($partsDefault, $parts);
        return \array_intersect_key($parts, $partsDefault);
    }

    /**
     * Get request uri and query from $_SERVER
     *
     * @return array path & query
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private static function uriPathQueryFromGlobals()
    {
        $path = '/';
        $query = null;
        if (isset($_SERVER['REQUEST_URI'])) {
            $exploded = \explode('?', $_SERVER['REQUEST_URI'], 2);
            // exploded is an array of length 1 or 2
            // use array_shift to avoid testing if exploded[1] exists
            $path = \array_shift($exploded);
            $query = \array_shift($exploded); // string|null
        } elseif (isset($_SERVER['QUERY_STRING'])) {
            $query = $_SERVER['QUERY_STRING'];
        }
        return array(
            'path' => $path,
            'query' => $query !== null
                ? $query
                : \http_build_query($_GET),
        );
    }
}

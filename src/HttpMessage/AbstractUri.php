<?php

/**
 * This file is part of HttpMessage
 *
 * @package   bdk/http-message
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v1.0
 */

namespace bdk\HttpMessage;

use bdk\HttpMessage\AssertionTrait;

/**
 * Extended by Uri
 *
 * All the non-public Uri bits
 */
abstract class AbstractUri
{
    use AssertionTrait;

    const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    private static $schemes = array(
        'ftp' => 21,
        'http' => 80,
        'https' => 443,
    );

    /**
     * Create path component of Uri
     *
     * @param string $authority Authority [user-info@]host[:port]
     * @param string $path      Path
     *
     * @return string
     */
    protected static function createUriPath($authority, $path)
    {
        if ($path === '') {
            return $path;
        }
        if ($path[0] !== '/' && $authority !== '') {
            // If the path is rootless and an authority is present,
            // the path MUST be prefixed by "/"
            $path = '/' . $path;
        } elseif (\substr($path, 0, 2) === '//' && $authority === '') {
            // If the path is starting with more than one "/" and no authority is present,
            // starting slashes MUST be reduced to one.
            $path = '/' . \ltrim($path, '/');
        }
        return $path;
    }

    /**
     * Filter/validate path
     *
     * @param string $path URI path
     *
     * @return string
     * @throws InvalidArgumentException
     */
    protected function filterPath($path)
    {
        $this->assertString($path, 'path');
        $specPattern = '%:@\/';
        $encodePattern = '%(?![A-Fa-f0-9]{2})';
        $regex = '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . $specPattern . ']+|' . $encodePattern . ')/';
        return $this->regexEncode($regex, $path);
    }

    /**
     * Filter/validate port
     *
     * @param null|int $port Port
     *
     * @return null|int
     * @throws InvalidArgumentException
     */
    protected function filterPort($port)
    {
        if ($port === null) {
            return null;
        }
        if (\is_string($port) && \preg_match('/^\d+$/', $port)) {
            $port = (int) $port;
        }
        $this->assertPort($port);
        return $port;
    }

    /**
     * Filter/validate query and fragment
     *
     * @param string $str query or frabment
     *
     * @return string
     */
    protected function filterQueryAndFragment($str)
    {
        $specPattern = '%:@\/\?';
        $encodePattern = '%(?![A-Fa-f0-9]{2})';
        $regex = '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . $specPattern . ']+|' . $encodePattern . ')/';
        return $this->regexEncode($regex, $str);
    }

    /**
     * Get host and port from $_SERVER vals
     *
     * @return array host & port
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected static function hostPortFromGlobals()
    {
        $hostPort = array(
            'host' => null,
            'port' => null,
        );
        if (isset($_SERVER['HTTP_HOST'])) {
            $hostPort = self::hostPortFromHttpHost($_SERVER['HTTP_HOST']);
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
     * Is a given port standard for the given scheme?
     *
     * @param string $scheme Scheme
     * @param int    $port   Port
     *
     * @return bool
     */
    protected static function isStandardPort($scheme, $port)
    {
        return isset(self::$schemes[$scheme]) && $port === self::$schemes[$scheme];
    }

    /**
     * Perform Locale-independent lowercasing
     *
     * @param string $str String to lowercase
     *
     * @return string
     */
    protected static function lowercase($str)
    {
        return \strtr(
            $str,
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz'
        );
    }

    /**
     * Get request uri and query from $_SERVER
     *
     * @return array path & query
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected static function pathQueryFromGlobals()
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

    /**
     * Get host & port from `$_SERVER['HTTP_HOST']`
     *
     * @param string $httpHost `$_SERVER['HTTP_HOST']` value
     *
     * @return array host & port
     */
    private static function hostPortFromHttpHost($httpHost)
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
     * Call rawurlencode on on match
     *
     * @param string $regex Regular expression
     * @param string $str   string
     *
     * @return string
     */
    private static function regexEncode($regex, $str)
    {
        return \preg_replace_callback($regex, static function ($matches) {
            return \rawurlencode($matches[0]);
        }, $str);
    }
}

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
     * Parse URL (multi-byte safe)
     *
     * @param string $url The URL to parse.
     *
     * @return array|false
     */
    protected function parseUrl($url)
    {
        if (PHP_VERSION_ID >= 80000) {
            return \parse_url($url);
        }
        // parse_url is not multi-byte safe...
        //  url encode the url  then decode the individual parts
        $chars = '!*\'();:@&=$,/?#[]';
        $entities = \str_split(\urlencode($chars), 3);
        $chars = \str_split($chars);
        $urlEnc = \str_replace($entities, $chars, \urlencode($url));
        $parts = $this->parseUrlPatched($urlEnc);
        if (!$parts) {
            return $parts;
        }
        foreach ($parts as $name => $value) {
            $parts[$name] = \is_string($value)
                ? \urldecode(\str_replace($chars, $entities, $value))
                : $value;
        }
        return $parts;
    }

    /**
     * Parse URL that may or may not contain schema
     *
     * @param string $url The URL to parse.
     *
     * @return array|false
     */
    private function parseUrlPatched($url)
    {
        if (PHP_VERSION_ID >= 50500 || \strpos($url, '//') !== 0) {
            return \parse_url($url);
        }
        // php 5.4 chokes without the scheme
        $parts = \parse_url('http:' . $url);
        if ($parts) {
            unset($parts['scheme']);
        }
        return $parts;
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

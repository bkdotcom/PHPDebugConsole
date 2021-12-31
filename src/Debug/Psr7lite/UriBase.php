<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Psr7lite;

use InvalidArgumentException;

/**
 * All the non-public Uri bits
 */
class UriBase
{
    const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    private static $schemes = array(
        'ftp' => 21,
        'http' => 80,
        'https' => 443,
    );

    /**
     * Throw exception if invalid host string.
     *
     * @param string $host The host string to of a URI.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertHost($host)
    {
        $this->assertString($host, 'host');
        if (\in_array($host, array('','localhost'), true)) {
            // An empty host value is equivalent to removing the host.
            // No validation required
            return;
        }
        if ($this->isFqdn($host)) {
            return;
        }
        if (\filter_var($host, FILTER_VALIDATE_IP)) {
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            '"%s" is not a valid host',
            $host
        ));
    }

    /**
     * Throw exception if invalid value.
     *
     * @param string $value The value to check.
     * @param string $what  The name of the value.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertString($value, $what = '')
    {
        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf(
                '%s must be a string, but %s provided.',
                \ucfirst($what),
                $this->getTypeDebug($value)
            ));
        }
    }

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
            return '';
        }
        if ($path[0] !== '/' && $authority !== '') {
            // If the path is rootless and an authority is present,
            // the path MUST be prefixed by "/"
            return '/' . $path;
        }
        if (isset($path[1]) && $path[1] === '/' && $authority === '') {
            // If the path is starting with more than one "/" and no authority is present,
            // starting slashes MUST be reduced to one.
            return '/' . \ltrim($path, '/');
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
        return self::isNonStandardPort($this->getScheme(), $port)
            ? $port
            : null;
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
     * Parse URL with fix for php 5.4
     *
     * @param string $url The URL to parse.
     *
     * @return array|false
     */
    protected function parseUrl($url)
    {
        if (PHP_VERSION_ID < 50500 && \strpos($url, '//') === 0) {
            // php 5.4 chokes without the scheme
            $parts = \parse_url('http:' . $url);
            if ($parts) {
                $parts['scheme'] = '';
            }
            return $parts;
        }
        return \parse_url($url);
    }

    /**
     * Throw exception if invalid port value
     *
     * @param int $port port value
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function assertPort($port)
    {
        if (!\is_int($port)) {
            throw new InvalidArgumentException(\sprintf(
                'Port must be a int, but %s provided.',
                $this->getTypeDebug($port)
            ));
        }
        if ($port < 0 || $port > 0xffff) {
            throw new InvalidArgumentException(\sprintf('Invalid port: %d. Must be between 0 and 65535', $port));
        }
    }

    /**
     * Get the value's type
     *
     * @param mixed $value Value to inspect
     *
     * @return string
     */
    private static function getTypeDebug($value)
    {
        return \is_object($value)
            ? \get_class($value)
            : \gettype($value);
    }

    /**
     * Test if hostname is a fully-qualified domain naim
     *
     * @param string $host Hostname to test
     *
     * @return bool
     *
     * @see https://www.regextester.com/103452
     */
    private function isFqdn($host)
    {
        if (PHP_VERSION_ID >= 70000) {
            return \filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
        }
        $regex = '/(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]\.)+[a-zA-Z]{2,63}$)/';
        return \preg_match($regex, $host) === 1;
    }

    /**
     * Is a given port non-standard for the current scheme?
     *
     * @param string $scheme Scheme
     * @param int    $port   Port
     *
     * @return bool
     */
    private static function isNonStandardPort($scheme, $port)
    {
        return !isset(self::$schemes[$scheme]) || $port !== self::$schemes[$scheme];
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
        return \preg_replace_callback($regex, function ($matches) {
            return \rawurlencode($matches[0]);
        }, $str);
    }
}

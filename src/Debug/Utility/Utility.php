<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use Exception;
use Psr\Http\Message\StreamInterface;

/**
 * Utility methods
 */
class Utility
{
    /**
     * Emit headers queued for output directly using `header()`
     *
     * @param array $headers array of headers
     *                array(
     *                   array(name, value)
     *                   name => value
     *                   name => array(value1, value2),
     *                )
     *
     * @return void
     * @throws \RuntimeException if headers already sent
     */
    public static function emitHeaders($headers)
    {
        if (!$headers) {
            return;
        }
        $file = '';
        $line = 0;
        // @phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
        if (headers_sent($file, $line)) {
            throw new \RuntimeException('Headers already sent: ' . $file . ', line ' . $line);
        }
        foreach ($headers as $key => $val) {
            if (\is_int($key)) {
                $key = $val[0];
                $val = $val[1];
            }
            self::emitHeader($key, $val);
        }
    }

    /**
     * Format duration
     *
     * @param float    $duration  duration in seconds
     * @param string   $format    DateInterval format string, or 'auto', us', 'ms', 's', or 'sec'
     * @param int|null $precision decimal precision
     *
     * @return string
     */
    public static function formatDuration($duration, $format = 'auto', $precision = 4)
    {
        $format = self::formatDurationGetFormat($duration, $format);
        if (\preg_match('/%[YyMmDdaHhIiSsFf]/', $format)) {
            return static::formatDurationDateInterval($duration, $format);
        }
        switch ($format) {
            case 'us':
                $val = $duration * 1000000;
                $unit = 'μs';
                break;
            case 'ms':
                $val = $duration * 1000;
                $unit = 'ms';
                break;
            default:
                $val = $duration;
                $unit = 'sec';
        }
        if ($precision) {
            $val = \round($val, $precision);
        }
        return $val . ' ' . $unit;
    }

    /**
     * Convert size int into "1.23 kB" or vice versa
     *
     * @param int|string $size      bytes or similar to "1.23M"
     * @param bool       $returnInt return integer?
     *
     * @return string|int|false
     */
    public static function getBytes($size, $returnInt = false)
    {
        if (\is_string($size)) {
            $size = self::parseBytes($size);
        }
        if ($size === false) {
            return false;
        }
        if ($returnInt) {
            return (int) $size;
        }
        $units = array('B','kB','MB','GB','TB','PB');
        $exp = (int) \floor(\log((float) $size, 1024));
        $pow = \pow(1024, $exp);
        $size = (int) $pow < 1
            ? '0 B'
            : \round($size / $pow, 2) . ' ' . $units[$exp];
        return $size;
    }

    /**
     * Returns sent/pending response header values for specified header
     *
     * @param string      $key       ('Content-Type') header to return
     * @param null|string $delimiter (', ') if string, then join the header values
     *                                 if null, return array
     *
     * @return array|string
     */
    public static function getEmittedHeader($key = 'Content-Type', $delimiter = ', ')
    {
        $headers = static::getEmittedHeaders();
        $header = isset($headers[$key])
            ? $headers[$key]
            : array();
        return \is_string($delimiter)
            ? \implode($delimiter, $header)
            : $header;
    }

    /**
     * Returns sent/pending response headers
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     * @return array
     */
    public static function getEmittedHeaders()
    {
        // @phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
        $list = headers_list();
        $headers = array();
        foreach ($list as $header) {
            list($key, $value) = \array_replace(array('',''), \explode(': ', $header, 2));
            $headers[$key][] = $value;
        }
        return $headers;
    }

    /**
     * Get stream contents without affecting pointer
     *
     * @param StreamInterface $stream StreamInteface
     *
     * @return string
     */
    public static function getStreamContents(StreamInterface $stream)
    {
        try {
            $pos = $stream->tell();
            $body = (string) $stream; // __toString() is like getContents(), but without throwing exceptions
            $stream->seek($pos);
            return $body;
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            return '';
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Get current git branch
     *
     * @return string|null
     */
    public static function gitBranch()
    {
        $redirect = \stripos(PHP_OS, 'WIN') !== 0
            ? '2>/dev/null'
            : '2> nul';  // @codeCoverageIgnore
        $outputLines = array();
        $returnStatus = 0;
        // all branches are output
        // current branch is preceeded with "*"
        \exec('git branch ' . $redirect, $outputLines, $returnStatus);
        $allLines = \implode("\n", $outputLines);
        $matches = array();
        \preg_match('#^\* (.+)$#m', $allLines, $matches);
        return $matches
            ? $matches[1]
            : null;
    }

    /**
     * Does specified http method generally have a request body
     *
     * @param string $method http method (such as 'GET' or 'POST')
     *
     * @return bool
     */
    public static function httpMethodHasBody($method)
    {
        // don't expect a request body for these methods
        $noBodyMethods = array('CONNECT','DELETE','GET','HEAD','OPTIONS','TRACE');
        return \in_array($method, $noBodyMethods, true) === false;
    }

    /**
     * "Safely" test if value is a file
     *
     * @param string $val value to test
     *
     * @return bool
     */
    public static function isFile($val)
    {
        if (\is_string($val) === false) {
            return false;
        }
        /*
            pre-test / prevent "is_file() expects parameter 1 to be a valid path, string given"
        */
        if (\preg_match('#(://|[\r\n\x00])#', $val) === 1) {
            return false;
        }
        return \is_file($val);
    }

    /**
     * Emit a header
     *
     * @param string       $name  Header name
     * @param string|array $value Header value(s)
     *
     * @return void
     * @phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
     */
    private static function emitHeader($name, $value)
    {
        if (\is_array($value)) {
            $val = \array_shift($value);
            header($name . ': ' . $val);
            foreach ($value as $val) {
                // add (vs replace) the additional headers
                header($name . ': ' . $val, false);
            }
            return;
        }
        header($name . ': ' . $value);
    }

    /**
     * Format a duration using a DateInterval format string
     *
     * @param float  $duration duration in seconds
     * @param string $format   DateInterval format string
     *
     * @return string
     *
     * @see https://www.php.net/manual/en/dateinterval.format.php
     */
    private static function formatDurationDateInterval($duration, $format)
    {
        // php < 7.1 DateInterval doesn't support fraction..   we'll work around that
        $hours = \floor($duration / 3600);
        $sec = $duration - $hours * 3600;
        $min = \floor($sec / 60);
        $sec = $sec - $min * 60;
        $sec = \round($sec, 6);
        if (\preg_match('/%[Ff]/', $format)) {
            $secWhole = \floor($sec);
            $secFraction = $sec - $secWhole;
            $sec = $secWhole;
            $micros = $secFraction * 1000000;
            $format = \strtr($format, array(
                '%F' => \sprintf('%06d', $micros),  // Microseconds: 6 digits with leading 0
                '%f' => $micros,                    // Microseconds: w/o leading zeros
            ));
        }
        $dateInterval = new \DateInterval('PT0S');
        $dateInterval->h = (int) $hours;
        $dateInterval->i = (int) $min;
        $dateInterval->s = (int) $sec;
        return $dateInterval->format($format);
    }

    /**
     * Get Duration format
     *
     * @param float  $duration duration in seconds
     * @param string $format   "auto", "us", "ms", "s", or DateInterval format string
     *
     * @return string
     */
    private static function formatDurationGetFormat($duration, $format)
    {
        if ($format !== 'auto') {
            return $format;
        }
        if ($duration < 1 / 1000) {
            return 'us';
        }
        if ($duration < 1) {
            return 'ms';
        }
        if ($duration < 60) {
            return 's';
        }
        if ($duration < 3600) {
            return '%im %Ss'; // M:SS
        }
        return '%hh %Im %Ss'; // H:MM:SS
    }

    /**
     * Parse string such as 128M
     *
     * @param string $size size
     *
     * @return int|false
     */
    private static function parseBytes($size)
    {
        if (\preg_match('/^[\d,]+$/', $size)) {
            return (int) \str_replace(',', '', $size);
        }
        $matches = array();
        if (\preg_match('/^([\d,.]+)\s?([kmgtp])?b?$/i', $size, $matches)) {
            $matches = \array_replace(array('','',''), $matches);
            $size = (float) \str_replace(',', '', $matches[1]);
            switch (\strtolower($matches[2])) {
                case 'p':
                    $size *= 1024;
                    // no break
                case 't':
                    $size *= 1024;
                    // no break
                case 'g':
                    $size *= 1024;
                    // no break
                case 'm':
                    $size *= 1024;
                    // no break
                case 'k':
                    $size *= 1024;
            }
            return (int) $size;
        }
        return false;
    }
}

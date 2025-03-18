<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     1.2
 */

namespace bdk\Debug;

use bdk\Debug;
use RuntimeException;

/**
 * Utility methods
 */
class Utility
{
    /**
     * Emit headers queued for output directly using `header()`
     *
     * @param array<array-key,string|string[]> $headers array of headers
     *                array(
     *                   array(name, value)
     *                   name => value
     *                   name => array(value1, value2),
     *                )
     *
     * @return void
     * @throws RuntimeException if headers already sent
     */
    public static function emitHeaders(array $headers)
    {
        if (!$headers) {
            return;
        }
        $file = '';
        $line = 0;
        // phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
        if (headers_sent($file, $line)) {
            throw new RuntimeException(self::trans('utility.headers-sent', array(
                'file' => $file,
                'line' => $line,
            )));
        }
        foreach ($headers as $key => $val) {
            if (\is_int($key)) {
                $key = (string) $val[0];
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
                list($val, $unit) = [$duration * 1000000, 'μs'];
                break;
            case 'ms':
                list($val, $unit) = [$duration * 1000, 'ms'];
                break;
            default:
                list($val, $unit) = [$duration, 'sec'];
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
        $units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'];
        $exp = (int) \floor(\log((float) $size, 1024));
        $pow = \pow(1024, $exp);
        /** @psalm-suppress RedundantCast */
        $size = (int) $pow < 1
            ? '0 B'
            : \round($size / $pow, 2) . ' ' . $units[$exp];
        return $size;
    }

    /**
     * Returns sent/pending response header values for specified header
     *
     * @param string      $key       ('Content-Type') header to return
     * @param string|null $delimiter (', ') if string, then join the header values
     *                                 if null, return array
     *
     * @return string|string[]
     *
     * @deprecated use $debug->getEmittedHeader() instead
     */
    public static function getEmittedHeader($key = 'Content-Type', $delimiter = ', ')
    {
        return \bdk\Debug\Plugin\Method\ReqRes::getEmittedHeader($key, $delimiter);
    }

    /**
     * Returns sent/pending response headers
     *
     * It is preferred to use PSR-7 (Http-Messaage) response interface over this method
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     * @return array<string,list<string>>
     *
     * @deprecated use $debug->getEmittedHeaders() instead
     */
    public static function getEmittedHeaders()
    {
        return \bdk\Debug\Plugin\Method\ReqRes::getEmittedHeaders();
    }

    /**
     * Get current git branch for specified directory
     *
     * @param string $dir (optional) defaults to current working dir
     *
     * @return string|null
     */
    public static function gitBranch($dir = null)
    {
        // exec('git branch') may fail due due to permissions / rights
        // navigate up until we find the ./git/HEAD file
        $dir = $dir ?: \getcwd();
        $parts = \explode(DIRECTORY_SEPARATOR, $dir);
        $docRoot = Debug::getInstance()->getServerParam('DOCUMENT_ROOT');
        $docRootParts = \explode(DIRECTORY_SEPARATOR, $docRoot);
        $dirBreakParts = \array_slice($docRootParts, 0, -1);
        for ($i = \count($parts); $i > 0; $i--) {
            $dirParts = \array_slice($parts, 0, $i);
            $gitHeadFilepath = \implode(DIRECTORY_SEPARATOR, \array_merge(
                $dirParts,
                ['.git', 'HEAD']
            ));
            if (\file_exists($gitHeadFilepath)) {
                $fileLines = \file($gitHeadFilepath);
                // line 0 should be something like:
                // ref: refs/heads/branchName
                $parts = \array_replace(
                    [null, null, ''],
                    \explode('/', $fileLines[0], 3)
                );
                return \trim($parts[2]);
            }
            if ($dirParts === $dirBreakParts) {
                break;
            }
        }
        return null;
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
        $noBodyMethods = ['CONNECT', 'DELETE', 'GET', 'HEAD', 'OPTIONS', 'TRACE'];
        return \in_array($method, $noBodyMethods, true) === false;
    }

    /**
     * "Safely" test if value is a file
     *
     * @param string $val value to test
     *
     * @return bool
     *
     * @psalm-assert-if-true string $val
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
     * Sort a list of files
     *
     * @param list<string> $files Files to sort
     *
     * @return list<string>
     */
    public static function sortFiles($files)
    {
        \usort($files, static function ($valA, $valB) {
            $valA = \str_replace('_', '0', $valA);
            $valB = \str_replace('_', '0', $valB);
            $dirA = \dirname($valA);
            $dirB = \dirname($valB);
            return $dirA === $dirB
                ? \strnatcasecmp($valA, $valB)
                : \strnatcasecmp($dirA, $dirB);
        });
        return $files;
    }

    /**
     * Convenience wrapper for Debug's trans method
     *
     * @param string $str    string to translate
     * @param array  $args   optional arguments
     * @param string $domain optional domain (defaults to defaultLocale)
     *
     * @return string
     */
    public static function trans($str, array $args = array(), $domain = null)
    {
        return Debug::getInstance()->i18n->trans($str, $args, $domain);
    }

    /**
     * Emit a header
     *
     * @param string          $name  Header name
     * @param string|string[] $value Header value(s)
     *
     * @return void
     *
     * @phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
     */
    private static function emitHeader($name, $value)
    {
        $values = (array) $value;
        $val = \array_shift($values);
        header($name . ': ' . $val);
        foreach ($values as $val) {
            // add (vs replace) the additional values
            header($name . ': ' . $val, false);
        }
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
        $duration = \sprintf('PT%dH%dM%dS', (int) $hours, (int) $min, (int) $sec);
        $dateInterval = new \DateInterval($duration);
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
        $matches = [];
        if (\preg_match('/^([\d,.]+)\s?([kmgtp])?b?$/i', $size, $matches)) {
            $matches = \array_replace(['', '', ''], $matches);
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

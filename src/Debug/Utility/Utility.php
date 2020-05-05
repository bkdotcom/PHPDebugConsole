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

namespace bdk\Debug;

use DOMDocument;
use Exception;
use Psr\Http\Message\StreamInterface;
use SqlFormatter;

/**
 * Utility methods
 */
class Utility
{

    private static $serverParams = array();

    protected static $domDocument;

    /**
     * "dereference" array
     * returns a copy of the array with references removed
     *
     * @param array $source source array
     * @param bool  $deep   (true) deep copy
     *
     * @return array
     */
    public static function arrayCopy($source, $deep = true)
    {
        $arr = array();
        foreach ($source as $key => $val) {
            if ($deep && \is_array($val)) {
                $arr[$key] = self::arrayCopy($val);
                continue;
            }
            $arr[$key] = $val;
        }
        return $arr;
    }

    /**
     * Is passed argument a simple array with all-integer keys in sequence from 0 to n?
     * empty array returns true
     *
     * @param mixed $val value to check
     *
     * @return bool
     */
    public static function arrayIsList($val)
    {
        if (!\is_array($val)) {
            return false;
        }
        $keys = \array_keys($val);
        foreach ($keys as $i => $key) {
            if ($key !== $i) {
                return false;
            }
        }
        return true;
    }

    /**
     * Recursively merge two arrays
     *
     * @param array $arrayDef default array
     * @param array $array2   array 2
     *
     * @return array
     */
    public static function arrayMergeDeep($arrayDef, $array2)
    {
        if (!\is_array($arrayDef) || self::isCallable($arrayDef)) {
            // not array or appears to be a callable
            return $array2;
        }
        if (!\is_array($array2) || self::isCallable($array2)) {
            // not array or appears to be a callable
            return $array2;
        }
        foreach ($array2 as $k2 => $v2) {
            if (\is_int($k2)) {
                if (!\in_array($v2, $arrayDef)) {
                    $arrayDef[] = $v2;
                }
                continue;
            }
            if (!isset($arrayDef[$k2])) {
                $arrayDef[$k2] = $v2;
                continue;
            }
            $arrayDef[$k2] = self::arrayMergeDeep($arrayDef[$k2], $v2);
        }
        return $arrayDef;
    }

    /**
     * Get value from array
     *
     * @param array        $array array to traverse
     * @param array|string $path  key path
     *                               path may contain special keys:
     *                                 * __count__ : return count() (traversal will cease)
     *                                 * __end__ : last value
     *                                 * __reset__ : first value
     *
     * @return mixed
     */
    public static function arrayPathGet($array, $path)
    {
        if (!\is_array($path)) {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        $path = \array_reverse($path);
        while ($path) {
            $key = \array_pop($path);
            $arrayAccess = \is_array($array) || $array instanceof \ArrayAccess;
            if (!$arrayAccess) {
                return null;
            }
            if (isset($array[$key])) {
                $array = $array[$key];
                continue;
            }
            if ($key === '__count__') {
                return \count($array);
            }
            if ($key === '__end__') {
                \end($array);
                $path[] = \key($array);
                continue;
            }
            if ($key === '__reset__') {
                \reset($array);
                $path[] = \key($array);
                continue;
            }
            return null;
        }
        return $array;
    }

    /**
     * Update/Set an array value via "path"
     *
     * @param array        $array array to edit
     * @param array|string $path  path may contain special keys:
     *                                 * __end__ : last value
     *                                 * __reset__ : first value
     * @param mixed        $val   value to set
     *
     * @return void
     */
    public static function arrayPathSet(&$array, $path, $val)
    {
        if (!\is_array($path)) {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        $path = \array_reverse($path);
        $ref = &$array;
        while ($path) {
            $key = \array_pop($path);
            if ($key === '__end__') {
                \end($ref);
                $path[] = \key($ref);
                continue;
            }
            if ($key === '__reset__') {
                \reset($ref);
                $path[] = \key($ref);
                continue;
            }
            if (!isset($ref[$key]) || !\is_array($ref[$key])) {
                $ref[$key] = array(); // initialize this level
            }
            $ref = &$ref[$key];
        }
        $ref = $val;
    }

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
    public function emitHeaders($headers)
    {
        if (!$headers) {
            return;
        }
        if (\headers_sent($file, $line)) {
            throw new \RuntimeException('Headers already sent: ' . $file . ', line ' . $line);
        }
        foreach ($headers as $key => $nameVal) {
            if (\is_int($key)) {
                \header($nameVal[0] . ': ' . $nameVal[1]);
                continue;
            }
            if (\is_string($nameVal)) {
                \header($key . ': ' . $nameVal);
                continue;
            }
            if (\is_array($nameVal)) {
                foreach ($nameVal as $val) {
                    \header($key . ': ' . $val);
                }
            }
        }
    }

    /**
     * Format duration
     *
     * @param float    $duration  duration in seconds
     * @param string   $format    DateInterval format string, or 'us', 'ms', 's'
     * @param int|null $precision decimal precision
     *
     * @return string
     */
    public static function formatDuration($duration, $format = 'auto', $precision = 4)
    {
        $format = self::formatDurationGetFormat($duration, $format);
        if (\preg_match('/%[YyMmDdaHhIiSsFf]/', $format)) {
            // php < 7.1 DateInterval doesn't support fraction..   we'll work around that
            $hours = \floor($duration / 3600);
            $sec = $duration - $hours * 3600;
            $min = \floor($sec / 60);
            $sec = $sec - $min * 60;
            $sec = \round($sec, 6);
            if (\preg_match('/%[Ff]/', $format)) {
                $secWhole = \floor($sec);
                $secFraction = $secWhole - $sec;
                $sec = $secWhole;
                $micros = $secFraction * 1000000;
                $format = \strtr($format, array(
                    '%F' => \sprintf('%06d', $micros),  // Microseconds: 6 digits with leading 0
                    '%f' => $micros,                    // Microseconds: w/o leading zeros
                ));
            }
            $format = \preg_replace('/%[Ss]/', (string) $sec, $format);
            $dateInterval = new \DateInterval('PT0S');
            $dateInterval->h = $hours;
            $dateInterval->i = $min;
            $dateInterval->sec = $sec;
            return $dateInterval->format($format);
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
     * @return string|int
     */
    public static function getBytes($size, $returnInt = false)
    {
        if (\is_string($size)) {
            $size = self::parseBytes($size);
        }
        if ($returnInt) {
            return (int) $size;
        }
        $units = array('B','kB','MB','GB','TB','PB');
        $exp = \floor(\log((float) $size, 1024));
        $pow = \pow(1024, $exp);
        $size = (int) $pow === 0
            ? '0 B'
            : \round($size / $pow, 2) . ' ' . $units[$exp];
        return $size;
    }

    /**
     * Returns sent/pending response header values for specified header
     *
     * @param string $key       ('Content-Type') header to return
     * @param string $delimiter Optional.  If specified, join values.  Otherwise, array is returned
     *
     * @return array|string
     */
    public static function getEmittedHeader($key = 'Content-Type', $delimiter = null)
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
        $list = \headers_list();
        $headers = array();
        foreach ($list as $header) {
            list($key, $value) = \explode(': ', $header, 2);
            $headers[$key][] = $value;
        }
        return $headers;
    }

    /**
     * returns required/included files sorted by directory
     *
     * @return array
     */
    public static function getIncludedFiles()
    {
        $includedFiles = \get_included_files();
        \usort($includedFiles, function ($valA, $valB) {
            $dirA = \dirname($valA);
            $dirB = \dirname($valB);
            return $dirA === $dirB
                ? \strnatcasecmp($valA, $valB)
                : \strnatcasecmp($dirA, $dirB);
        });
        return $includedFiles;
    }

    /**
     * Returns cli, cron, ajax, or http
     *
     * @return string cli | "cli cron" | http | "http ajax"
     */
    public static function getInterface()
    {
        $return = 'http';
        /*
            notes:
                $_SERVER['argv'] could be populated with query string if register_argc_argv = On
                we used to also check for `defined('STDIN')`, but it's not unit test friendly
        */
        $argv = self::getServerParam('argv', array());
        $isCliOrCron = \count(\array_filter(array(
            // have argv and it's not query_string
            $argv && $argv !== array(self::getServerParam('QUERY_STRING')),
            // serverParam REQUEST_METHOD... NOT request->getMethod() which likely defaults to GET
            self::getServerParam('REQUEST_METHOD') === null,
        ))) > 0;
        if ($isCliOrCron) {
            // TERM is a linux/unix thing
            $return = self::getServerParam('TERM') !== null || self::getServerParam('PATH') !== null
                ? 'cli'
                : 'cli cron';
        } elseif (self::getServerParam('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest') {
            $return = 'http ajax';
        }
        return $return;
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
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Get the "phrase" associated with the status code
     *
     * @param string $statusCode 3-digit status code
     *
     * @return string
     */
    public static function httpStatusPhrase($statusCode)
    {
        $phrases = array(
            '100' => 'Continue',
            '101' => 'Switching Protocols',
            '200' => 'OK',
            '201' => 'Created',
            '202' => 'Accepted',
            '203' => 'Non-Authoritative Information',
            '204' => 'No Content',
            '205' => 'Reset Content',
            '206' => 'Partial Content',
            '300' => 'Multiple Choices',
            '301' => 'Moved Permanently',
            '302' => 'Moved Temporarily',
            '303' => 'See Other',
            '304' => 'Not Modified',
            '305' => 'Use Proxy',
            '307' => 'Temporary Redirect',
            '400' => 'Bad Request',
            '401' => 'Unauthorized',
            '402' => 'Payment Required',
            '403' => 'Forbidden',
            '404' => 'Not Found',
            '405' => 'Method Not Allowed',
            '406' => 'Not Acceptable',
            '407' => 'Proxy Authentication Required',
            '408' => 'Request Time-out',
            '409' => 'Conflict',
            '410' => 'Gone',
            '411' => 'Length Required',
            '412' => 'Precondition Failed',
            '413' => 'Request Entity Too Large',
            '414' => 'Request-URI Too Large',
            '415' => 'Unsupported Media Type',
            '416' => 'Range Not Satisfiable',
            '417' => 'Expectation Failed',
            '426' => 'Upgrade Required',
            '500' => 'Internal Server Error',
            '501' => 'Not Implemented',
            '502' => 'Bad Gateway',
            '503' => 'Service Unavailable',
            '504' => 'Gateway Time-out',
            '505' => 'HTTP Version not supported',
        );
        return isset($phrases[$statusCode])
            ? $phrases[$statusCode]
            : '';
    }

    /**
     * Checks if a given string is base64 encoded
     *
     * @param string $str string to check
     *
     * @return bool
     */
    public static function isBase64Encoded($str)
    {
        return (bool) \preg_match('%^[a-zA-Z0-9(!\s+)?\r\n/+]*={0,2}$%', \trim($str));
    }

    /**
     * Test if string is valid xml
     *
     * @param string $str string to test
     *
     * @return bool
     */
    public static function isXml($str)
    {
        if (!\is_string($str)) {
            return false;
        }
        \libxml_use_internal_errors(true);
        $xmlDoc = \simplexml_load_string($str);
        \libxml_clear_errors();
        return $xmlDoc !== false;
    }

    /**
     * Determine PHP's MemoryLimit
     *
     * @return string
     */
    public static function memoryLimit()
    {
        $iniVal = \ini_get('memory_limit');
        return $iniVal ?: '128M';
    }

    /**
     * Prettify JSON string
     *
     * @param string $json JSON string to prettify
     *
     * @return string
     */
    public static function prettyJson($json)
    {
        $opts = JSON_PRETTY_PRINT;
        if (\strpos($json, '\\/') === false) {
            // json doesn't appear to contain escaped slashes
            $opts |= JSON_UNESCAPED_SLASHES;
        }
        if (\strpos($json, '/u') === false) {
            // json doesn't appear to contain encoded unicode
            $opts |= JSON_UNESCAPED_UNICODE;
        }
        return \json_encode(\json_decode($json), $opts);
    }

    /**
     * Prettify SQL string
     *
     * @param string $sql SQL string to prettify
     *
     * @return string
     */
    public static function prettySql($sql)
    {
        if (!\class_exists('\SqlFormatter')) {
            return $sql;
        }
        // whitespace only, don't highlight
        $sql = SqlFormatter::format($sql, false);
        // SqlFormatter borks bound params
        $sql = \strtr($sql, array(
            ' : ' => ' :',
            ' =: ' => ' = :',
        ));
        return $sql;
    }

    /**
     * Prettify XML string
     *
     * @param string $xml XML string to prettify
     *
     * @return string
     */
    public static function prettyXml($xml)
    {
        if (!$xml) {
            // avoid "empty string supplied" error
            return $xml;
        }
        if (!self::$domDocument) {
            self::$domDocument = new DOMDocument();
            self::$domDocument->preserveWhiteSpace = false;
            self::$domDocument->formatOutput = true;
        }
        self::$domDocument->loadXML($xml);
        return self::$domDocument->saveXML();
    }

    /**
     * Generate a unique request id
     *
     * @return string
     */
    public static function requestId()
    {
        return \hash(
            'crc32b',
            self::getServerParam('REMOTE_ADDR', 'terminal')
                . (self::getServerParam('REQUEST_TIME_FLOAT') ?: self::getServerParam('REQUEST_TIME'))
                . self::getServerParam('REMOTE_PORT', '')
        );
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
     * Get $_SERVER param
     * Gets serverParams from serverRequest interface
     *
     * @param string $name    $_SERVER key/name
     * @param mixed  $default default value
     *
     * @return mixed
     */
    private static function getServerParam($name, $default = null)
    {
        if (!self::$serverParams) {
            $request = \bdk\Debug::getInstance()->request;
            self::$serverParams = $request->getServerParams();
        }
        return \array_key_exists($name, self::$serverParams)
            ? self::$serverParams[$name]
            : $default;
    }

    /**
     * Syntax-only is_callable() check
     * Additionally checks that $array[0] is an object
     *
     * @param string|array $val value to check
     *
     * @return bool
     */
    private static function isCallable($val)
    {
        return \is_array($val)
            ? \is_callable($val, true) && \is_object($val[0])
            : \is_callable($val);
    }

    /**
     * Parse string such as 128M
     *
     * @param string $size size
     *
     * @return int
     */
    private static function parseBytes($size)
    {
        if (\preg_match('/^([\d,.]+)\s?([kmgtp])b?$/i', $size, $matches)) {
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
        }
        return $size;
    }
}

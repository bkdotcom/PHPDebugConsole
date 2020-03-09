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

namespace bdk\Debug\Utility;

use DOMDocument;
use Exception;
use Psr\Http\Message\StreamInterface;
use SqlFormatter;

/**
 * Utility methods
 */
class Utilities
{

    /**
     * self closing / empty / void html tags
     *
     * Not including 'command' (obsolete) and 'keygen' (deprecated),
     *
     * @var array
     */
    public static $htmlEmptyTags = array('area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr');

    /**
     * Used by parseAttribString
     *
     * @var array
     */
    public static $htmlBoolAttr = array(
        // GLOBAL
        'contenteditable', 'hidden', 'itemscope',
        // 'spellcheck', // enum : true|false
        // 'translate', // enum : yes|no

        // FORM / INPUT
        // 'autocomplete', // enum : on|off
        'autofocus', 'checked', 'disabled', 'formnovalidate', 'multiple', 'novalidate', 'readonly', 'required', 'selected',

        // AUDIO / VIDEO / TRACK
        'autoplay', 'controls', 'default', 'loop', 'muted',

        // DETAILS
        'open',

        // IFRAME
        'frameborder',

        // IMG
        'ismap',

        // OBJECT
        'typemustmatch',

        // OL
        'reversed',

        // SCRIPT
        'async', 'defer', 'nomodule',

        // STYLE
        'scoped',

        // OBSOLETE / DEPRECATED / NEVER-A-THING
        // "allowfullscreen",
        // "allowpaymentrequest",
        'compact',  // <dir> and <ol>
        'nohref',   // <area>
        'noresize', // <frame>
        'noshade',  // <hr>
        'nowrap',   // dt, dd, td, th
        'scrolling',// <iframe>
        'seamless', // <iframe> - removed from draft
        'sortable', // <table> - removed from draft
    );

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
            } else {
                $arr[$key] = $val;
            }
        }
        return $arr;
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
            } elseif (!isset($arrayDef[$k2])) {
                $arrayDef[$k2] = $v2;
            } else {
                $arrayDef[$k2] = self::arrayMergeDeep($arrayDef[$k2], $v2);
            }
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
            } elseif (isset($array[$key])) {
                $array = $array[$key];
            } elseif ($key === '__count__') {
                return \count($array);
            } elseif ($key === '__end__') {
                \end($array);
                $path[] = \key($array);
            } elseif ($key === '__reset__') {
                \reset($array);
                $path[] = \key($array);
            } else {
                return null;
            }
        }
        return $array;
    }

    /**
     * Build attribute string
     *
     * Attributes will be sorted by name
     * class & style attributes may be provided as arrays
     * data-* attributes will be json-encoded (if non-string)
     * non data attribs with null value will not be output
     *
     * @param array|string $attribs key/values
     *
     * @return string
     * @see    https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#autofilling-form-controls:-the-autocomplete-attribute
     */
    public static function buildAttribString($attribs)
    {
        if (\is_string($attribs)) {
            return \rtrim(' ' . \trim($attribs));
        }
        $attribPairs = array();
        foreach ($attribs as $key => $val) {
            $val = self::buildAttribVal($key, $val);
            if ($val === null) {
                continue;
            }
            if ($val === '' && \in_array($key, array('class', 'style'))) {
                continue;
            }
            $attribPairs[] = $key . '="' . \htmlspecialchars($val) . '"';
        }
        \sort($attribPairs);
        return \rtrim(' ' . \implode(' ', $attribPairs));
    }

    /**
     * Build an html tag
     *
     * @param string       $tagName   tag name (ie "div" or "input")
     * @param array|string $attribs   key/value attributes
     * @param string       $innerhtml inner HTML if applicable
     *
     * @return string
     */
    public static function buildTag($tagName, $attribs = array(), $innerhtml = '')
    {
        $tagName = \strtolower($tagName);
        $attribStr = self::buildAttribString($attribs);
        return \in_array($tagName, self::$htmlEmptyTags)
            ? '<' . $tagName . $attribStr . ' />'
            : '<' . $tagName . $attribStr . '>' . $innerhtml . '</' . $tagName . '>';
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
        if ($format === 'us') {
            $val = $duration * 1000000;
            $unit = 'μs';
        } elseif ($format === 'ms') {
            $val = $duration * 1000;
            $unit = 'ms';
        } else {
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
            note: $_SERVER['argv'] could be populated with query string if register_argc_argv = On
        */
        $serverParams = $_SERVER;
        $isCliOrCron = \count(\array_filter(array(
            \defined('STDIN'),
            isset($serverParams['argv']) && \count($serverParams['argv']) > 1,
            !\array_key_exists('REQUEST_METHOD', $serverParams),
        ))) > 0;
        if ($isCliOrCron) {
            // TERM is a linux/unix thing
            $return = isset($serverParams['TERM']) || \array_key_exists('PATH', $serverParams)
                ? 'cli'
                : 'cli cron';
        } elseif (isset($serverParams['HTTP_X_REQUESTED_WITH']) && $serverParams['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
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
     * Is passed argument a simple array with all-integer in sequence from 0 to n?
     * empty array returns true
     *
     * @param mixed $val value to check
     *
     * @return bool
     */
    public static function isList($val)
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
     * Parse string -o- attributes into a key=>value array
     *
     * @param string $str        string to parse
     * @param bool   $dataDecode (true) whether to json_decode data attributes
     *
     * @return array
     */
    public static function parseAttribString($str, $dataDecode = true)
    {
        $attribs = array();
        $regexAttribs = '/\b([\w\-]+)\b(?: \s*=\s*(["\'])(.*?)\\2 | \s*=\s*(\S+) )?/xs';
        \preg_match_all($regexAttribs, $str, $matches);
        $keys = \array_map('strtolower', $matches[1]);
        $values = \array_replace($matches[3], \array_filter($matches[4], 'strlen'));
        foreach ($keys as $i => $k) {
            $attribs[$k] = $values[$i];
            if (\in_array($k, self::$htmlBoolAttr)) {
                $attribs[$k] = true;
            }
        }
        \ksort($attribs);
        foreach ($attribs as $k => $v) {
            if (\is_string($v)) {
                $attribs[$k] = \htmlspecialchars_decode($v);
            }
            $isDataAttrib = \strpos($k, 'data-') === 0;
            if ($isDataAttrib && $dataDecode) {
                $val = $attribs[$k];
                $attribs[$k] = \json_decode($attribs[$k], true);
                if ($attribs[$k] === null && $val !== 'null') {
                    $attribs[$k] = \json_decode('"' . $val . '"', true);
                }
            }
        }
        return $attribs;
    }

    /**
     * Parse HTML/XML tag
     *
     * returns array(
     *    'tagname' => string
     *    'attribs' => array
     *    'innerhtml' => string | null
     * )
     *
     * @param string $tag html tag to parse
     *
     * @return array|false
     */
    public static function parseTag($tag)
    {
        $regexTag = '#<([^\s>]+)([^>]*)>(.*)</\\1>#is';
        $regexTag2 = '#^<(?:\/\s*)?([^\s>]+)(.*?)\/?>$#s';
        $tag = \trim($tag);
        if (\preg_match($regexTag, $tag, $matches)) {
            $return = array(
                'tagname' => $matches[1],
                'attribs' => self::parseAttribString($matches[2]),
                'innerhtml' => $matches[3],
            );
        } elseif (\preg_match($regexTag2, $tag, $matches)) {
            $return = array(
                'tagname' => $matches[1],
                'attribs' => self::parseAttribString($matches[2]),
                'innerhtml' => null,
            );
        } else {
            $return = false;
        }
        return $return;
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
        $serverParams = $_SERVER;
        return \hash(
            'crc32b',
            (isset($serverParams['REMOTE_ADDR']) ? $serverParams['REMOTE_ADDR'] : 'terminal')
                . (isset($serverParams['REQUEST_TIME_FLOAT']) ? $serverParams['REQUEST_TIME_FLOAT'] : $serverParams['REQUEST_TIME'])
                . (isset($serverParams['REMOTE_PORT']) ? $serverParams['REMOTE_PORT'] : '')
        );
    }

    /**
     * Convert array attribute value to string
     *
     * Convert class/style array value to string
     * This function is not meant for data attributs
     *
     * @param string $key   attribute name (class|style)
     * @param array  $value classnames for class, key/value for style
     *
     * @return string|null
     */
    private static function buildAttribArrayVal($key, $value = array())
    {
        if ($key === 'class') {
            if (!\is_array($value)) {
                $value = \explode(' ', $value);
            }
            $value = \array_filter(\array_unique($value));
            \sort($value);
            return \implode(' ', $value);
        }
        if ($key === 'style') {
            $keyValues = array();
            foreach ($value as $k => $v) {
                $keyValues[] = $k . ':' . $v . ';';
            }
            \sort($keyValues);
            return \implode('', $keyValues);
        }
        return null;
    }

    /**
     * Convert boolean attribute value to string
     *
     * @param string $key   attribute name
     * @param bool   $value true|false
     *
     * @return string|null
     */
    private static function buildAttribBoolVal($key, $value = true)
    {
        if ($key === 'autocomplete') {
            return $value ? 'on' : 'off';
        }
        if ($key === 'spellcheck') {
            return $value ? 'true' : 'false';
        }
        if ($key === 'translate') {
            return $value ? 'yes' : 'no';
        }
        if ($value) {
            // even if not a recognized boolean attribute
            return $key;
        }
        return null;
    }

    /**
     * Converts attribute value to string
     *
     * @param string $key key
     * @param mixed  $val value
     *
     * @return string|null
     */
    private static function buildAttribVal(&$key, $val)
    {
        if (\is_int($key)) {
            $key = $val;
            $val = true;
        }
        $key = \strtolower($key);
        if (\strpos($key, 'data-') === 0) {
            if (!\is_string($val)) {
                $val = \json_encode($val);
            }
            return $val;
        }
        if ($val === null) {
            return null;
        }
        if (\is_bool($val)) {
            return self::buildAttribBoolVal($key, $val);
        }
        if (\is_array($val) || $key === 'class') {
            return self::buildAttribArrayVal($key, $val);
        }
        return \trim($val);
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
     * Syntax-only is_callable() check
     * Additionally checks that $array[0] is an object
     *
     * @param array $array variable to check
     *
     * @return bool
     */
    private static function isCallable($array)
    {
        return \is_callable($array, true) && \is_object($array[0]);
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

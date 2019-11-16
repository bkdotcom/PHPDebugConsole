<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use bdk\Debug;
use bdk\Debug\LogEntry;
use Psr\Http\Message\StreamInterface;
use DOMDocument;
use Exception;
use SqlFormatter;       // optional library

/**
 * Utility methods
 */
class Utilities
{

    protected static $domDocument;

    const INCL_ARGS = 1;

    /**
     * Used to determine caller info...
     * backtrace is walked and we stop when frame matches on of the set classes or filepaths
     *
     * @var array
     */
    private static $callerBreakers = array(
        'classes' => array('bdk\\Debug'),
        'classesRegex' => '/^bdk\\\\Debug\b/',  // we cache a regex of the classes
        'paths' => array(),
    );

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

    /**
     * add a new namespace, classname or filepath to be used to determine when to
     * stop iterrating over the backtrace when determining calling info
     *
     * @param string       $what 'class' or 'path'
     * @param string|array $val  'class' : classname(s)|namespace(s)
     *                           'path' : path(s)
     *
     * @return void
     */
    public static function addCallerBreaker($what, $val)
    {
        if ($what == 'class') {
            $what = 'classes';
        } elseif ($what == 'path') {
            $what = 'paths';
        }
        self::$callerBreakers[$what] = \array_merge(self::$callerBreakers[$what], (array) $val);
        self::$callerBreakers[$what] = \array_unique(self::$callerBreakers[$what]);
        if ($what == 'classes') {
            self::$callerBreakers['classesRegex'] = '/^('
                . \implode('|', \array_map('preg_quote', self::$callerBreakers['classes']))
                . ')\b/';
        }
    }

    /**
     * "dereference" array
     * returns a copy of the array with references removed
     *
     * @param array   $source source array
     * @param boolean $deep   (true) deep copy
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
            } elseif ($key == '__count__') {
                return \count($array);
            } elseif ($key == '__end__') {
                \end($array);
                $path[] = \key($array);
            } elseif ($key == '__reset__') {
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
        foreach ($attribs as $k => $v) {
            if (\is_int($k)) {
                $k = $v;
                $v = true;
            }
            $k = \strtolower($k);
            if (\strpos($k, 'data-') === 0) {
                if (!\is_string($v)) {
                    $v = \json_encode($v);
                }
            } elseif (\is_bool($v)) {
                $v = self::buildAttribBoolVal($k, $v);
            } elseif (\is_array($v) || $k === 'class') {
                $v = self::buildAttribArrayVal($k, $v);
            }
            if (
                \array_filter(array(
                    $v === null,
                    $v === ''
                        && \in_array($k, array('class', 'style'))
                ))
            ) {
                // don't include
                continue;
            }
            $v = \trim($v);
            $attribPairs[] = $k . '="' . \htmlspecialchars($v) . '"';
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
     * @param float        $duration  duration in seconds
     * @param string       $format    DateInterval format string, or 'us', 'ms', 's'
     * @param integer|null $precision decimal precision
     *
     * @return string
     */
    public static function formatDuration($duration, $format = 'auto', $precision = 4)
    {
        if ($format == 'auto') {
            if ($duration < 1 / 1000) {
                $format = 'us';
            } elseif ($duration < 1) {
                $format = 'ms';
            } elseif ($duration < 60) {
                $format = 's';
            } elseif ($duration < 3600) {
                $format = '%im %Ss'; // M:SS
            } else {
                $format = '%hh %Im %Ss'; // H:MM:SS
            }
        }
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
        if ($format == 'us') {
            $val = $duration * 1000000;
            $unit = 'μs';
        } elseif ($format == 'ms') {
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
     * Get all HTTP header key/values as an associative array for the current request.
     *
     * @return string[string] The HTTP header key/value pairs.
     */
    public static function getAllHeaders()
    {
        if (\function_exists('getallheaders')) {
            return \getallheaders();
        }
        $headers = array();
        $copyServer = array(
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        );
        foreach ($_SERVER as $key => $value) {
            if (\substr($key, 0, 5) === 'HTTP_') {
                $key = \substr($key, 5);
                if (!isset($copyServer[$key]) || !isset($_SERVER[$key])) {
                    $key = \str_replace(' ', '-', \ucwords(\strtolower(\str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } elseif (isset($copyServer[$key])) {
                $headers[$copyServer[$key]] = $value;
            }
        }
        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $basicPass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
                $headers['Authorization'] = 'Basic ' . \base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basicPass);
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }
        return $headers;
    }

    /**
     * Convert size int into "1.23 kB"
     *
     * @param integer|string $size      bytes or similar to "1.23M"
     * @param boolean        $returnInt return integer?
     *
     * @return string|integer
     */
    public static function getBytes($size, $returnInt = false)
    {
        if (\is_string($size) && \preg_match('/^([\d,.]+)\s?([kmgtp])b?$/i', $size, $matches)) {
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
        if ($returnInt) {
            return (int) $size;
        }
        $units = array('B','kB','MB','GB','TB','PB');
        $i = \floor(\log((float) $size, 1024));
        $pow = \pow(1024, $i);
        $size = $pow == 0
            ? '0 B'
            : \round($size / $pow, 2) . ' ' . $units[$i];
        return $size;
    }

    /**
     * Returns information regarding previous call stack position
     * call_user_func() and call_user_func_array() are skipped
     *
     * Information returned:
     *     function : function/method name
     *     class :    fully qualified classname
     *     file :     file
     *     line :     line number
     *     type :     "->": instance call, "::": static call, null: not object oriented
     *
     * If a method is defined as static:
     *    the class value will always be the class in which the method was defined,
     *    type will always be "::", even if called with an ->
     *
     * @param integer $offset Adjust how far to go back
     * @param integer $flags  optional INCL_ARGS
     *
     * @return array
     */
    public static function getCallerInfo($offset = 0, $flags = 0)
    {
        /*
            backtrace:
            index 0 is current position
            file/line are calling _from_
            function/class are what's getting called

            Must get at least backtrace 13 frames to account for potential framework loggers
        */
        $options = DEBUG_BACKTRACE_PROVIDE_OBJECT;
        if (!($flags & self::INCL_ARGS)) {
            $options = $options | DEBUG_BACKTRACE_IGNORE_ARGS;
        }
        $backtrace = \debug_backtrace($options, 13);
        $numFrames = \count($backtrace);
        for ($i = $numFrames - 1; $i > 1; $i--) {
            if (isset($backtrace[$i]['class']) && \preg_match(self::$callerBreakers['classesRegex'], $backtrace[$i]['class'])) {
                break;
            }
            foreach (self::$callerBreakers['paths'] as $path) {
                if (isset($backtrace[$i]['file']) && \strpos($backtrace[$i]['file'], $path) === 0) {
                    $i++;
                    break 2;
                }
            }
        }
        /*
            file/line values may be missing... if frame called via core PHP function/method
        */
        for ($i = $i + $offset; $i < $numFrames; $i++) {
            if (isset($backtrace[$i]['line'])) {
                break;
            }
        }
        return self::getCallerInfoBuild(\array_slice($backtrace, $i));
    }

    /**
     * Returns a sent/pending response header value
     *
     * @param string $key default = 'Content-Type', header to return
     *
     * @return string (empty string if not emitted)
     * @req    php >= 5
     */
    public static function getEmittedHeader($key = 'Content-Type')
    {
        $value = '';
        $headers = \headers_list();
        foreach ($headers as $header) {
            if (\preg_match('/^' . $key . ':\s*([^;]*)/i', $header, $matches)) {
                $value = $matches[1];
                break;
            }
        }
        return $value;
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
            return $dirA == $dirB
                ? \strnatcasecmp($valA, $valB)
                : \strnatcasecmp($dirA, $dirB);
        });
        return $includedFiles;
    }

    /**
     * Returns cli, cron, ajax, or http
     *
     * @return string cli | cron | ajax | http
     */
    public static function getInterface()
    {
        $return = 'http';
        /*
            note: $_SERVER['argv'] could be populated with query string if
            register_argc_argv = On
        */
        $isCliOrCron = \count(\array_filter(array(
            \defined('STDIN'),
            isset($_SERVER['argv']) && \count($_SERVER['argv']) > 1,
            !\array_key_exists('REQUEST_METHOD', $_SERVER),
        ))) > 0;
        if ($isCliOrCron) {
            // TERM is a linux/unix thing
            $return = isset($_SERVER['TERM']) || \array_key_exists('PATH', $_SERVER)
                ? 'cli'
                : 'cron';
        } elseif (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            $return = 'ajax';
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
     * Checks if a given string is base64 encoded
     *
     * @param string $str string to check
     *
     * @return boolean
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
     * @return boolean
     */
    public static function isList($val)
    {
        if (!\is_array($val)) {
            return false;
        }
        $keys = \array_keys($val);
        foreach ($keys as $i => $key) {
            if ($i != $key) {
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
     * @return boolean
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
     * @param string  $str        string to parse
     * @param boolean $dataDecode (true) whether to json_decode data attributes
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
        // whitespace only, don't hightlight
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
            (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'terminal')
                . (isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : $_SERVER['REQUEST_TIME'])
                . (isset($_SERVER['REMOTE_PORT']) ? $_SERVER['REMOTE_PORT'] : '')
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
        if ($key == 'class') {
            if (!\is_array($value)) {
                $value = \explode(' ', $value);
            }
            $value = \array_filter(\array_unique($value));
            \sort($value);
            $value = \implode(' ', $value);
        } elseif ($key == 'style') {
            $keyValues = array();
            foreach ($value as $k => $v) {
                $keyValues[] = $k . ':' . $v . ';';
            }
            \sort($keyValues);
            $value = \implode('', $keyValues);
        } else {
            $value = null;
        }
        return $value;
    }

    /**
     * Convert boolean attribute value to string
     *
     * @param string  $key   attribute name
     * @param boolean $value true|false
     *
     * @return string|null
     */
    private static function buildAttribBoolVal($key, $value = true)
    {
        if ($key == 'autocomplete') {
            $value = $value ? 'on' : 'off';
        } elseif ($key == 'spellcheck') {
            $value = $value ? 'true' : 'false';
        } elseif ($key == 'translate') {
            $value = $value ? 'yes' : 'no';
        } elseif ($value) {
            // even if not a recognized boolean attribute
            $value = $key;
        } else {
            $value = null;
        }
        return $value;
    }

    /**
     * Build callerInfo array from given backtrace segment
     *
     * @param array $backtrace backtrace
     *
     * @return array
     */
    private static function getCallerInfoBuild($backtrace)
    {
        $return = array(
            'file' => null,
            'line' => null,
            'function' => null,
            'class' => null,
            'type' => null,
        );
        $numFrames = \count($backtrace);
        $iLine = 0;
        $iFunc = 1;
        if (isset($backtrace[$iFunc])) {
            // skip over call_user_func / call_user_func_array / invoke
            $class = isset($backtrace[$iFunc]['class'])
                ? $backtrace[$iFunc]['class']
                : null;
            if (
                \in_array($backtrace[$iFunc]['function'], array('call_user_func', 'call_user_func_array'))
                || $class == 'ReflectionMethod'
                    && $backtrace[$iFunc]['function'] == 'invoke'
            ) {
                $iLine++;
                $iFunc++;
            }
        }
        if (isset($backtrace[$iFunc])) {
            $return = \array_merge($return, $backtrace[$iFunc]);
            unset($return['object']);
            if ($return['type'] == '->') {
                $return['class'] = \get_class($backtrace[$iFunc]['object']);
            }
        }
        if (isset($backtrace[$iLine])) {
            $return['file'] = $backtrace[$iLine]['file'];
            $return['line'] = $backtrace[$iLine]['line'];
        } else {
            $return['file'] = $backtrace[$numFrames - 1]['file'];
            $return['line'] = 0;
        }
        return $return;
    }

    /**
     * Syntax-only is_callable() check
     * Additionally checks that $array[0] is an object
     *
     * @param array $array variable to check
     *
     * @return boolean
     */
    private static function isCallable($array)
    {
        return \is_callable($array, true) && \is_object($array[0]);
    }
}

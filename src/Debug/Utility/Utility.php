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

namespace bdk\Debug;

use DOMDocument;
use Exception;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use ReflectionObject;
use SqlFormatter;

/**
 * Utility methods
 */
class Utility
{

    const IS_CALLABLE_ARRAY_ONLY = 1;
    const IS_CALLABLE_OBJ_ONLY = 2;
    const IS_CALLABLE_STRICT = 3;
    const IS_BASE64_LENGTH = 1;
    const IS_BASE64_CHAR_STAT = 2;

    protected static $domDocument;

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
        $file = '';
        $line = 0;
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
     * @param string   $format    DateInterval format string, or 'auto', us', 'ms', 's', or 'sec'
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
            $dateInterval->h = (int) $hours;
            $dateInterval->i = (int) $min;
            $dateInterval->s = (int) $sec;
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
     * Get friendly classname for given classname or object
     * This is primarily useful for anonymous classes
     *
     * @param object|class-string $mixed Reflector instance, object, or classname
     *
     * @return string
     */
    public static function friendlyClassName($mixed)
    {
        $reflector = $mixed instanceof ReflectionClass
            ? $mixed
            : (\is_object($mixed)
                ? new ReflectionObject($mixed)
                : new ReflectionClass($mixed)
            );
        if (PHP_VERSION_ID < 70000 || $reflector->isAnonymous() === false) {
            return $reflector->getName();
        }
        $parentClassRef = $reflector->getParentClass();
        $extends = $parentClassRef ? $parentClassRef->getName() : null;
        return ($extends ?: \current($reflector->getInterfaceNames()) ?: 'class') . '@anonymous';
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
            $valA = \str_replace('_', '0', $valA);
            $valB = \str_replace('_', '0', $valB);
            $dirA = \dirname($valA);
            $dirB = \dirname($valB);
            return $dirA === $dirB
                ? \strnatcasecmp($valA, $valB)
                : \strnatcasecmp($dirA, $dirB);
        });
        return $includedFiles;
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
     * FYI:
     *   md5: 32-char hex
     *   sha1:  40-char hex
     *
     * @param string $val  value to check
     * @param int    $opts (IS_BASE64_LENGTH | IS_BASE64_CHAR_STAT)
     *
     * @return bool
     */
    public static function isBase64Encoded($val, $opts = 3)
    {
        if (\is_string($val) === false) {
            return false;
        }
        $val = \trim($val);
        $isHex = \preg_match('/^[0-9A-F]+$/i', $val) === 1;
        if ($isHex) {
            return false;
        }
        // only allow whitspace at beginning and end of lines
        $regex = '#^'
            . '([ \t]*[a-zA-Z0-9+/]*[ \t]*[\r\n]+)*'
            . '([ \t]*[a-zA-Z0-9+/]*={0,2})' // last line may have "=" padding at tend"
            . '$#';
        if (\preg_match($regex, $val) !== 1) {
            return false;
        }
        $valNoSpace = \preg_replace('#\s#', '', $val);
        if ($opts & self::IS_BASE64_LENGTH) {
            $mod = \strlen($valNoSpace) % 4;
            if ($mod > 0) {
                return false;
            }
        }
        if ($opts & self::IS_BASE64_CHAR_STAT && self::isBase64EncodedTestStats($valNoSpace) === false) {
            return false;
        }
        $data = \base64_decode($valNoSpace, true);
        return $data !== false;
    }

    /**
     * Test if value is callable
     *
     * @param string|array $val  value to check
     * @param int          $opts bitmask of IS_CALLABLE_x constants
     *                         default:  IS_CALLABLE_ARRAY_ONLY | IS_CALLABLE_OBJ_ONLY | IS_CALLABLE_STRICT
     *                         IS_CALLABLE_ARRAY_ONLY
     *                              must be array(x, 'method')
     *                         IS_CALLABLE_OBJ_ONLY
     *                              must be array(obj, 'methodName')
     *                         IS_CALLABLE_STRICT
     *
     * @return bool
     */
    public static function isCallable($val, $opts = 0b111)
    {
        $syntaxOnly = ($opts & self::IS_CALLABLE_STRICT) !== self::IS_CALLABLE_STRICT;
        if (\is_array($val) === false) {
            return $opts & self::IS_CALLABLE_ARRAY_ONLY
                ? false
                : \is_callable($val, $syntaxOnly);
        }
        if (!isset($val[0])) {
            return false;
        }
        if ($opts & self::IS_CALLABLE_OBJ_ONLY && \is_object($val[0]) === false) {
            return false;
        }
        return \is_callable($val, $syntaxOnly);
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
        if (!\is_string($val)) {
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
     * Test if value is a json encoded  object or array
     *
     * @param string $val value to test
     *
     * @return bool
     */
    public static function isJson($val)
    {
        if (!\is_string($val)) {
            return false;
        }
        if (!\preg_match('/^(\[.+\]|\{.+\})$/s', $val)) {
            return false;
        }
        \json_decode($val);
        return \json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Test if value is output from `serialize()`
     * Will return false if contains a object other than stdClass
     *
     * @param string $val value to test
     *
     * @return bool
     */
    public static function isSerializedSafe($val)
    {
        if (!\is_string($val)) {
            return false;
        }
        if (\preg_match('/^b:[01];$/', $val)) {
            // bool
            return true;
        }
        $isSerialized = false;
        $matches = array();
        if (\preg_match('/^(N|i:\d+|d:\d+\.\d+|s:\d+:".*");$/', $val)) {
            // null, int, float, or string
            $isSerialized = true;
        } elseif (\preg_match('/^(?:a|O:8:"stdClass"):\d+:\{(.+)\}$/', $val, $matches)) {
            // appears to be a serialized array or stdClass object
            $isSerialized = true;
            if (\preg_match('/[OC]:\d+:"((?!stdClass)[^"])*":\d+:/', $matches[1])) {
                // appears to contain a serialized obj other than stdClass
                $isSerialized = false;
            }
        }
        if ($isSerialized) {
            \set_error_handler(function () {
                // ignore unserialize errors
            });
            $isSerialized = \unserialize($val) !== false;
            \restore_error_handler();
        }
        return $isSerialized;
    }

    /**
     * Throwable is a PHP 7+ thing
     *
     * @param mixed $val Value to test
     *
     * @return bool
     */
    public static function isThrowable($val)
    {
        return $val instanceof \Error || $val instanceof \Exception;
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
     * The goal is to format whitespace without effecting the encoding
     *
     * @param string $json        JSON string to prettify
     * @param int    $encodeFlags (0) specify json_encode flags
     *                               we will always add JSON_PRETTY_PRINT
     *                               we will add JSON_UNESCAPED_SLASHES if source doesn't contain escaped slashes
     *                               we will add JSON_UNESCAPED_UNICODE IF source doesn't contain escaped unicode
     *
     * @return string
     */
    public static function prettyJson($json, $encodeFlags = 0)
    {
        $flags = $encodeFlags | JSON_PRETTY_PRINT;
        if (\strpos($json, '\\/') === false) {
            // json doesn't appear to contain escaped slashes
            $flags |= JSON_UNESCAPED_SLASHES;
        }
        if (\strpos($json, '\\u') === false) {
            // json doesn't appear to contain encoded unicode
            $flags |= JSON_UNESCAPED_UNICODE;
        }
        return \json_encode(\json_decode($json), $flags);
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
        if (!\class_exists('SqlFormatter')) {
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
     * Interpolates context values into the message placeholders.
     *
     * @param string|object $message      message (string, or obj with __toString)
     * @param array|object  $context      optional array of key/values or object
     *                                      if array: interpolated values get removed
     * @param array         $placeholders gets set to the placeholders found in message
     *
     * @return string
     * @throws \RuntimeException if non-stringable object provided for $message
     * @throws \InvalidArgumentException if $context not array or object
     */
    public static function strInterpolate($message, $context = array(), &$placeholders = array())
    {
        // build a replacement array with braces around the context keys
        if (!\is_array($context) && !\is_object($context)) {
            throw new \InvalidArgumentException(
                'Expected array or object for $context. ' . \gettype($context) . ' provided'
            );
        }
        if (\is_object($message)) {
            if (\method_exists($message, '__toString') === false) {
                throw new \RuntimeException(__METHOD__ . ': ' . \get_class($message) . 'is not stringable');
            }
            $message = (string) $message;
        }
        $matches = array();
        \preg_match_all('/\{([a-zA-Z0-9._-]+)\}/', $message, $matches);
        $placeholders = \array_unique($matches[1]);
        $replaceVals = self::strInterpolateValues($placeholders, $context);
        return \strtr((string) $message, $replaceVals);
    }

    /**
     * Get substitution values for strInterpolate
     *
     * @param array        $placeholders keys
     * @param array|object $context      values
     *
     * @return string[] key->value array
     */
    private static function strInterpolateValues($placeholders, $context)
    {
        $replace = array();
        $isArrayAccess = \is_array($context) || $context instanceof \ArrayAccess;
        foreach ($placeholders as $key) {
            $val = $isArrayAccess
                ? (isset($context[$key]) ? $context[$key] : null)
                : (isset($context->{$key}) ? $context->{$key} : null);
            if (
                \array_filter(array(
                    $val === null,
                    \is_array($val),
                    \is_object($val) && \method_exists($val, '__toString') === false,
                ))
            ) {
                continue;
            }
            $replace['{' . $key . '}'] = (string) $val;
        }
        return $replace;
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
     * Test if character distribution is what we would expect for a bse 64 string
     * This is quite unreliable as encoding isn't random
     *
     * @param string $val string alreadl striped of whitespace
     *
     * @return bool
     */
    private static function isBase64EncodedTestStats($val)
    {
        $count = 0;
        $valNoPadding = \preg_replace('/=+$/', '', $val, -1, $count);
        $strlen = \strlen($valNoPadding);
        if ($strlen === 0) {
            return false;
        }
        if ($count > 0) {
            // if val ends with "=" it's pretty safe to assume base64
            return true;
        }
        $stats = array(
            'lower' => array(
                \preg_match_all('/[a-z]/', $val),
                40.626,
                8,
            ),
            'upper' => array(
                \preg_match_all('/[A-Z]/', $val),
                40.625,
                8,
            ),
            'num' => array(
                \preg_match_all('/[0-9]/', $val),
                15.625,
                8,
            ),
            'other' => array(
                \preg_match_all('/[+\/]/', $val),
                3.125,
                5,
            )
        );
        foreach ($stats as $stat) {
            $per = $stat[0] * 100 / $strlen;
            $diff = \abs($per - $stat[1]);
            if ($diff > $stat[2]) {
                return false;
            }
        }
        return true;
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
        if (\is_int($size)) {
            return $size;
        }
        if (\preg_match('/^[\d,]+$/', $size)) {
            return (int) \str_replace(',', '', $size);
        }
        $matches = array();
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
            return (int) $size;
        }
        return false;
    }
}

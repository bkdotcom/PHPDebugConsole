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
        // iterate over keys more efficient than `$val === array_values($val)`
        $keys = \array_keys($val);
        foreach ($keys as $i => $key) {
            if ($key !== $i) {
                return false;
            }
        }
        return true;
    }

    /**
     * Applies the callback to all leafs of the given array
     *
     * @param callable $callback Callable to be applied
     * @param array    $input    Input array
     *
     * @return array
     */
    public static function arrayMapRecursive($callback, $input)
    {
        $return = array();
        foreach ($input as $key => $val) {
            if (\is_array($val)) {
                $return[$key] = self::arrayMapRecursive($callback, $val);
                continue;
            }
            $return[$key] = $callback($val);
        }
        return $return;
    }

    /**
     * Recursively merge arrays
     *
     * @param array $arrayDef   default array
     * @param array $array2,... array to merge
     *
     * @return array
     */
    public static function arrayMergeDeep($arrayDef, $array2)
    {
        $mergeArrays = \func_get_args();
        \array_shift($mergeArrays);
        while ($mergeArrays) {
            $array2 = \array_shift($mergeArrays);
            $arrayDef = static::arrayMergeDeepWalk($arrayDef, $array2);
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
        $statusCode = (int) $statusCode;
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
        $syntaxOnly = $opts & self::IS_CALLABLE_STRICT !== self::IS_CALLABLE_STRICT;
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
            pre-text / prevent "is_file() expects parameter 1 to be a valid path, string given"
        */
        if (\preg_match('#(://|[\r\n\x00])#', $val) === 1) {
            return false;
        }
        return \is_file($val);
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
     * Interpolates context values into the message placeholders.
     *
     * @param string|object $message message (string, or obj with __toString)
     * @param array|object  $context optional array of key/values or object
     *                                    if array: interpolated values get removed
     * @param bool          $unset   (false) whether to unset values from context (if array)
     *
     * @return string
     * @throws \RuntimeException if non-stringable object provided for $message
     * @throws \InvalidArgumentException if $context not array or object
     */
    public function strInterpolate($message, &$context = array(), $unset = false)
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
        \preg_match_all('/\{([a-zA-Z0-9.]+)\}/', $message, $matches);
        $placeholders = \array_unique($matches[1]);
        $replaceVals = self::strInterpolateValues($placeholders, $context);
        if ($unset && \is_array($context)) {
            $context = \array_diff_key($context, \array_flip($placeholders));
        }
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
     * Merge 2nd array into first
     *
     * @param array $arrayDef default array
     * @param array $array2   array 2
     *
     * @return array
     */
    private static function arrayMergeDeepWalk($arrayDef, $array2)
    {
        foreach ($array2 as $k2 => $v2) {
            if (!\is_array($v2) || static::isCallable($v2)) {
                // not array or appears to be a callable
                if (\is_int($k2) === false) {
                    $arrayDef[$k2] = $v2;
                    continue;
                }
                // append int-key'd values if not already in_array
                if (\in_array($v2, $arrayDef)) {
                    // already in array
                    continue;
                }
                // append it
                $arrayDef[] = $v2;
                continue;
            }
            if (!isset($arrayDef[$k2]) || !\is_array($arrayDef[$k2]) || static::isCallable($arrayDef[$k2])) {
                $arrayDef[$k2] = $v2;
                continue;
            }
            // both values are arrays... merge em
            $arrayDef[$k2] = static::arrayMergeDeep($arrayDef[$k2], $v2);
        }
        return $arrayDef;
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

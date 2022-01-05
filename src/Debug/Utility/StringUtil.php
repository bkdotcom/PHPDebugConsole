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

namespace bdk\Debug\Utility;

use DOMDocument;
use SqlFormatter;

/**
 * String utility methods
 */
class StringUtil
{
    const IS_BASE64_LENGTH = 1;
    const IS_BASE64_CHAR_STAT = 2;

    protected static $domDocument;

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
    public static function interpolate($message, $context = array(), &$placeholders = array())
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
        $replaceVals = self::interpolateValues($placeholders, $context);
        return \strtr((string) $message, $replaceVals);
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
        if (self::isBase64RegexTest($val) === false) {
            return false;
        }
        $valNoSpace = \preg_replace('#\s#', '', $val);
        $mod = \strlen($valNoSpace) % 4;
        if ($opts & self::IS_BASE64_LENGTH && $mod > 0) {
            return false;
        }
        if ($opts & self::IS_BASE64_CHAR_STAT && self::isBase64EncodedTestStats($valNoSpace) === false) {
            return false;
        }
        return \base64_decode($valNoSpace, true) !== false;
    }

    /**
     * Test if value is a json encoded object or array
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
        $isSerialized = false;
        $matches = array();
        if (\preg_match('/^(N|b:[01]|i:\d+|d:\d+\.\d+|s:\d+:".*");$/', $val)) {
            // null, bool, int, float, or string
            $isSerialized = true;
        } elseif (\preg_match('/^(?:a|O:8:"stdClass"):\d+:\{(.+)\}$/', $val, $matches)) {
            // appears to be a serialized array or stdClass object
            // make sure does not contain a serialized obj other than stdClass
            $isSerialized = \preg_match('/[OC]:\d+:"((?!stdClass)[^"])*":\d+:/', $matches[1]) !== 1;
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
     *
     * @see https://github.com/jdorn/sql-formatter
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
            'lower' => array(\preg_match_all('/[a-z]/', $val), 40.626, 8),
            'upper' => array(\preg_match_all('/[A-Z]/', $val), 40.625, 8),
            'num' => array(\preg_match_all('/[0-9]/', $val), 15.625, 8),
            'other' => array(\preg_match_all('/[+\/]/', $val), 3.125, 5),
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
     * Test if value matches basic base64 regex
     *
     * @param string $val string to test
     *
     * @return bool
     */
    private static function isBase64RegexTest($val)
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
            . '([ \t]*[a-zA-Z0-9+/]*={0,2})' // last line may have "=" padding at the end"
            . '$#';
        return \preg_match($regex, $val) === 1;
    }

    /**
     * Get substitution values for `interpolate()`
     *
     * @param array        $placeholders keys
     * @param array|object $context      values
     *
     * @return string[] key->value array
     */
    private static function interpolateValues($placeholders, $context)
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
}

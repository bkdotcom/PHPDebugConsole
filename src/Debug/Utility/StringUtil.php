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

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\StringUtilHelperTrait;
use bdk\HttpMessage\Utility\ContentType;
use bdk\HttpMessage\Utility\Stream as StreamUtility;
use DOMDocument;
use finfo;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use SqlFormatter;

/**
 * String utility methods
 */
class StringUtil
{
    use StringUtilHelperTrait;

    const IS_BASE64_LENGTH = 1;
    const IS_BASE64_CHAR_STAT = 2;

    /** @var DOMDocument|null */
    protected static $domDocument;
    /** @var array Interpolation context*/
    private static $interpContext = array();
    /** @var bool */
    private static $interpIsArrayAccess = false;

    /**
     * Find the longest common prefix for provided strings
     *
     * @param string[] $strings Strings to compare
     *
     * @return string
     */
    public static function commonPrefix(array $strings)
    {
        self::assertStrings($strings);

        if (empty($strings)) {
            return '';
        }

        \sort($strings);
        $s1 = $strings[0];    // First string
        $s2 = \end($strings); // Last string
        $len = \min(\strlen($s1), \strlen($s2));
        for ($i = 0; $i < $len && $s1[$i] === $s2[$i]; $i++);

        return \substr($s1, 0, $i);
    }

    /**
     * Compare two values specifying operator
     *
     * By default, returns -1 if the first version is lower than the second,
     *     0 if they are equal, and 1 if the second is lower.
     *
     * When specifying a non "strcmp" function for the optional operator
     *   return true if the relationship is the one specified by the operator, false otherwise.
     *
     * @param mixed  $valA     Value A
     * @param mixed  $valB     Value B
     * @param string $operator Comparison operator
     *
     * @return int|bool
     *
     * @throws \InvalidArgumentException on invalid operator
     */
    public static function compare($valA, $valB, $operator = 'strnatcmp')
    {
        // phpcs:ignore SlevomatCodingStandard.Arrays.DisallowPartiallyKeyed.DisallowedPartiallyKeyed
        $operators = array(
            'strcmp',
            'strcasecmp',
            'strnatcmp', null => 'strnatcmp',
            'strnatcasecmp',
            '===',
            '==', 'eq' => '==', '=' => '==',
            '!==',
            '!=', 'ne' => '!=', '<>' => '!=',
            '>=', 'ge' => '>=',
            '<=', 'le' => '<=',
            '>', 'gt' => '>',
            '<', 'lt' => '<',
        );
        if (isset($operators[$operator]) && \is_numeric($operator) === false) {
            // one of the aliases
            $operator = $operators[$operator];
        } elseif (\in_array($operator, $operators, true) === false) {
            throw new InvalidArgumentException(__METHOD__ . ' - Invalid operator passed');
        }
        if (\in_array($operator, ['===', '!=='], true) === false) {
            list($valA, $valB) = static::compareTypeJuggle($valA, $valB);
        }
        return static::doCompare($valA, $valB, $operator);
    }

    /**
     * Detect mime-type
     *
     * @param StreamInterface|string $val Value to inspect
     *
     * @return string
     */
    public static function contentType($val)
    {
        if ($val instanceof StreamInterface) {
            $val = StreamUtility::getContents($val);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $contentType = $finfo->buffer($val);
        if ($contentType !== ContentType::TXT) {
            return $contentType;
        }
        if (self::isJson($val)) {
            return ContentType::JSON;
        }
        if (self::isHtml($val)) {
            return ContentType::HTML;
        }
        return $contentType;
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param string|Stringable $message      message (string, or obj with __toString)
     * @param array|object      $context      optional key/value array or object
     * @param array             $placeholders gets set to the placeholders found in message
     *
     * @return string
     * @throws \InvalidArgumentException if $message or $context invalid
     */
    public static function interpolate($message, $context = array(), &$placeholders = array())
    {
        static::interpolateAssertArgs($message, $context);
        self::$interpContext = $context;
        self::$interpIsArrayAccess = \is_array($context) || $context instanceof \ArrayAccess;
        $matches = [];
        \preg_match_all('/\{([a-zA-Z0-9._\\/-]+)\}/', (string) $message, $matches);
        $placeholders = \array_unique($matches[1]);
        $replaceVals = self::interpolateValues($placeholders);
        self::$interpContext = array();
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
     * Test if value is html
     *
     * @param mixed $val value to test
     *
     * @return bool
     */
    public static function isHtml($val)
    {
        if (\is_string($val) === false) {
            return false;
        }
        if (\preg_match('/^\s*<!DOCTYPE html/ui', $val) === 1) {
            return true;
        }
        if (\preg_match('/^\s*<\?/u', $val) === 1) {
            return false;
        }
        $containsTag = \preg_match('/<([a-z]+|h[1-6])\b[^<]*>/', $val) === 1;
        $containsEntity = \preg_match('/&([a-z]{2,23}|#\d+|#x[0-9a-f]+);/i', $val) === 1;
        return $containsTag || $containsEntity;
    }

    /**
     * Test if value is a json encoded object or array
     *
     * @param mixed $val value to test
     *
     * @return bool
     */
    public static function isJson($val)
    {
        if (\is_string($val) === false) {
            return false;
        }
        if (\preg_match('/^\s*(\[.+\]|\{.+\})\s*$/s', $val) !== 1) {
            return false;
        }
        if (\function_exists('json_validate')) {
            return \json_validate($val, JSON_INVALID_UTF8_IGNORE);
        }
        \json_decode($val); // @codeCoverageIgnore
        return \json_last_error() === JSON_ERROR_NONE; // @codeCoverageIgnore
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
        if (\is_string($val) === false) {
            return false;
        }
        $isSerialized = false;
        $matches = array();
        if (\preg_match('/^(N|b:[01]|i:\d+|d:\d+\.\d+|s:\d+:".*");$/s', $val)) {
            // null, bool, int, float, or string
            $isSerialized = true;
        } elseif (\preg_match('/^(?:a|O:8:"stdClass"):\d+:\{(.+)\}$/s', $val, $matches)) {
            // appears to be a serialized array or stdClass object
            // make sure does not contain a serialized obj other than stdClass
            $isSerialized = \preg_match('/[OC]:\d+:"((?!stdClass)[^"])*":\d+:/', $matches[1]) !== 1;
        }
        if ($isSerialized) {
            \set_error_handler(static function () {
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
     * Note that HTML with a DocType declaration will return false, but without it may return true
     *
     * @param string $str string to test
     *
     * @return bool
     */
    public static function isXml($str)
    {
        if (\is_string($str) === false) {
            return false;
        }
        if (empty($str)) {
            return false;
        }
        if (\preg_match('/^\s*<!DOCTYPE html/u', $str) === 1) {
            // with/without byte-order mark
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
     * @param string $json           JSON string to prettify
     * @param int    $encodeFlags    (0) specify json_encode flags
     *                                 we will add JSON_UNESCAPED_SLASHES if source doesn't contain escaped slashes
     *                                 we will add JSON_UNESCAPED_UNICODE IF source doesn't contain escaped unicode
     * @param int    $encodeFlagsAdd (JSON_PRETTY_PRINT) additional flags to add
     *
     * @return string|false false if invalid json
     */
    public static function prettyJson($json, $encodeFlags = 0, $encodeFlagsAdd = JSON_PRETTY_PRINT)
    {
        $flags = $encodeFlags | $encodeFlagsAdd;
        if (\strpos($json, '\\/') === false) {
            // json doesn't appear to contain escaped slashes
            $flags |= JSON_UNESCAPED_SLASHES;
        }
        if (\strpos($json, '\\u') === false) {
            // json doesn't appear to contain encoded unicode
            $flags |= JSON_UNESCAPED_UNICODE;
        }
        $decoded = \json_decode($json);
        return \json_last_error() === JSON_ERROR_NONE
            ? \json_encode($decoded, $flags)
            : false;
    }

    /**
     * Prettify SQL string
     *
     * @param string $sql     SQL string to prettify
     * @param bool   $success Was prettification successful?
     *
     * @return string
     *
     * @see https://github.com/jdorn/sql-formatter
     */
    public static function prettySql($sql, &$success = false)
    {
        if (\class_exists('SqlFormatter') === false) {
            return $sql; // @codeCoverageIgnore
        }
        $success = true;
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
}

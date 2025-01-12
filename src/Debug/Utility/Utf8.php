<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\Utf8Buffer;

/**
 * Validate Utf8 / "highlight" non-utf8, control, & whitespace characters
 *
 * @link https://www.cl.cam.ac.uk/~mgk25/ucs/examples/UTF-8-test.txt
 * @link http://www.i18nqa.com/debug/utf8-debug.html
 */
class Utf8
{
    const TYPE_OTHER = 'other';
    const TYPE_UTF8 = 'utf8';
    const TYPE_UTF8_CONTROL = 'utf8Control'; // control character (sans \r\n\t)

    /** @var Utf8Buffer|null */
    private static $buffer;

    /**
     * Convert code point to character
     *
     * @param int $codePoint Unicode code-point
     *
     * @return string
     */
    public static function chr($codePoint)
    {
        if ($codePoint <= 0x7F) {
            // Plain ASCII
            return \chr($codePoint);
        }
        if ($codePoint <= 0x07FF) {
            // 2-byte unicode (range 0x80-0x7FF)
            return ''
                . \chr((($codePoint >> 6) & 0x1F) | 0xC0)
                . \chr((($codePoint >> 0) & 0x3F) | 0x80);
        }
        if ($codePoint <= 0xFFFF) {
            // 3-byte unicode (range 0x800-0xFFFF)
            return ''
                . \chr((($codePoint >> 12) & 0x0F) | 0xE0)
                . \chr((($codePoint >>  6) & 0x3F) | 0x80)
                . \chr((($codePoint >>  0) & 0x3F) | 0x80);
        }
        if ($codePoint <= 0x10FFFF) {
            // 4-byte unicode (range 0x10000-1114111)
            return ''
                . \chr((($codePoint >> 18) & 0x07) | 0xF0)
                . \chr((($codePoint >> 12) & 0x3F) | 0x80)
                . \chr((($codePoint >>  6) & 0x3F) | 0x80)
                . \chr((($codePoint >>  0) & 0x3F) | 0x80);
        }
    }

    /**
     * Determine if string is UTF-8 encoded
     *
     * In addition, if valid UTF-8, will also report whether string contains
     * control, or other special characters that could otherwise go unnoticed
     *
     * @param string $str string to check
     *
     * @return bool
     */
    public static function isUtf8($str)
    {
        $buffer = new Utf8Buffer($str);
        return $buffer->isUtf8();
    }

    /**
     * Get Unicode code point of character
     *
     * @param string $char Character to get code point for
     *
     * @return int|false The Unicode code point for the first character of string or false on failure.
     */
    public static function ord($char)
    {
        $ord = \ord($char[0]);
        if ($ord < 0x80) {
            return $ord;
        } elseif ($ord < 0xe0) {
            return ($ord - 0xc0 << 6) + \ord($char[1]) - 0x80;
        } elseif ($ord < 0xf0) {
            return ($ord - 0xe0 << 12)
                + (\ord($char[1]) - 0x80 << 6)
                + \ord($char[2]) - 0x80;
        } elseif ($ord < 0xf8) {
            return ($ord - 0xf0 << 18)
                + (\ord($char[1]) - 0x80 << 12)
                + (\ord($char[2]) - 0x80 << 6)
                + \ord($char[3]) - 0x80;
        }
        return false;
    }

    /**
     * mb_strcut implementation
     *
     * @param string   $str    The string being cut
     * @param int      $start  start position
     * @param int|null $length length in bytes
     *
     * @return string
     * @see    https://www.php.net/manual/en/function.mb-strcut.php
     */
    public static function strcut($str, $start, $length = null)
    {
        self::$buffer = new Utf8Buffer($str);
        $start = self::strcutGetStart($start);
        $length = $length !== null
            ? self::strcutGetLength($start, $length)
            : self::$buffer->strlen() - $start;
        self::$buffer->seek($start);
        return self::$buffer->read($length);
    }

    /**
     * Get string's length in bytes
     *
     * @param string $string string to calculate
     *
     * @return int
     */
    public static function strlen($string)
    {
        return \function_exists('mb_strlen') && ((int) \ini_get('mbstring.func_overload') & 2)
            ? \mb_strlen($string, '8bit')
            : \strlen($string);
    }

    /**
     * Attempt to convert string to UTF-8 encoding
     *
     * @param string $str string to convert
     *
     * @return string
     */
    public static function toUtf8($str)
    {
        if (\extension_loaded('mbstring') === false || \function_exists('iconv') === false) {
            return $str; // @codeCoverageIgnore
        }
        // 'Windows-1252' detection only seems to work in PHP-8 ?
        // we won't include... ISO-8859-1  too many false positive
        $encodings = ['ASCII', 'UTF-8'];
        $encoding = \mb_detect_encoding($str, $encodings, true);
        if ($encoding === false) {
            // Assume Windows-1252
            return self::toUtf8Unknown($str);
        }
        return $str;
    }

    /**
     * Find length value...
     *
     * @param int $start  Our start value
     * @param int $length User supplied length
     *
     * @return int
     */
    private static function strcutGetLength($start, $length)
    {
        $length = (int) $length;
        $strlen = self::$buffer->strlen();
        $end = $length >= 0
            ? $start + $length
            : $strlen + $length;
        if ($end >= $strlen) {
            return $strlen - $start;
        }
        $end++; // increment to offset the initial decrement below
        for ($i = 0; $i < 4, $end > 0; $i++) {
            $end--;
            self::$buffer->seek($end);
            if (self::$buffer->isOffsetUtf8()) {
                break;
            }
        }
        return \max($end - $start, 0);
    }

    /**
     * Find start position
     *
     * @param int $start User supplied start position
     *
     * @return int
     */
    private static function strcutGetStart($start)
    {
        if ($start <= 0) {
            return 0;
        }
        $start++; // increment so that we start at original
        for ($i = 0; $i < 4; $i++) {
            $start--;
            self::$buffer->seek($start);
            if ($start === 0 || self::$buffer->isOffsetUtf8()) {
                break;
            }
        }
        return $start;
    }

    /**
     * Attempt to convert string to UTF-8 when unable to determine current encoding
     *
     * @param string $str string to convert
     *
     * @return string
     */
    private static function toUtf8Unknown($str)
    {
        $strConv = \iconv('Windows-1252', 'UTF-8', $str);
        if ($strConv === false) {
            $strConv = \htmlentities($str, ENT_COMPAT);
            $strConv = \html_entity_decode($strConv, ENT_COMPAT, 'UTF-8');
        }
        return $strConv;
    }
}

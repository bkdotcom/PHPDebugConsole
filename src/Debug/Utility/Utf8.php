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

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\Utf8Buffer;
use bdk\Debug\Utility\Utf8Dump;

/**
 * Validate Utf8 / "highlight" non-utf8, control, & whitespace characters
 *
 * @link https://www.cl.cam.ac.uk/~mgk25/ucs/examples/UTF-8-test.txt
 * @link http://www.i18nqa.com/debug/utf8-debug.html
 */
class Utf8
{
    private static $buffer;

    /**
     * Highlight non-UTF-8, control, & "special" characters
     *
     * control & non-utf-8 chars are displayed as hex
     * "special" unicode-characters are displayed with the \uxxxx representation
     *
     * @param string $str     string containing binary
     * @param array  $options prefix, sanitizeNonBinary, useHtml
     *
     * @return string
     */
    public static function dump($str, $options = array())
    {
        $buffer = new Utf8Buffer($str);
        $dump = new Utf8Dump();
        $info = $buffer->analyze();
        $dump->setOptions($options);
        if ($info['percentBinary'] > 33) {
            $dump->setOptions('prefix', false);
            return $dump->dumpBlock($str, 'other');
        }
        $str = '';
        foreach ($info['blocks'] as $block) {
            $str .= $dump->dumpBlock($block[1], $block[0]);
        }
        return $str;
    }

    /**
     * Determine if string is UTF-8 encoded
     *
     * In addition, if valid UTF-8, will also report whether string contains
     * control, or other speical characters that could otherwise go unnoticed
     *
     * @param string $str        string to check
     * @param bool   $hasSpecial does valid utf-8 string contain control or "exotic" whitespace type character
     *
     * @return bool
     */
    public static function isUtf8($str, &$hasSpecial = false)
    {
        $buffer = new Utf8Buffer($str);
        return $buffer->isUtf8($hasSpecial);
    }

    /**
     * mb_strcut implementation
     *
     * @param string $str    The string being cut
     * @param int    $start  start position
     * @param int    $length length in bytes
     *
     * @return string
     * @see    https://www.php.net/manual/en/function.mb-strcut.php
     */
    public static function strcut($str, $start, $length = null)
    {
        self::$buffer = new \bdk\Debug\Utility\Utf8Buffer($str);
        $start = self::strcutGetStart($start);
        $length = self::strcutGetLength($start, $length);
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
        $encodings = array('ASCII', 'UTF-8');
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
        $end = $start + $length;
        $strlen = self::$buffer->strlen();
        if ($end >= $strlen) {
            return $strlen - $start;
        }
        $end++; // increment so that we start at original
        for ($i = 0; $i < 4; $i++) {
            $end--;
            self::$buffer->seek($end);
            if (self::$buffer->isOffsetUtf8()) {
                break;
            }
        }
        return $end - $start;
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

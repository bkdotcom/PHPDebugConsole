<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v2.1.0
 */

namespace bdk\Debug\Utility;

/**
 * Validate Utf8 / "highlight" non-utf8, control, & whitespace characters
 */
class Utf8
{
    private static $special = array(
        'regex:#[^\S \r\n\t]#u',        // with /u modifier, \s is equivalent to \p{Z}
        'regex:#[^\P{C}\r\n\t]#u',      // invisible control characters and unused code points. (includes zwsp & BOM)
        // "\xe2\x80\x8b",  // zero-width Space (included in \p{Cf})
        // "\xef\xbb\xbf",  // UTF-8 BOM        (included in \p{Cf})
        "\xef\xbf\xbd",  // "Replacement Character"
    );
    private static $charDesc = array(
        0x00 => 'NUL',
        0x01 => 'SOH',
        0x02 => 'STX',
        0x03 => 'ETX',
        0x04 => 'EOT',
        0x05 => 'ENQ',
        0x06 => 'ACK',
        0x07 => 'BEL',
        0x08 => 'BS',
        0x09 => 'HT',     // not treated special by default
        0x0A => 'LF',     // not treated special by default
        0x0B => 'VT',
        0x0C => 'FF',
        0x0D => 'CR',     // not treated special by default
        0x0E => 'SO',
        0x0F => 'SI',
        0x10 => 'DLE',
        0x11 => 'DC1',
        0x12 => 'DC2',
        0x13 => 'DC3',
        0x14 => 'DC4',
        0x15 => 'NAK',
        0x16 => 'SYN',
        0x17 => 'ETB',
        0x18 => 'CAN',
        0x19 => 'EM',
        0x1A => 'SUB',
        0x1B => 'ESC',
        0x1C => 'FS',
        0x1D => 'GS',
        0x1E => 'RS',
        0x1F => 'US',
        0x7F => 'DEL',
        0xA0 => 'NBSP',
        0x1680 => 'Ogham Space Mark',
        0x2000 => 'En Quad',
        0x2001 => 'Em Quad',
        0x2002 => 'En Space',
        0x2003 => 'Em Space',
        0x2004 => 'Three-Per-Em Space',
        0x2005 => 'Four-Per-Em Space',
        0x2006 => 'Six-Per-Em Space',
        0x2007 => 'Figure Space',
        0x2008 => 'Punctuation Space',
        0x2009 => 'Thin Space',
        0x200A => 'Hair Space',
        0x200B => 'Zero Width Space',   // not included in Separator Category
        0x2028 => 'Line Separator',
        0x2029 => 'Paragraph Separator',
        0x202F => 'Narrow No-Break Space',
        0x205F => 'Medium Mathematical Space',
        0x3000 => 'Ideographic Space',
        0xFEFF => 'BOM / Zero Width No-Break Space', // not included in Separator Category
        0xFFFD => 'Replacement Character',
    );

    private static $useHtml = false;
    private static $curI;
    private static $sanitizeNonBinary = true; // htmlspecialchars non-binary
    private static $stats = array();
    private static $str = '';

    /**
     * Add additional characters to be treated as special chars
     *
     * @param array|string $special character or array of characters ore regular-expressions
     *
     * @return void
     */
    public static function addSpecial($special)
    {
        $special = (array) $special;
        foreach ($special as $char) {
            self::$special[] = $char;
        }
    }

    /**
     * Check UTF-8 string (or single-character) against list of special characters or regular-expressions
     *
     * @param string $str String to check
     *
     * @return bool
     */
    public static function hasSpecial($str)
    {
        foreach (self::$special as $special) {
            if (\strpos($special, 'regex:') === 0) {
                if (\preg_match(\substr($special, 6), $str) > 0) {
                    return true;
                }
            } elseif (\strpos($str, $special) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Highlight non-UTF-8, control, & "special" characters
     *
     * control & non-utf-8 chars are displayed as hex
     * "special" unicode-characters are displayed with the \uxxxx representation
     *
     * @param string $str               string containing binary
     * @param bool   $useHtml           (false) add html markup
     * @param bool   $sanitizeNonBinary (false) apply htmlspecialchars to non-special chars?
     *
     * @return string
     */
    public static function dump($str, $useHtml = false, $sanitizeNonBinary = false)
    {
        self::$useHtml = $useHtml;
        self::$sanitizeNonBinary = $sanitizeNonBinary;
        self::setStr($str);
        $curBlockType = 'utf8'; // utf8, utf8special, other
        $curBlockStart = 0; // string offset
        $strNew = '';
        while (self::$curI < self::$stats['strLen']) {
            $curI = self::$curI;
            $charType = self::getCharType($str[$curI]);
            if ($charType !== $curBlockType) {
                $len = $curI - $curBlockStart;
                self::incStat($curBlockType, $len);
                $subStr = \substr(self::$str, $curBlockStart, $len);
                $strNew .= self::dumpBlock($subStr, $curBlockType);
                $curBlockStart = $curI;
                $curBlockType = $charType;
            }
        }
        $len = self::$stats['strLen'] - $curBlockStart;
        self::incStat($curBlockType, $len);
        if (self::$stats['strLen']) {
            $percentOther = self::$stats['bytesOther'] / self::$stats['strLen'] * 100;
            if ($percentOther > 33) {
                $strNew = self::dumpBlock($str, 'other', array('prefix' => false));
            } else {
                $subStr = \substr(self::$str, $curBlockStart, $len);
                $strNew .= self::dumpBlock($subStr, $curBlockType);
            }
        }
        return $strNew;
    }

    /**
     * Determine if string is UTF-8 encoded
     *
     * In addition, if valid UTF-8, will also report whether string contains
     * control, or other speical characters that could otherwise go unnoticed
     *
     * @param string $str     string to check
     * @param bool   $special does valid utf-8 string control or "exotic" whitespace type character
     *
     * @return bool
     */
    public static function isUtf8($str, &$special = false)
    {
        self::setStr($str);
        $special = false;
        while (self::$curI < self::$stats['strLen']) {
            $isUtf8 = self::isOffsetUtf8($isSpecial); // special is only checking control chars
            if (!$isUtf8) {
                return false;
            }
            if ($isSpecial) {
                $special = true;
            }
        }
        $special = $special || self::hasSpecial($str);
        return true;
    }

    /**
     * Returns decimal code-point for multi-byte character
     *
     * Use dechex to convert to hex (ie \uxxxx)
     *
     * @param string $str    A string or single character
     * @param int    $offset (0) Zero-based offset will be updated for offset of next char
     * @param string $char   will be populated with the character found at offset
     *
     * @return int
     */
    public static function ordUtf8($str, &$offset = 0, &$char = null)
    {
        $code = \ord($str[$offset]);
        $numBytes = 1;
        if ($code >= 0x80) {            // otherwise 0xxxxxxx
            if ($code < 0xe0) {         // 110xxxxx
                $numBytes = 2;
                $code -= 0xC0;
            } elseif ($code < 0xf0) {   // 1110xxxx
                $numBytes = 3;
                $code -= 0xE0;
            } elseif ($code < 0xf8) {
                $numBytes = 4;          // 11110xxx
                $code -= 0xF0;
            }
            for ($i = 1; $i < $numBytes; $i++) {
                $code2 = \ord($str[$offset + $i]) - 0x80;        // 10xxxxxx
                $code = $code * 64 + $code2;
            }
        }
        $char = \substr($str, $offset, $numBytes);
        $offset = $offset + $numBytes;
        return $code;
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
        if (\extension_loaded('mbstring') && \function_exists('iconv')) {
            $encoding = \mb_detect_encoding($str, \mb_detect_order(), true);
            if (!$encoding) {
                $str_conv = false;
                if (\function_exists('iconv')) {
                    $str_conv = \iconv('cp1252', 'UTF-8', $str);
                }
                if ($str_conv === false) {
                    $str_conv = \htmlentities($str, ENT_COMPAT);
                    $str_conv = \html_entity_decode($str_conv, ENT_COMPAT, 'UTF-8');
                }
                $str = $str_conv;
            } elseif (!\in_array($encoding, array('ASCII','UTF-8'))) {
                $str_new = \iconv($encoding, 'UTF-8', $str);
                if ($str_new !== false) {
                    $str = $str_new;
                }
            }
        }
        return $str;
    }

    /**
     * Format a block of text
     *
     * @param string $str       string to output
     * @param string $blockType "utf8", "utf8special", or "other"
     * @param array  $options   options
     *
     * @return string hidden/special chars converted to visible human-readable
     */
    private static function dumpBlock($str, $blockType, $options = array())
    {
        if ($str === '') {
            return '';
        }
        if ($blockType === 'utf8' && self::$sanitizeNonBinary) {
            $str = \htmlspecialchars($str);
        } elseif ($blockType === 'utf8special') {
            $str = self::dumpBlockSpecial($str);
        } elseif ($blockType === 'other') {
            $str = self::dumpBlockOther($str, $options);
        }
        return $str;
    }

    /**
     * Dump a "special" char  (ie hidden/whitespace)
     *
     * @param string $str string/char
     *
     * @return string
     */
    private static function dumpBlockSpecial($str)
    {
        $strNew = '';
        $i = 0;
        $length = \strlen($str);
        while ($i < $length) {
            $ord = self::ordUtf8($str, $i, $char);
            $ordHex = \dechex($ord);
            $ordHex = \str_pad($ordHex, 4, '0', STR_PAD_LEFT);
            if (self::$useHtml) {
                $chars = \str_split($char);
                $utf8Hex = \array_map('bin2hex', $chars);
                $utf8Hex = '\x' . \implode(' \x', $utf8Hex);
                $title = $utf8Hex;
                if (isset(self::$charDesc[$ord])) {
                    $title = self::$charDesc[$ord] . ': ' . $utf8Hex;
                }
                $url = 'https://unicode-table.com/en/' . $ordHex;
                $strNew = '<a class="unicode" href="' . $url . '" target="unicode-table" title="' . $title . '">\u' . $ordHex . '</a>';
            } else {
                $strNew .= '\u{' . $ordHex . '}';
            }
        }
        return $strNew;
    }

    /**
     * Dump "other" characters (ie control char)
     *
     * @param string $str     string/char
     * @param array  $options options
     *
     * @return string
     */
    private static function dumpBlockOther($str, $options = array())
    {
        $options = \array_merge(array(
            'prefix' => true,
        ), $options);
        if (!$options['prefix']) {
            $str = \bin2hex($str);
            $str = \trim(\chunk_split($str, 2, ' '));
        } else {
            $chars = \str_split($str);
            foreach ($chars as $i => $char) {
                $ord = \ord($char);
                $hex = \bin2hex($char); // could use dechex($ord), but would require padding
                if (self::$useHtml && isset(self::$charDesc[$ord])) {
                    if ($ord < 0x20 || $ord === 0x7f) {
                        // lets use the control pictures
                        $chr = $ord === 0x7f
                            ? "\xe2\x90\xa1"            // "del" char
                            : "\xe2\x90" . \chr($ord + 128); // chars for 0x00 - 0x1F
                        $chars[$i] = '<span class="c1-control" title="' . self::$charDesc[$ord] . ': \x' . $hex . '">' . $chr . '</span>';
                    } else {
                        $chars[$i] = '<span title="' . self::$charDesc[$ord] . '">\x' . $hex . '</span>';
                    }
                } else {
                    $chars[$i] = '\x' . $hex;
                }
            }
            $str = \implode(' ', $chars);
        }
        if (self::$useHtml) {
            $str = '<span class="binary">' . $str . '</span>';
        }
        return $str;
    }

    /**
     * Get byte sequence from current string
     *
     * @param int $len length to get (1-4)
     *
     * @return array
     */
    private static function getBytes($len)
    {
        $bytes = array();
        for ($i = 0; $i < $len; $i++) {
            $bytes[] = self::$curI + $i < self::$stats['strLen']
                ? \ord(self::$str[self::$curI + $i])
                : null;
        }
        return $bytes;
    }

    /**
     * get charater "category"
     *
     * @param string $char single byte
     *
     * @return string "utf8", "utf8special", or "other"
     */
    private static function getCharType($char)
    {
        $controlCharAs = 'other'; // how should we treat ascii control chars?
        $isUtf8 = self::isOffsetUtf8($isSpecial, true);
        if ($isUtf8 && $isSpecial && $controlCharAs !== 'utf8special' && \ord($char) < 0x80) {
            if ($controlCharAs === 'other') {
                $isUtf8 = false;
            } elseif ($controlCharAs === 'utf8') {
                $isSpecial = false;
            }
        }
        $charType = 'other';
        if ($isUtf8) {
            $charType = $isSpecial
                ? 'utf8special'
                : 'utf8';
        }
        return $charType;
    }

    /**
     * Increment statistic
     *
     * @param string $stat stat to increment
     * @param int    $inc  increment ammount
     *
     * @return void
     */
    private static function incStat($stat, $inc)
    {
        if ($stat === 'utf8special') {
            $stat = 'bytesSpecial';
        } else {
            $stat = 'bytes' . \ucfirst($stat);
        }
        self::$stats[$stat] += $inc;
    }

    /**
     * Is the byte or byte-sequence beginning at the current offset a valid utf-8 character?
     *
     * Increments the current offset
     *
     * @param bool $special      populated with whether offset is a control or "special" character
     * @param bool $checkSpecial test for user-defined special chars?
     *
     * @return bool
     */
    private static function isOffsetUtf8(&$special = false, $checkSpecial = false)
    {
        $iStart = self::$curI;
        $byte = \ord(self::$str[self::$curI]);
        $inc = 1;
        $isUtf8 = false;
        $isSpecial = false;
        if ($byte < 0x80) {
            // single byte 0bbbbbbb
            $inc = 1; // advance to next byte
            $isUtf8 = true;
            $isSpecial = self::test1byteSeq($byte);
        } elseif (($byte & 0xe0) === 0xc0) {
            // 2-byte sequence 110bbbbb 10bbbbbb
            $inc = 2;   // skip the next byte
            $isUtf8 = self::test2byteSeq();
        } elseif (($byte & 0xf0) === 0xe0) {
            // 3-byte sequence 1110bbbb 10bbbbbb 10bbbbbb
            $inc = 3;   // skip the next 2 bytes
            $isUtf8 = self::test3byteSeq();
        } elseif (($byte & 0xf8) === 0xf0) {
            // 4-byte sequence: 11110bbb 10bbbbbb 10bbbbbb 10bbbbbb
            $inc = 4;   // skip the next 3 bytes
            $isUtf8 = self::test4byteSeq();
        }
        if ($isUtf8) {
            self::$curI += $inc;
        } else {
            self::$curI++;
            return false;
        }
        if ($checkSpecial) {
            $subStr = \substr(self::$str, $iStart, self::$curI - $iStart);
            $special = $isSpecial || self::hasSpecial($subStr);
        }
        return true;
    }

    /**
     * Reset string statistics
     *
     * @param string $str string being inspected/output
     *
     * @return void
     */
    private static function setStr($str)
    {
        self::$str = $str;
        self::$curI = 0;
        self::$stats = array(
            'bytesOther' => 0,
            'bytesSpecial' => 0,        // special UTF-8
            'bytesUtf8' => 0,           // includes ASCII
            'strLen' => \strlen($str),
        );
    }

    /**
     * Test if single byte "sequence" is a "special" char
     *
     * @param int $byte $ordinal ordinal value of char
     *
     * @return bool
     */
    private static function test1byteSeq($byte)
    {
        return $byte < 0x20 && !\in_array($byte, array(0x09,0x0a,0x0d)) || $byte === 0x7f;
    }

    /**
     * Test if current 2-byte sequence is valid UTF8 char
     *
     * @return bool
     */
    private static function test2byteSeq()
    {
        $bytes = self::getBytes(2);
        return (self::$curI + 1 >= self::$stats['strLen']
            || ($bytes[1] & 0xc0) !== 0x80
            || ($bytes[0] & 0xfe) === 0xc0  // overlong
        ) === false;
    }

    /**
     * Test if current 3-byte sequence is valid UTF8 char
     *
     * @return bool
     */
    private static function test3byteSeq()
    {
        $bytes = self::getBytes(3);
        return (self::$curI + 2 >= self::$stats['strLen']
            || ($bytes[1] & 0xc0) !== 0x80
            || ($bytes[2] & 0xc0) !== 0x80
            || $bytes[0] === 0xe0
                && ($bytes[1] & 0xe0) === 0x80  // overlong
            || $bytes[0] === 0xed
                && ($bytes[1] & 0xe0) === 0xa0  // UTF-16 surrogate (U+D800 - U+DFFF)
        ) === false;
    }

    /**
     * Test if current 4-byte sequence is valid UTF8 char
     *
     * @return bool
     */
    private static function test4byteSeq()
    {
        $bytes = self::getBytes(4);
        return (self::$curI + 3 >= self::$stats['strLen']
            || ($bytes[1] & 0xc0) !== 0x80
            || ($bytes[2] & 0xc0) !== 0x80
            || ($bytes[3] & 0xc0) !== 0x80
            || $bytes[0] === 0xf0
                && ($bytes[1] & 0xf0) === 0x80  // overlong
            || $bytes[0] === 0xf4
                && $bytes[1] > 0x8f
            || $bytes[0] > 0xf4    // > U+10FFFF
        ) === false;
    }
}

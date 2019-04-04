<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.1.0
 */

namespace bdk\Debug;

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
     * @return boolean
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
    }

    /**
     * Highlight non-UTF-8, control, & "special" characters
     *
     * control & non-utf-8 chars are displayed as hex
     * "special" unicode-characters are displayed with the \uxxxx representation
     *
     * @param string  $str               string containing binary
     * @param boolean $useHtml           (false) add html markup
     * @param boolean $sanitizeNonBinary (false) apply htmlspecialchars to non-special chars?
     *
     * @return string
     */
    public static function dump($str, $useHtml = false, $sanitizeNonBinary = false)
    {
        self::$useHtml = $useHtml;
        self::$sanitizeNonBinary = $sanitizeNonBinary;
        self::setStr($str);
        $controlCharAs = 'other'; // how should we treat ascii control chars?
        $curBlockType = 'utf8'; // utf8, utf8special, other
        $newBlockType = null;
        $curBlockStart = 0; // string offset
        $strNew = '';
        while (self::$curI < self::$stats['strLen']) {
            $curI = self::$curI;
            $isUtf8 = self::isOffsetUtf8($isSpecial, true);
            if ($isUtf8 && $isSpecial && $controlCharAs !== 'utf8special' && \ord($str[$curI]) < 0x80) {
                if ($controlCharAs == 'other') {
                    $isUtf8 = false;
                } elseif ($controlCharAs == 'utf8') {
                    $isSpecial = false;
                }
            }
            if ($isUtf8) {
                if ($isSpecial) {
                    // control-char or special
                    if ($curBlockType !== 'utf8special') {
                        $newBlockType = 'utf8special';
                    }
                } else {
                    // plain-ol-utf8
                    if ($curBlockType !== 'utf8') {
                        $newBlockType = 'utf8';
                    }
                }
            } else {
                // not a valid utf-8 character
                if ($curBlockType !== 'other') {
                    $newBlockType = 'other';
                }
            }
            if ($newBlockType) {
                $len = $curI - $curBlockStart;
                self::incStat($curBlockType, $len);
                $subStr = \substr(self::$str, $curBlockStart, $len);
                $strNew .= self::dumpBlock($subStr, $curBlockType);
                $curBlockStart = $curI;
                $curBlockType = $newBlockType;
                $newBlockType = null;
            }
        }
        $len = self::$stats['strLen'] - $curBlockStart;
        self::incStat($curBlockType, $len);
        if (self::$stats['strLen']) {
            $percentOther = (self::$stats['bytesOther']) / self::$stats['strLen'] * 100;
            if ($percentOther > 33) {
                $strNew = self::dumpBlock($str, 'other', array('prefix'=>false));
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
     * @param string  $str     string to check
     * @param boolean $special does valid utf-8 string control or "exotic" whitespace type character
     *
     * @return boolean
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
     * @param string  $str    A string or single character
     * @param integer $offset (0) Zero-based offset will be updated for offset of next char
     * @param string  $char   will be populated with the character found at offset
     *
     * @return integer
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
     * @return [type] [description]
     */
    private static function dumpBlock($str, $blockType, $options = array())
    {
        if ($str === '') {
            return '';
        }
        $options = \array_merge(array(
            'prefix' => true,
        ), $options);
        if ($blockType == 'utf8' && self::$sanitizeNonBinary) {
            $str = \htmlspecialchars($str);
        } elseif ($blockType == 'utf8special') {
            $strNew = '';
            $i = 0;
            $length = \strlen($str);
            while ($i < $length) {
                $ord = self::ordUtf8($str, $i, $char);
                $ordHex = \dechex($ord);
                $ordHex = \str_pad($ordHex, 4, "0", STR_PAD_LEFT);
                if (self::$useHtml) {
                    $chars = \str_split($char);
                    $utf8Hex = \array_map('bin2hex', $chars);
                    $utf8Hex = '\x'.\implode(' \x', $utf8Hex);
                    $title = $utf8Hex;
                    if (isset(self::$charDesc[$ord])) {
                        $title = self::$charDesc[$ord].': '.$utf8Hex;
                    }
                    $strNew = '<a class="unicode" href="https://unicode-table.com/en/'.$ordHex.'" target="unicode-table" title="'.$title.'">\u'.$ordHex.'</a>';
                } else {
                    $strNew .= '\u{'.$ordHex.'}';
                }
            }
            $str = $strNew;
        } elseif ($blockType == 'other') {
            if (!$options['prefix']) {
                $str = \bin2hex($str);
                $str = \trim(\chunk_split($str, 2, ' '));
            } else {
                $chars = \str_split($str);
                foreach ($chars as $i => $char) {
                    $ord = \ord($char);
                    $hex = \bin2hex($char); // could use dechex($ord), but would require padding
                    if (self::$useHtml && isset(self::$charDesc[$ord])) {
                        if ($ord < 0x20 || $ord == 0x7f) {
                            // lets use the control pictures
                            $chr = $ord == 0x7f
                                ? "\xe2\x90\xa1"            // "del" char
                                : "\xe2\x90".\chr($ord+128); // chars for 0x00 - 0x1F
                            $chars[$i] = '<span class="c1-control" title="'.self::$charDesc[$ord].': \x'.$hex.'">'.$chr.'</span>';
                        } else {
                            $chars[$i] = '<span title="'.self::$charDesc[$ord].'">\x'.$hex.'</span>';
                        }
                    } else {
                        $chars[$i] = '\x'.$hex;
                    }
                }
                $str = \implode(' ', $chars);
            }
            if (self::$useHtml) {
                $str = '<span class="binary">'.$str.'</span>';
            }
        }
        return $str;
    }

    /**
     * Increment statistic
     *
     * @param string  $stat stat to increment
     * @param integer $inc  increment ammount
     *
     * @return void
     */
    private static function incStat($stat, $inc)
    {
        if ($stat == 'utf8special') {
            $stat = 'bytesSpecial';
        } else {
            $stat = 'bytes'.\ucfirst($stat);
        }
        self::$stats[$stat] += $inc;
    }

    /**
     * Is the byte or byte-sequence beginning at the current offset a valid utf-8 character?
     *
     * Increments the current offset
     *
     * @param boolean $special      populated with whether offset is a control or "special" character
     * @param boolean $checkSpecial test for user-defined special chars?
     *
     * @return boolean [description]
     */
    private static function isOffsetUtf8(&$special = false, $checkSpecial = false)
    {
        $i = self::$curI;
        $special = false;
        $byte1 = \ord(self::$str[$i]);
        $byte2 = $i + 1 < self::$stats['strLen'] ? \ord(self::$str[$i+1]) : null;
        $byte3 = $i + 2 < self::$stats['strLen'] ? \ord(self::$str[$i+2]) : null;
        $byte4 = $i + 3 < self::$stats['strLen'] ? \ord(self::$str[$i+3]) : null;
        if ($byte1 < 0x80) {                 # 0bbbbbbb
            if (($byte1 < 0x20 || $byte1 == 0x7f) && !\in_array($byte1, array(0x09,0x0a,0x0d))) {
                $special = true;
            }
            self::$curI += 1;    // advance to next byte
        } elseif (($byte1 & 0xe0) == 0xc0) { # 110bbbbb 10bbbbbb
            if ($i + 1 >= self::$stats['strLen'] ||
                ($byte2 & 0xc0) !== 0x80 ||
                ($byte1 & 0xfe) === 0xc0  // overlong
            ) {
                self::$curI += 1;
                return false;
            }
            self::$curI += 2;    // skip the next byte
        } elseif (($byte1 & 0xf0) == 0xe0) { # 3-byte sequence 1110bbbb 10bbbbbb 10bbbbbb
            if ($i + 2 >= self::$stats['strLen'] ||
                ($byte2 & 0xc0) !== 0x80 ||
                ($byte3 & 0xc0) !== 0x80 ||
                $byte1 === 0xe0 && ($byte2 & 0xe0) === 0x80 ||  // overlong
                $byte1 === 0xed && ($byte2 & 0xe0) === 0xa0     // UTF-16 surrogate (U+D800 - U+DFFF)
            ) {
                self::$curI += 1;
                return false;
            }
            self::$curI += 3;    // skip the next 2 bytes
        } elseif (($byte1 & 0xf8) == 0xf0) { # 4-byte sequence: 11110bbb 10bbbbbb 10bbbbbb 10bbbbbb
            if ($i + 3 >= self::$stats['strLen'] ||
                ($byte2 & 0xc0) !== 0x80 ||
                ($byte3 & 0xc0) !== 0x80 ||
                ($byte4 & 0xc0) !== 0x80 ||
                $byte1 === 0xf0 && ($byte2 & 0xf0) === 0x80 ||      // overlong
                $byte1 === 0xf4 && $byte2 > 0x8f || $byte1 > 0xf4   // > U+10FFFF
            ) {
                self::$curI += 1;
                return false;
            }
            self::$curI += 4;    // skip the next 3 bytes
        } else {                            # Does not match any model
            self::$curI += 1;
            return false;
        }
        if ($checkSpecial) {
            $subStr = \substr(self::$str, $i, self::$curI-$i);
            $special = $special || self::hasSpecial($subStr);
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
}

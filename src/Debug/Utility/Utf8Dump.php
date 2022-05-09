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

use bdk\Debug\Utility\Utf8;

/**
 * Dump strings / "highlight" non-printing & whitespace characters
 */
class Utf8Dump
{
    private $charDesc = array(
        0x00 => 'NUL',
        0x01 => 'SOH (start of heading)',
        0x02 => 'STX (start of text)',
        0x03 => 'ETX (end of text)',
        0x04 => 'EOT (end of transmission)',
        0x05 => 'ENQ (enquiry)',
        0x06 => 'ACK (acknowledge)',
        0x07 => 'BEL (bell)',
        0x08 => 'BS (backspace)',
        0x09 => 'HT (horizontal tab)',           // \t not treated special by default
        0x0A => 'LF (NL line feed / new line)',  // \n not treated special by default
        0x0B => 'VT (vertical tab)',
        0x0C => 'FF (NP form feed / new page)',
        0x0D => 'CR (carriage return)',          // \r not treated special by default
        0x0E => 'SO (shift out)',
        0x0F => 'SI (shift in)',
        0x10 => 'DLE (data link escape)',
        0x11 => 'DC1 (device control 1)',
        0x12 => 'DC2 (device control 2)',
        0x13 => 'DC3 (device control 3)',
        0x14 => 'DC4 (device control 4)',
        0x15 => 'NAK (negative acknowledge)',
        0x16 => 'SYN (synchronous idle)',
        0x17 => 'ETB (end of trans. block)',
        0x18 => 'CAN (cancel)',
        0x19 => 'EM (end of medium)',
        0x1A => 'SUB (substitute)',
        0x1B => 'ESC (escape)',
        0x1C => 'FS (file seperator)',
        0x1D => 'GS (group seperator)',
        0x1E => 'RS (record seperator)',
        0x1F => 'US (unit seperator)',
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
        0x200B => 'Zero Width Space', // not included in Separator Category
        0x2028 => 'Line Separator',
        0x2029 => 'Paragraph Separator',
        0x202F => 'Narrow No-Break Space',
        0x205F => 'Medium Mathematical Space',
        0x3000 => 'Ideographic Space',
        0xFEFF => 'BOM / Zero Width No-Break Space', // not included in Separator Category
        0xFFFD => 'Replacement Character',
    );

    private $options = array(
        'prefix' => true,
        'sanitizeNonBinary' => false,
        'useHtml' => false,
    );

    /**
     * Format a block of text
     *
     * @param string $str       string to output
     * @param string $blockType "utf8", "utf8control", "utf8special", or "other"
     *
     * @return string hidden/special chars converted to visible human-readable
     */
    public function dumpBlock($str, $blockType)
    {
        if ($str === '') {
            return '';
        }
        // echo $blockType . ' "' . \bin2hex($str) . '"' . "\n";
        switch ($blockType) {
            case 'utf8special':
                return $this->dumpBlockSpecial($str);
            case 'utf8control':
            case 'other':
                $str = $this->dumpBlockCtrlOther($str);
                return $this->options['useHtml']
                    ? '<span class="binary">' . $str . '</span>'
                    : $str;
        }
        // default / 'utf8'
        return $this->options['sanitizeNonBinary']
            ? \htmlspecialchars($str)
            : $str;
    }

    /**
     * Set one or more options
     *
     *    setOptions('key', 'value')
     *    setOptions(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param array|string $mixed key=>value array or key
     * @param mixed        $val   new value
     *
     * @return void
     */
    public function setOptions($mixed, $val = null)
    {
        if (\is_string($mixed)) {
            $mixed = array($mixed => $val);
        }
        $this->options = \array_merge($this->options, $mixed);
    }

    /**
     * Dump "other" characters (ie control char)
     *
     * @param string $str string/char
     *
     * @return string
     */
    private function dumpBlockCtrlOther($str)
    {
        if ($this->options['prefix'] === false) {
            $str = \bin2hex($str);
            return \trim(\chunk_split($str, 2, ' '));
        }
        if ($this->options['useHtml'] === false) {
            $prefix = '\\x';
            $str = \bin2hex($str);
            $str = \trim(\chunk_split($str, 2, ' '));
            return $prefix . \str_replace(' ', $prefix, $str);
        }
        $chars = \str_split($str);
        foreach ($chars as $i => $char) {
            $chars[$i] = $this->dumpCtrlOtherCharHtml($char);
        }
        return \implode('', $chars);
    }

    /**
     * Dump a "special" char  (ie hidden/whitespace)
     *
     * @param string $str string/char
     *
     * @return string
     */
    private function dumpBlockSpecial($str)
    {
        $strNew = '';
        $pos = 0; // ordUtf8 updates
        $length = Utf8::strlen($str);
        while ($pos < $length) {
            $char = '';
            $ord = $this->ordUtf8($str, $pos, $char);
            $ordHex = \dechex($ord);
            $ordHex = \str_pad($ordHex, 4, '0', STR_PAD_LEFT);
            if ($this->options['useHtml'] === false) {
                $strNew .= '\u{' . $ordHex . '}';
                continue;
            }
            $chars = \str_split($char);
            $utf8Hex = \array_map('bin2hex', $chars);
            $utf8Hex = '\x' . \implode(' \x', $utf8Hex);
            $title = $utf8Hex;
            if (isset($this->charDesc[$ord])) {
                $title = $this->charDesc[$ord] . ': ' . $utf8Hex;
            }
            $url = 'https://unicode-table.com/en/' . $ordHex;
            $strNew .= '<a class="unicode" href="' . $url . '" target="unicode-table" title="' . $title . '">\u' . $ordHex . '</a>';
        }
        return $strNew;
    }

    /**
     * Dump control and "other" character
     *
     * @param string $char single (may be multi-byte) char
     *
     * @return string
     */
    private function dumpCtrlOtherCharHtml($char)
    {
        $ord = \ord($char);
        $prefix = '\\x';
        $hex = $prefix . \bin2hex($char); // could use dechex($ord), but would require padding
        if (!isset($this->charDesc[$ord])) {
            // other
            return $hex;
        }
        // lets use the control pictures
        $chr = $ord === 0x7f
            ? "\xe2\x90\xa1"            // "del" char
            : "\xe2\x90" . \chr($ord + 128); // chars for 0x00 - 0x1F
        return '<span class="c1-control" title="' . $this->charDesc[$ord] . ': ' . $hex . '">' . $chr . '</span>';
    }

    /**
     * Returns decimal code-point for multi-byte character
     *
     * Use dechex to convert to hex (ie \uxxxx)
     *
     * @param string $str    A string or single character
     * @param int    $offset (0) Zero-based offset will be updated to offset of next char
     * @param string $char   will be populated with the character found at offset
     *
     * @return int
     */
    private function ordUtf8($str, &$offset = 0, &$char = null)
    {
        $code = \ord($str[$offset]);
        $numBytes = 1;
        if ($code < 0x80) {
            $numBytes = 1;
        } elseif ($code < 0xe0) {   // 110xxxxx
            $code -= 0xc0;
            $numBytes = 2;
        } elseif ($code < 0xf0) {   // 1110xxxx
            $code -= 0xe0;
            $numBytes = 3;
        } elseif ($code < 0xf8) {
            $code -= 0xf0;
            $numBytes = 4;          // 11110xxx
        }
        for ($i = 1; $i < $numBytes; $i++) {
            $code2 = \ord($str[$offset + $i]) - 0x80; // 10xxxxxx
            $code = $code * 64 + $code2;
        }
        $char = \substr($str, $offset, $numBytes);
        $offset = $offset + $numBytes;
        return $code;
    }
}

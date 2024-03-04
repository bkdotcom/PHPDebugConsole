<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\Utf8;
use LengthException;

/**
 * String "stream" used for analyzing/testing
 *
 * @psalm-type stats = array{
 *   blocks: list<array{0:Utf8::TYPE_*, 1:string}>,
 *   bytesControl: int,
 *   bytesOther: int,
 *   bytesSpecial: int,
 *   bytesUtf8: int,
 *   calculated: bool,
 *   mbStrlen: int,
 *   percentBinary: float,
 *   strlen: int,
 * }
 */
class Utf8Buffer
{
    /** @var int */
    private $curI;

    /** @var stats */
    private $stats = array(
        'blocks' => array(),
        'bytesControl' => 0,
        'bytesOther' => 0,          // aka binary
        'bytesSpecial' => 0,        // special UTF-8 (aka "exotic" whitespace type chars)
        'bytesUtf8' => 0,           // includes ASCII (does not incl Control or Special)
        'calculated' => false,      // internal check if stats calculated
        'mbStrlen' => 0,
        'percentBinary' => 0,
        'strlen' => 0,
    );

    /** @var array<'chars'|'regex', list<non-empty-string>> */
    private static $special = array(
        'chars' => array(
            "\xef\xbf\xbd",  // "Replacement Character"
            // "\xe2\x80\x8b",  // zero-width Space (included in \p{Cf})
            // "\xef\xbb\xbf",  // UTF-8 BOM        (included in \p{Cf})
        ),
        'regex' => array(
            '#[^\S \r\n\t]#u',        // with /u modifier, \s is equivalent to \p{Z}
            '#[^\P{C}\r\n\t]#u',      // invisible control characters and unused code points. (includes zwsp & BOM)
        ),
    );

    /** @var string */
    private $str = '';

    /**
     * Constructor
     *
     * @param string $string The string to analyze
     */
    public function __construct($string)
    {
        $this->curI = 0;
        $this->stats['strlen'] = Utf8::strlen($string);
        $this->str = $string;
    }

    /**
     * Add additional characters to be treated as special chars
     *
     * @param non-empty-string[]|non-empty-string $special character or array of characters or regular-expressions
     *
     * @return void
     */
    public static function addSpecial($special)
    {
        $special = (array) $special;
        foreach ($special as $char) {
            if (\strpos($char, 'regex:') !== 0) {
                /** @psalm-suppress PropertyTypeCoercion */
                self::$special['chars'][] = $char;
                continue;
            }
            // remove "regex:" prefix
            $regex = \substr($char, 6);
            if (\strlen($regex)) {
                /** @psalm-suppress PropertyTypeCoercion */
                self::$special['regex'][] = $regex;
            }
        }
    }

    /**
     * Get stats about string
     *
     * @return stats
     *
     * @psalm-suppress InvalidReturnType
     */
    public function analyze()
    {
        if ($this->stats['calculated']) {
            /** @psalm-suppress InvalidReturnStatement */
            return \array_diff_key($this->stats, array('calculated' => false));
        }
        $this->seek(0);
        $curBlockType = Utf8::TYPE_UTF8;
        $curBlockStart = 0;     // string offset
        while ($this->curI < $this->stats['strlen']) {
            $this->stats['mbStrlen']++;
            $curI = $this->curI;
            $charType = $this->getOffsetCharType();
            if ($charType !== $curBlockType) {
                $curBlockLen = $curI - $curBlockStart;
                $this->addBlock($curBlockType, $curBlockStart, $curBlockLen);
                $curBlockStart = $curI;
                $curBlockType = $charType;
            }
        }
        $curBlockLen = $this->stats['strlen'] - $curBlockStart;
        $this->addBlock($curBlockType, $curBlockStart, $curBlockLen);
        $this->analyzeFinish();
        $this->stats['calculated'] = true;
        /** @psalm-suppress InvalidReturnStatement */
        return \array_diff_key($this->stats, array('calculated' => false));
    }

    /**
     * Is the byte or byte-sequence beginning at the current offset a valid utf-8 character?
     *
     * Increments the current offset
     *
     * @param string $char populated with the char that was tested
     *
     * @return bool
     */
    public function isOffsetUtf8(&$char = '')
    {
        $byte1 = \ord($this->str[$this->curI]);
        $inc = 1;
        $isUtf8 = false;
        if ($byte1 < 0x80) {
            // single byte 0bbbbbbb
            $isUtf8 = true;
        } elseif (($byte1 & 0xe0) === 0xc0) {
            // 2-byte sequence 110bbbbb 10bbbbbb
            $inc = 2;   // skip the next byte
            $isUtf8 = $this->test2byteSeq();
        } elseif (($byte1 & 0xf0) === 0xe0) {
            // 3-byte sequence 1110bbbb 10bbbbbb 10bbbbbb
            $inc = 3;   // skip the next 2 bytes
            $isUtf8 = $this->test3byteSeq();
        } elseif (($byte1 & 0xf8) === 0xf0) {
            // 4-byte sequence: 11110bbb 10bbbbbb 10bbbbbb 10bbbbbb
            $inc = 4;   // skip the next 3 bytes
            $isUtf8 = $this->test4byteSeq();
        }
        $char = \substr($this->str, $this->curI, $inc);
        $this->curI += $inc;
        return $isUtf8;
    }

    /**
     * Determine if string is UTF-8 encoded
     *
     * In addition, if valid UTF-8, will also report whether string contains
     * control, or other special characters that could otherwise go unnoticed
     *
     * @param bool $hasSpecial does valid utf-8 string contain control or "exotic" whitespace type character
     *
     * @return bool
     */
    public function isUtf8(&$hasSpecial = false)
    {
        while ($this->curI < $this->stats['strlen']) {
            $isUtf8 = $this->isOffsetUtf8();
            if (!$isUtf8) {
                return false;
            }
        }
        $hasSpecial = $this->hasSpecial($this->str);
        return true;
    }

    /**
     * Read data from the stream and update internal pointer.
     *
     * @param int $length Read up to $length bytes
     *
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     *
     * @throws LengthException
     */
    public function read($length)
    {
        if ($length < 0) {
            throw new LengthException('Utf8Buffer::read - length must be >= 0');
        }
        $start = $this->curI;
        $this->curI = \min($this->curI + $length, $this->stats['strlen']);
        return \substr($this->str, $start, $length);
    }

    /**
     * Seek to a position in the stream.
     *
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.
     *     SEEK_SET: Set position equal to offset bytes
     *     SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     *
     * @return void
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        $whence = (int) $whence;
        switch ($whence) {
            case SEEK_SET:
                $this->curI = $offset;
                return;
            case SEEK_CUR:
                $this->curI = $this->curI + $offset;
                return;
            case SEEK_END:
                $this->curI = $this->stats['strlen'] + $offset;
                return;
        }
    }

    /**
     * Get string's length in bytes
     *
     * @return int
     */
    public function strlen()
    {
        return $this->stats['strlen'];
    }

    /**
     * Add block of text and increment stat
     *
     * @param Utf8::TYPE_* $type  block type
     * @param int          $start start position
     * @param int          $len   length
     *
     * @return void
     */
    private function addBlock($type, $start, $len)
    {
        $this->incStat($type, $len);
        $subStr = \substr($this->str, $start, $len);
        $this->stats['blocks'][] = array($type, $subStr);
    }

    /**
     * Calculate percentBinary and remove first block if empty
     *
     * @return void
     */
    private function analyzeFinish()
    {
        $this->stats['percentBinary'] = $this->stats['strlen']
            ? ($this->stats['bytesControl'] + $this->stats['bytesOther']) / $this->stats['strlen'] * 100
            : 0;
        if ($this->stats['strlen'] && Utf8::strlen($this->stats['blocks'][0][1]) === 0) {
            // first block was empty
            \array_shift($this->stats['blocks']);
        }
    }

    /**
     * Get byte sequence from current string
     *
     * @param int $len length to get (1-4)
     *
     * @return list<int>
     */
    private function getBytes($len)
    {
        $bytes = array();
        $len = \min($len, $this->stats['strlen'] - $this->curI);
        for ($i = 0; $i < $len; $i++) {
            $bytes[] = \ord($this->str[$this->curI + $i]);
        }
        return $bytes;
    }

    /**
     * get charater "category"
     *
     * @return Utf8::TYPE_*
     */
    private function getOffsetCharType()
    {
        $char = '';
        $byte1 = $this->str[$this->curI]; // collect before calling isOffsetUtf8
        $isUtf8 = $this->isOffsetUtf8($char);
        if ($isUtf8 === false) {
            return Utf8::TYPE_OTHER;
        }
        $isSpecial = $this->hasSpecial($char);
        if ($isSpecial) {
            return \ord($byte1) < 0x80
                ? Utf8::TYPE_CONTROL
                : Utf8::TYPE_SPECIAL;
        }
        return Utf8::TYPE_UTF8;
    }

    /**
     * Check UTF-8 string (or single-character) against list of special characters or regular-expressions
     *
     * @param string $str String to check
     *
     * @return bool
     */
    private function hasSpecial($str)
    {
        foreach (self::$special['regex'] as $regex) {
            if (\preg_match($regex, $str) > 0) {
                return true;
            }
        }
        foreach (self::$special['chars'] as $char) {
            if (\strpos($str, $char) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Increment statistic
     *
     * @param Utf8::TYPE_* $stat stat to increment
     * @param int          $inc  increment ammount
     *
     * @return void
     */
    private function incStat($stat, $inc)
    {
        /** @var 'bytesControl'|'bytesOther'|'bytesSpecial'|'bytesUtf8' */
        $stat = 'bytes' . \ucfirst($stat);
        $this->stats[$stat] += $inc;
    }

    /**
     * Test if current 2-byte sequence is valid UTF8 char
     *
     * @return bool
     */
    private function test2byteSeq()
    {
        $bytes = $this->getBytes(2);
        if (\count($bytes) !== 2) {
            return false;
        }
        return (($bytes[1] & 0xc0) !== 0x80
            || ($bytes[0] & 0xfe) === 0xc0  // overlong
        ) === false;
    }

    /**
     * Test if current 3-byte sequence is valid UTF8 char
     *
     * @return bool
     */
    private function test3byteSeq()
    {
        $bytes = $this->getBytes(3);
        if (\count($bytes) !== 3) {
            return false;
        }
        return (($bytes[1] & 0xc0) !== 0x80
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
    private function test4byteSeq()
    {
        $bytes = $this->getBytes(4);
        if (\count($bytes) !== 4) {
            return false;
        }
        return (($bytes[1] & 0xc0) !== 0x80
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

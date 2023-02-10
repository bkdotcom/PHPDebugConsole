<?php

/**
 * Run with --process-isolation option
 */

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\Utf8;
use bdk\Debug\Utility\Utf8Buffer;
use bdk\Debug\Utility\Utf8Dump;

/**
 * PHPUnit tests for Debug class
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 *
 * @covers \bdk\Debug\Utility\Utf8
 * @covers \bdk\Debug\Utility\Utf8Buffer
 * @covers \bdk\Debug\Utility\Utf8Dump
 */
class Utf8Test extends \PHPUnit\Framework\TestCase
{
    public function testBufferAnalyze()
    {
        $str = "\xef\xbb\xbfPesky BOM";
        $buffer = new Utf8Buffer($str);
        $stats1 = $buffer->analyze();
        $stats2 = $buffer->analyze();
        self::assertSame(array(
            'blocks' => array(
                array(
                    'utf8special',
                    "\xef\xbb\xbf"
                ),
                array(
                    'utf8',
                    'Pesky BOM',
                ),
            ),
            'bytesControl' => 0,
            'bytesOther' => 0,
            'bytesSpecial' => 3,
            'bytesUtf8' => 9,
            'percentBinary' => 0,
            'mbStrlen' => 10,
            'strlen' => 12,
        ), $stats1);
        self::assertSame($stats1, $stats2);
    }

    /**
     * @param string $input        input string
     * @param string $outputExpect expected output
     * @param array  $opts         options
     *
     * @dataProvider providerDump
     *
     * @return void
     */
    public function testDump($input, $outputExpect, $opts = array())
    {
        self::assertSame($outputExpect, Utf8::dump($input, $opts));
    }

    /**
     * Test
     *
     * @param string $input  input string
     * @param bool   $isUtf8 whether string is utf8
     *
     * @return void
     *
     * @dataProvider providerIsUtf8
     *
     * @link http://www.cl.cam.ac.uk/~mgk25/ucs/examples/UTF-8-test.txt
     * @link http://web.mit.edu/barnowl/src/glib/glib-2.16.3/tests/utf8-validate.c
     */
    public function testIsUtf8($input, $isUtf8)
    {
        self::assertSame($isUtf8, Utf8::isUtf8($input));
    }

    /**
     * Test the private Utf8Dump::ordUtf9 method
     *
     * ordUtf is only called for "special" chars.. but works on all utf8 chars
     *
     * @return void
     */
    public function testOrdUtf8()
    {
        $utf8Dump = new Utf8Dump();
        $reflector = new \ReflectionMethod($utf8Dump, 'ordUtf8');
        $reflector->setAccessible(true);
        self::assertSame(97, $reflector->invoke($utf8Dump, 'a'));
        self::AssertSame(128169, $reflector->invoke($utf8Dump, '💩'));
    }

    /**
     * @param string $input        input string
     * @param int    $startPos     cut start pos
     * @param int    $endPos       cut end pos
     * @param string $outputExpect expected output
     *
     * @dataProvider providerStrCut
     *
     * @return void
     */
    public function testStrCut($input, $startPos, $endPos, $outputExpect)
    {
        self::assertSame($outputExpect, Utf8::strCut($input, $startPos, $endPos));
    }

    /**
     * Test
     *
     * @param string $input        input string
     * @param string $outputExpect expected output
     *
     * @dataProvider providerToUtf8
     *
     * @return void
     */
    public function testToUtf8($input, $outputExpect)
    {
        $output = Utf8::toUtf8($input);
        $output = \htmlentities($output, 0, 'UTF-8');
        self::assertSame($outputExpect, $output);
    }

    public static function providerDump()
    {
        $binary = \base64_decode('TzipAdbGNF+DfyAwZrp7ew==');
        return array(
            'empty' => array('', ''),

            'plain' => array("\tBrad was here\r\n",
                "\tBrad was here\r\n",
                array('useHtml' => true),
            ),

            'nonUtf8Char' => array("\xfe is not a valid utf8 char",
                '<span class="binary">\xfe</span> is not a valid utf8 char',
                array('useHtml' => true)
            ),

            'bom' => array("\xef\xbb\xbfPesky BOM",
                '\u{feff}Pesky BOM'),
            'bomHtml' => array("\xef\xbb\xbfPesky BOM",
                '<a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>Pesky BOM',
                array('useHtml' => true),
            ),

            'del' => array("control char \x7f",
                'control char \\x7f'),
            'delHtml' => array("control char \x7f",
                'control char <span class="binary"><span class="c1-control" title="DEL: \\x7f">␡</span></span>',
                array('useHtml' => true),
            ),

            'nul_plain' => array("null \x00 up in here",
                'null \\x00 up in here'
            ),
            'nul' => array("control char \x00",
                'control char <span class="binary"><span class="c1-control" title="NUL: \\x00">␀</span></span>',
                array('useHtml' => true),
            ),
            'soh' => array("control char \x01",
                'control char <span class="binary"><span class="c1-control" title="SOH (start of heading): \\x01">␁</span></span>',
                array('useHtml' => true),
            ),
            'stx' => array("control char \x02",
                'control char <span class="binary"><span class="c1-control" title="STX (start of text): \\x02">␂</span></span>',
                array('useHtml' => true),
            ),
            'etx' => array("control char \x03",
                'control char <span class="binary"><span class="c1-control" title="ETX (end of text): \\x03">␃</span></span>',
                array('useHtml' => true),
            ),
            'eot' => array("control char \x04",
                'control char <span class="binary"><span class="c1-control" title="EOT (end of transmission): \\x04">␄</span></span>',
                array('useHtml' => true),
            ),
            'enq' => array("control char \x05",
                'control char <span class="binary"><span class="c1-control" title="ENQ (enquiry): \\x05">␅</span></span>',
                array('useHtml' => true),
            ),
            'ack' => array("control char \x06",
                'control char <span class="binary"><span class="c1-control" title="ACK (acknowledge): \\x06">␆</span></span>',
                array('useHtml' => true),
            ),
            'bel' => array("control char \x07",
                'control char <span class="binary"><span class="c1-control" title="BEL (bell): \\x07">␇</span></span>',
                array('useHtml' => true),
            ),
            'bs' => array("control char \x08",
                'control char <span class="binary"><span class="c1-control" title="BS (backspace): \\x08">␈</span></span>',
                array('useHtml' => true),
            ),

            'ht' => array("dont treat special \x09",
                'dont treat special ' . "\t",
                array('useHtml' => true),
            ),
            'lf' => array("dont treat special \x0a",
                'dont treat special ' . "\n",
                array('useHtml' => true),
            ),
            'vt' => array("control char \x0b",
                'control char <span class="binary"><span class="c1-control" title="VT (vertical tab): \\x0b">␋</span></span>',
                array('useHtml' => true),
            ),
            'ff' => array("control char \x0c",
                'control char <span class="binary"><span class="c1-control" title="FF (NP form feed / new page): \\x0c">␌</span></span>',
                array('useHtml' => true),
            ),
            'cr' => array("dont treat special \x0d",
                'dont treat special ' . "\r",
                array('useHtml' => true),
            ),
            'so' => array("control char \x0e",
                'control char <span class="binary"><span class="c1-control" title="SO (shift out): \\x0e">␎</span></span>',
                array('useHtml' => true),
            ),
            'si' => array("control char \x0f",
                'control char <span class="binary"><span class="c1-control" title="SI (shift in): \\x0f">␏</span></span>',
                array('useHtml' => true),
            ),
            'dle' => array("control char \x10",
                'control char <span class="binary"><span class="c1-control" title="DLE (data link escape): \\x10">␐</span></span>',
                array('useHtml' => true),
            ),
            'dc1' => array("control char \x11",
                'control char <span class="binary"><span class="c1-control" title="DC1 (device control 1): \\x11">␑</span></span>',
                array('useHtml' => true),
            ),
            'dc2' => array("control char \x12",
                'control char <span class="binary"><span class="c1-control" title="DC2 (device control 2): \\x12">␒</span></span>',
                array('useHtml' => true),
            ),
            'dc3' => array("control char \x13",
                'control char <span class="binary"><span class="c1-control" title="DC3 (device control 3): \\x13">␓</span></span>',
                array('useHtml' => true),
            ),
            'dc4' => array("control char \x14",
                'control char <span class="binary"><span class="c1-control" title="DC4 (device control 4): \\x14">␔</span></span>',
                array('useHtml' => true),
            ),
            'nak' => array("control char \x15",
                'control char <span class="binary"><span class="c1-control" title="NAK (negative acknowledge): \\x15">␕</span></span>',
                array('useHtml' => true),
            ),
            'syn' => array("control char \x16",
                'control char <span class="binary"><span class="c1-control" title="SYN (synchronous idle): \\x16">␖</span></span>',
                array('useHtml' => true),
            ),
            'etb' => array("control char \x17",
                'control char <span class="binary"><span class="c1-control" title="ETB (end of trans. block): \\x17">␗</span></span>',
                array('useHtml' => true),
            ),
            'can' => array("control char \x18",
                'control char <span class="binary"><span class="c1-control" title="CAN (cancel): \\x18">␘</span></span>',
                array('useHtml' => true),
            ),
            'em' => array("control char \x19",
                'control char <span class="binary"><span class="c1-control" title="EM (end of medium): \\x19">␙</span></span>',
                array('useHtml' => true),
            ),
            'sub' => array("control char \x1a",
                'control char <span class="binary"><span class="c1-control" title="SUB (substitute): \\x1a">␚</span></span>',
                array('useHtml' => true),
            ),
            'esc' => array("control char \x1b",
                'control char <span class="binary"><span class="c1-control" title="ESC (escape): \\x1b">␛</span></span>',
                array('useHtml' => true),
            ),
            'fs' => array("control char \x1c",
                'control char <span class="binary"><span class="c1-control" title="FS (file seperator): \\x1c">␜</span></span>',
                array('useHtml' => true),
            ),
            'gs' => array("control char \x1d",
                'control char <span class="binary"><span class="c1-control" title="GS (group seperator): \\x1d">␝</span></span>',
                array('useHtml' => true),
            ),
            'rs' => array("control char \x1e",
                'control char <span class="binary"><span class="c1-control" title="RS (record seperator): \\x1e">␞</span></span>',
                array('useHtml' => true),
            ),
            'us' => array("control char \x1f",
                'control char <span class="binary"><span class="c1-control" title="US (unit seperator): \\x1f">␟</span></span>',
                array('useHtml' => true),
            ),

            array("\x00 \x01 \x02 \x03 \x04 \x05 \x06 \x07 \x08 \x09 \x0a \x0b \x0c \x0d \x0e \x0f \x10 \x11 \x12 \x13 \x14 \x15 \x16 \x17 \x18 \x19",
                '00 20 01 20 02 20 03 20 04 20 05 20 06 20 07 20 08 20 09 20 0a 20 0b 20 0c 20 0d 20 0e 20 0f 20 10 20 11 20 12 20 13 20 14 20 15 20 16 20 17 20 18 20 19',
            ),
            array("\x00 \x01 \x02 \x03 \x04 \x05 \x06 \x07 \x08 \x09 \x0a \x0b \x0c \x0d \x0e \x0f \x10 \x11 \x12 \x13 \x14 \x15 \x16 \x17 \x18 \x19",
                '<span class="binary">00 20 01 20 02 20 03 20 04 20 05 20 06 20 07 20 08 20 09 20 0a 20 0b 20 0c 20 0d 20 0e 20 0f 20 10 20 11 20 12 20 13 20 14 20 15 20 16 20 17 20 18 20 19</span>',
                array('useHtml' => true),
            ),

            // spot check a few non-printing chars
            array("easy-to-miss characters such as \xc2\xa0(nbsp), \xE2\x80\x89(thsp), &amp; \xE2\x80\x8B(zwsp)",
                'easy-to-miss characters such as \u{00a0}(nbsp), \u{2009}(thsp), &amp; \u{200b}(zwsp)',
            ),
            array("easy-to-miss characters such as \xc2\xa0(nbsp), \xE2\x80\x89(thsp), &amp; \xE2\x80\x8B(zwsp)",
                'easy-to-miss characters such as <a class="unicode" href="https://unicode-table.com/en/00a0" target="unicode-table" title="NBSP: \xc2 \xa0">\u00a0</a>(nbsp), <a class="unicode" href="https://unicode-table.com/en/2009" target="unicode-table" title="Thin Space: \xe2 \x80 \x89">\u2009</a>(thsp), &amp; <a class="unicode" href="https://unicode-table.com/en/200b" target="unicode-table" title="Zero Width Space: \xe2 \x80 \x8b">\u200b</a>(zwsp)',
                array('useHtml' => true),
            ),

            'replacement' => array("replacement char \xef\xbf\xbd",
                'replacement char <a class="unicode" href="https://unicode-table.com/en/fffd" target="unicode-table" title="Replacement Character: \xef \xbf \xbd">\ufffd</a>',
                array('useHtml' => true),
            ),

            array('poop 💩',
                'poop 💩',
            ),

            array($binary,
                \trim(\chunk_split(\bin2hex($binary), 2, ' ')),
            ),
            array($binary,
                '<span class="binary">' . \trim(\chunk_split(\bin2hex($binary), 2, ' ')) . '</span>',
                array('useHtml' => true),
            ),

            'sanitize' => array("<b>some\xc2\xa0html</b>",
                '&lt;b&gt;some<a class="unicode" href="https://unicode-table.com/en/00a0" target="unicode-table" title="NBSP: \xc2 \xa0">\u00a0</a>html&lt;/b&gt;',
                array(
                    'sanitizeNonBinary' => true,
                    'useHtml' => true,
                ),
            ),
        );
    }

    public static function providerIsUtf8()
    {
        return array(
            'plainAscii' => array('Plain Jane', true),
            'inc2byte' => array("Incomplete 2-byte sequence: \xc0", false),
            'inc3type' => array("Incomplete 3-byte sequence: \xef\xbf", false),
            'inc4byte' => array("Incomplete 4-byte sequence: \xf4\x8f", false),
            'tm' => array(\html_entity_decode('UTF8&trade;'), true),
            'undefReplacement' => array("\xc2\xa2 \xed\x9f\xbf \xee\x80\x80 \xef\xbf\xbd \xf4\x8f\xbf\xbf", true),
            array("\xf4\x90\x80\x80", false),
        );
    }

    public static function providerStrCut()
    {
        // 1-byte, 2-byte, 3-type, 4-byte
        $str = 'A©︙💩';
        return array(
            array($str, 0, 0, ''),
            array($str, 0, 1, 'A'),
            array($str, 0, 2, 'A'),
            array($str, 0, 3, 'A©'),
            array($str, 0, 4, 'A©'),
            array($str, 0, 5, 'A©'),
            array($str, 0, 6, 'A©︙'),
            array($str, 0, 7, 'A©︙'),
            array($str, 0, 8, 'A©︙'),
            array($str, 0, 9, 'A©︙'),
            array($str, 0, 10, $str),
            // start in middle of 2nd char
            array($str, 2, 2, '©'), // 1st char

            array($str, 2, 3, '©'), // 1st byte of 3rd char
            array($str, 2, 4, '©'), // 2nd byte of 3rd char
            array($str, 2, 5, '©︙'), // 3rd byte of 3rd char
            array($str, 2, 6, '©︙'), // 1st byte of last char
            array($str, 2, 7, '©︙'), // 2nd byte of last char
            array($str, 2, 8, '©︙'), // 3rd byte of last char
            array($str, 2, 9, '©︙💩'), // all 4 bytes of last char
            array($str, 2, 10, '©︙💩'),
        );
    }

    public static function providerToUtf8()
    {
        return array(
            'ascii' => array('plain',
                'plain'),
            'utf8' => array(\base64_decode('4oCcZmFuY3kgcXVvdGVz4oCd'),
                '&ldquo;fancy quotes&rdquo;'),
            'utf8_2' => array('bèfore [:: whoa ::] àfter',
                'b&egrave;fore [:: whoa ::] &agrave;fter'),
            'utf8_many' => array(\base64_decode('xZLFk8WgxaHFuMuGy5zigJrGkuKAnuKApuKAoOKAoeKAmOKAmeKAnOKAneKAouKAk+KAlOKEouKAsOKAueKAug=='),
                '&OElig;&oelig;&Scaron;&scaron;&Yuml;&circ;&tilde;&sbquo;&fnof;&bdquo;&hellip;&dagger;&Dagger;&lsquo;&rsquo;&ldquo;&rdquo;&bull;&ndash;&mdash;&trade;&permil;&lsaquo;&rsaquo;'),

            // 'utf-16' => array(
                // \mb_convert_encoding('Brad was here', 'UTF-8', 'UTF-16BE'),
                // '',
            // ),

            'ansi' => array(\base64_decode('k2ZhbmN5IHF1b3Rlc5Q='),
                '&ldquo;fancy quotes&rdquo;'),
            'Windows-1252' => array("42\x80", // euro
                '42&euro;',
            ),
            'ISO-8859-1' => array("\xE1\xE9\xF3\xFA", // 'áéóú'
                '&aacute;&eacute;&oacute;&uacute;'),

            'huffman' => array(\base64_decode('SHVmZm1hbi1HaWxyZWF0aCBKZW7p'),
                'Huffman-Gilreath Jen&eacute;'),
            'grr' => array(\base64_decode('SmVu6SBIdWZmbWFuLUdpbHJlYXRoIGFzc3VtZWQgaGVyIHJvbGUgYXMgdGhlIFByb2Zlc3Npb25hbCAmIEV4ZWN1dGl2ZSBPZmZpY2VyIGluIEF1Z3VzdCAyMDA3LiBKZW7pIGpvaW5lZCBBcnZlc3QgaW4gMjAwMiBhbmQgaGFzIGdhaW5lZCBhIHdlYWx0aCBvZiBrbm93bGVkZ2UgaW4gbWFueSBiYW5raW5nIGFyZWFzLCBzdWNoIGFzIGxlbmRpbmcsIHdlYWx0aCBtYW5hZ2VtZW50LCBhbmQgY29ycG9yYXRlIHNlcnZpY2VzLiBTaGUgaG9sZHMgYSBNYXN0ZXImcnNxdW87cyBkZWdyZWUgaW4gUHVibGljIEFkbWluaXN0cmF0aW9uIGFzIHdlbGwgYXMgYSBCU0JBIGluIFNtYWxsIEJ1c2luZXNzIEVudHJlcHJlbmV1cnNoaXAuIEplbukgaXMgdmVyeSBpbnZvbHZlZCBpbiB0aGUgY29tbXVuaXR5IGFuZCBzZXJ2ZXMgYXMgYSBtZW1iZXIgb2YgbnVtZXJvdXMgY2hhcml0YWJsZSBmb3VuZGF0aW9ucywgc3VjaCBhcyBEaWFtb25kcyBEZW5pbXMgYW5kIERpY2UgKFJlYnVpbGRpbmcgVG9nZXRoZXIpIGFuZCB0aGUgQWZyaWNhbiBFZHVjYXRpb24gUmVzb3VyY2UgQ2VudGVyLg=='),
                'Jen&eacute; Huffman-Gilreath assumed her role as the Professional &amp; Executive Officer in August 2007. Jen&eacute; joined Arvest in 2002 and has gained a wealth of knowledge in many banking areas, such as lending, wealth management, and corporate services. She holds a Master&amp;rsquo;s degree in Public Administration as well as a BSBA in Small Business Entrepreneurship. Jen&eacute; is very involved in the community and serves as a member of numerous charitable foundations, such as Diamonds Denims and Dice (Rebuilding Together) and the African Education Resource Center.'),
            'ansi_many' => array(\base64_decode('jJyKmp+ImIKDhIWGh5GSk5SVlpeZiYub'),
                '&OElig;&oelig;&Scaron;&scaron;&Yuml;&circ;&tilde;&sbquo;&fnof;&bdquo;&hellip;&dagger;&Dagger;&lsquo;&rsquo;&ldquo;&rdquo;&bull;&ndash;&mdash;&trade;&permil;&lsaquo;&rsaquo;'),
            'tm' => array('<b>' . "\x99" . '</b>',  // gets detected as ISO-8859-1 ?!
                '&lt;b&gt;&trade;&lt;/b&gt;'),
        );
    }
}

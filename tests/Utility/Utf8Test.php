<?php

/**
 * Run with --process-isolation option
 */

use bdk\Debug\Utility\Utf8;

/**
 * PHPUnit tests for Debug class
 */
class Utf8Test extends \PHPUnit\Framework\TestCase
{

    /**
     * Test
     *
     * @return void
     *
     * @link http://www.cl.cam.ac.uk/~mgk25/ucs/examples/UTF-8-test.txt
     * @link http://web.mit.edu/barnowl/src/glib/glib-2.16.3/tests/utf8-validate.c
     */
    public function testIsUtf8()
    {
        $this->assertTrue(Utf8::isUtf8('Plain Jane'));
        $this->assertTrue(Utf8::isUtf8(html_entity_decode('UTF8&trade;')));
        $this->assertTrue(Utf8::isUtf8("\xc2\xa2 \xed\x9f\xbf \xee\x80\x80 \xef\xbf\xbd \xf4\x8f\xbf\xbf"));
        $this->assertFalse(Utf8::isUtf8("\xf4\x90\x80\x80"));
    }

    public function testStrCut()
    {
        // 1-byte, 2-byte, 3-type, 4-byte
        $str = 'A©︙💩';

        $this->assertSame('', Utf8::strCut($str, 0, 0));
        $this->assertSame('A', Utf8::strCut($str, 0, 1));
        $this->assertSame('A', Utf8::strCut($str, 0, 2));
        $this->assertSame('A©', Utf8::strCut($str, 0, 3));
        $this->assertSame('A©', Utf8::strCut($str, 0, 4));
        $this->assertSame('A©', Utf8::strCut($str, 0, 5));
        $this->assertSame('A©︙', Utf8::strCut($str, 0, 6));
        $this->assertSame('A©︙', Utf8::strCut($str, 0, 7));
        $this->assertSame('A©︙', Utf8::strCut($str, 0, 8));
        $this->assertSame('A©︙', Utf8::strCut($str, 0, 9));
        $this->assertSame($str, Utf8::strCut($str, 0, 10));

        // start in middle of 2nd char
        $this->assertSame('©', Utf8::strCut($str, 2, 2)); // 1st char

        $this->assertSame('©', Utf8::strCut($str, 2, 3)); // 1st byte of 3rd char
        $this->assertSame('©', Utf8::strCut($str, 2, 4)); // 2nd byte of 3rd char
        $this->assertSame('©︙', Utf8::strCut($str, 2, 5)); // 3rd byte of 3rd char

        $this->assertSame('©︙', Utf8::strCut($str, 2, 6)); // 1st byte of last char
        $this->assertSame('©︙', Utf8::strCut($str, 2, 7)); // 2nd byte of last char
        $this->assertSame('©︙', Utf8::strCut($str, 2, 8)); // 3rd byte of last char
        $this->assertSame('©︙💩', Utf8::strCut($str, 2, 9)); // all 4 bytes of last char

        $this->assertSame('©︙💩', Utf8::strCut($str, 2, 10));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testToUtf8()
    {
        $strings = array(
            'plain'     => array('plain',
                'plain'),
            'huffman'   => array(base64_decode('SHVmZm1hbi1HaWxyZWF0aCBKZW7p'),
                'Huffman-Gilreath Jen&eacute;'),
            'grr'       => array(base64_decode('SmVu6SBIdWZmbWFuLUdpbHJlYXRoIGFzc3VtZWQgaGVyIHJvbGUgYXMgdGhlIFByb2Zlc3Npb25hbCAmIEV4ZWN1dGl2ZSBPZmZpY2VyIGluIEF1Z3VzdCAyMDA3LiBKZW7pIGpvaW5lZCBBcnZlc3QgaW4gMjAwMiBhbmQgaGFzIGdhaW5lZCBhIHdlYWx0aCBvZiBrbm93bGVkZ2UgaW4gbWFueSBiYW5raW5nIGFyZWFzLCBzdWNoIGFzIGxlbmRpbmcsIHdlYWx0aCBtYW5hZ2VtZW50LCBhbmQgY29ycG9yYXRlIHNlcnZpY2VzLiBTaGUgaG9sZHMgYSBNYXN0ZXImcnNxdW87cyBkZWdyZWUgaW4gUHVibGljIEFkbWluaXN0cmF0aW9uIGFzIHdlbGwgYXMgYSBCU0JBIGluIFNtYWxsIEJ1c2luZXNzIEVudHJlcHJlbmV1cnNoaXAuIEplbukgaXMgdmVyeSBpbnZvbHZlZCBpbiB0aGUgY29tbXVuaXR5IGFuZCBzZXJ2ZXMgYXMgYSBtZW1iZXIgb2YgbnVtZXJvdXMgY2hhcml0YWJsZSBmb3VuZGF0aW9ucywgc3VjaCBhcyBEaWFtb25kcyBEZW5pbXMgYW5kIERpY2UgKFJlYnVpbGRpbmcgVG9nZXRoZXIpIGFuZCB0aGUgQWZyaWNhbiBFZHVjYXRpb24gUmVzb3VyY2UgQ2VudGVyLg=='),
                'Jen&eacute; Huffman-Gilreath assumed her role as the Professional &amp; Executive Officer in August 2007. Jen&eacute; joined Arvest in 2002 and has gained a wealth of knowledge in many banking areas, such as lending, wealth management, and corporate services. She holds a Master&amp;rsquo;s degree in Public Administration as well as a BSBA in Small Business Entrepreneurship. Jen&eacute; is very involved in the community and serves as a member of numerous charitable foundations, such as Diamonds Denims and Dice (Rebuilding Together) and the African Education Resource Center.'),
            'utf8_many' => array(base64_decode('xZLFk8WgxaHFuMuGy5zigJrGkuKAnuKApuKAoOKAoeKAmOKAmeKAnOKAneKAouKAk+KAlOKEouKAsOKAueKAug=='),
                '&OElig;&oelig;&Scaron;&scaron;&Yuml;&circ;&tilde;&sbquo;&fnof;&bdquo;&hellip;&dagger;&Dagger;&lsquo;&rsquo;&ldquo;&rdquo;&bull;&ndash;&mdash;&trade;&permil;&lsaquo;&rsaquo;'),
            'ansi_many' => array(base64_decode('jJyKmp+ImIKDhIWGh5GSk5SVlpeZiYub'),
                '&OElig;&oelig;&Scaron;&scaron;&Yuml;&circ;&tilde;&sbquo;&fnof;&bdquo;&hellip;&dagger;&Dagger;&lsquo;&rsquo;&ldquo;&rdquo;&bull;&ndash;&mdash;&trade;&permil;&lsaquo;&rsaquo;'),
            'ansi'      => array(base64_decode('k2ZhbmN5IHF1b3Rlc5Q='),
                '&ldquo;fancy quotes&rdquo;'),
            'utf8'      => array(base64_decode('4oCcZmFuY3kgcXVvdGVz4oCd'),
                '&ldquo;fancy quotes&rdquo;'),
            'tm'        => array('<b>' . chr(153) . '</b>',
                '&lt;b&gt;&trade;&lt;/b&gt;'),
            'blah'      => array('bèfore [:: whoa ::] àfter',
                'b&egrave;fore [:: whoa ::] &agrave;fter'),
        );
        foreach ($strings as $k => $pair) {
            $expected = $pair[1];
            $string = Utf8::toUtf8($pair[0]);
            $string = htmlentities($string, null, 'UTF-8');
            $this->assertSame($expected, $string, $k . ' does not match');
        }
    }
}

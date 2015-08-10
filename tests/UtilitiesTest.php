<?php
/**
 * Run with --process-isolation option
 */

use \bdk\Debug\Utilities;

/**
 * PHPUnit tests for Debug class
 */
class UtilitiesTest extends PHPUnit_Framework_TestCase
{

    public function output($label, $var)
    {
        fwrite(STDOUT, $label.' = '.print_r($var, true) . "\n");
    }

    /**
     * Test
     *
     * @return void
     */
    public function testArrayColKeys()
    {
        $array = array(
            array('col1'=>'', 'col2'=>'', 'col4'=>''),
            array('col1'=>'', 'col2'=>'', 'col3'=>''),
            array('col1'=>'', 'col2'=>'', 'col3'=>''),
        );
        $colKeys = Utilities::arrayColKeys($array);
        $this->assertSame(array('col1','col2','col3','col4'), $colKeys);
        $array = array(
            array('a','b','c'),
            array('d','e','f','g'),
            array('h','i'),
        );
        $colKeys = Utilities::arrayColKeys($array);
        $this->assertSame(array(0,1,2,3), $colKeys);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testArrayMergeDeep()
    {
        $array1 = array(
            'planes' => 'array1 val',
            'trains' => array('electric','diesel',),
            'automobiles' => array(
                'hatchback' => array(),
                'sedan' => array('family','luxury'),
                'suv' => array('boxy','good'),
            ),
        );
        $array2 = array(
            'boats' => array('speed','house'),
            'trains' => array('steam',),
            'planes' => 'array2 val',
            'automobiles' => array(
                'hatchback' => 'array2 val',
                'suv' => 'array2 val',
            ),
        );
        $arrayExpect = array(
            'planes' => 'array2 val',
            'trains' => array('electric','diesel','steam',),
            'automobiles' => array(
                'hatchback' => 'array2 val',
                'sedan' => array('family','luxury'),
                'suv' => 'array2 val',
            ),
            'boats' => array('speed','house'),
        );
        $array3 = Utilities::arrayMergeDeep($array1, $array2);
        $this->assertSame($arrayExpect, $array3);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testBuildAttribString()
    {
        $attribs = array(
            'src' => '/path/to/image.png',
            'width' => 80,
            'height' => 100,
            'title' => 'Pork & Beans',
        );
        $attribStr = Utilities::buildAttribString($attribs);
        $expect = 'src="/path/to/image.png" width="80" height="100" title="Pork &amp; Beans"';
        $this->assertSame($expect, $attribStr);
    }

    /**
     * Test
     *
     * @return void
     * @todo headers not sent from cli... could test header parsing, but it'll be a protected method...
     */
    public function testGetResponseHeader()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testIsBase64Encoded()
    {
        $base64Str = base64_encode(chunk_split(str_repeat('zippity do dah', 50)));
        $this->assertTrue(Utilities::isBase64Encoded($base64Str));
        $this->assertFalse(Utilities::isBase64Encoded('I\'m just a bill.'));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testIsBinary()
    {
        $this->assertTrue(Utilities::isBinary(sha1('foo', true)));
        $this->assertTrue(Utilities::isBinary(pack("CCC", 0xef, 0xbb, 0xbf).' Pesky Bom'));
        $this->assertFalse(Utilities::isBinary(html_entity_decode('UTF8&trade;')));
    }

    /**
     * Test
     *
     * @return void
     * @link http://www.cl.cam.ac.uk/~mgk25/ucs/examples/UTF-8-test.txt
     */
    public function testIsUtf8()
    {
        $this->assertTrue(Utilities::isUtf8('Plain Jane'));
        $this->assertTrue(Utilities::isUtf8(html_entity_decode('UTF8&trade;')));
        $this->assertTrue(Utilities::isUtf8("\xc2\xa2 \xed\x9f\xbf \xee\x80\x80 \xef\xbf\xbd \xf4\x8f\xbf\xbf \xf4\x90\x80\x80"));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testParseAttribString()
    {
        $class = 'foo bar';
        $innerHtml = '<b>blah</b>'."\n";
        $html = '<span class="'.$class.'">'.$innerHtml.'</span>';
        $parts = Utilities::parseAttribString($html);
        $this->assertSame($class, $parts['class']);
        $this->assertSame($innerHtml, $parts['innerhtml']);
    }

    /**
     * Test
     *
     * @return void
     * @todo
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
            'tm'        => array('<b>'.chr(153).'</b>',
                '&lt;b&gt;&trade;&lt;/b&gt;'),
            'blah'      => array('bèfore [:: whoa ::] àfter',
                'b&egrave;fore [:: whoa ::] &agrave;fter'),
        );
        foreach ($strings as $k => $pair) {
            $expected = $pair[1];
            $string = Utilities::toUtf8($pair[0]);
            $string = htmlentities($string, null, 'UTF-8');
            $this->assertSame($expected, $string, $k.' does not match');
        }
    }

    /**
     * Test
     *
     * @return array of serialized logs
     */
    public function testSerializeLog()
    {
        $log = array(
            array('log', 'What rolls down stairs'),
            array('info', 'alone or in pairs'),
            array('warn', 'rolls over your neighbor\'s dog?'),
        );
        $serialized = Utilities::serializeLog($log);
        return array(
            array($serialized, $log)
        );
    }

    /**
     * Test
     *
     * @param string $serialized   string provided by testSerializeLog dataProvider
     * @param array  $unserialized the unserialized array
     *
     * @return void
     * @dataProvider testSerializeLog
     */
    public function testUnserializeLog($serialized, $unserialized)
    {
        $log = Utilities::unserializeLog($serialized);
        $this->assertSame($unserialized, $log);
    }
}

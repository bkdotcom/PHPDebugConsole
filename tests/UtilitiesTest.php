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

    public function debug($label, $var)
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
        $strEntities = '&Atilde;This test is weak.';
        $strIso8859 = html_entity_decode($strEntities, ENT_QUOTES, 'ISO-8859-1');
        $strUtf8 = Utilities::toUtf8($strIso8859);
        $this->assertSame($strEntities, htmlentities($strUtf8, ENT_QUOTES, 'UTF-8'));
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

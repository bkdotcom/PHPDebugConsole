<?php
/**
 * Run with --process-isolation option
 */

use \bdk\Debug\Utilities;

/**
 * PHPUnit tests for Debug class
 */
class UtilitiesTest extends DebugTestFramework
{

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

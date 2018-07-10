<?php
/**
 * Run with --process-isolation option
 */

use bdk\Debug\Utilities;

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
    public function testArrayPathGet()
    {
        $array = array(
            'surfaces' => array(
                'bed' => array(
                    'comfy' => true,
                ),
                'rock' => array(
                    'comfy' => false,
                )
            ),
        );
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, 'surfaces.bed.comfy'), true);
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, 'surfaces.rock.comfy'), false);
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, 'surfaces.bed.comfy.foo'), null);
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, 'surfaces.bed.comfy.0'), null);
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, 'surfaces.bed'), array('comfy'=>true));
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, 'surfaces.bed.foo'), null);
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, 'surfaces.__count__'), 2);
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, 'surfaces.__end__.comfy'), false);
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, 'surfaces.__reset__.comfy'), true);
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, 'surfaces.sofa.comfy'), null);
        $this->assertSame(\bdk\Debug\Utilities::arrayPathGet($array, array('surfaces','__end__','comfy')), false);
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
            'class' => array('test','dupe','dupe'),
            'title' => 'Pork & Beans',
            'value' => '',      // value=""  (we output empty value attrib)
            'style' => array('position'=>'absolute','display'=>'inline-block'),
            'dingus' => array('unknown array'),
            'foo' => false,     // not output
            'bar' => true,      // bar="bar"
            'baz' => '',        // not output (empty string)
            'autocomplete',     // autocomplete="on"
            'disabled',         // disabled="disabled"
            'data-string' => 'wassup?',
            'data-true' => true,
            'data-array' => array(
                'foo' => 'bar',
            ),
        );
        $attribStr = Utilities::buildAttribString($attribs);
        $expect = ' autocomplete="on" bar="bar" class="dupe test" data-array="{&quot;foo&quot;:&quot;bar&quot;}" data-string="wassup?" data-true="true" disabled="disabled" height="100" src="/path/to/image.png" style="display:inline-block;position:absolute;" title="Pork &amp; Beans" value="" width="80"';
        $this->assertSame($expect, $attribStr);

        $attribs = array(
            'class' => array(),     // not output
            'style' => array(),     // not output
            'data-empty-string' => '',
            'data-empty-array' => array(),
            'data-empty-obj' => (object) array(),
            'data-false' => false,
            'data-null' => null,    // not output
        );
        $attribStr = Utilities::buildAttribString($attribs);
        $expect = ' data-empty-array="[]" data-empty-obj="{}" data-empty-string="" data-false="false"';
        $this->assertSame($expect, $attribStr);

        $attribStr = Utilities::buildAttribString('');
        $this->assertSame('', $attribStr);

        $attribStr = Utilities::buildAttribString('I\'m a string');
        $expect = ' I\'m a string';
        $this->assertSame($expect, $attribStr);

        // don't add more space to existing space... should return a single leading space
        $attribStr = Utilities::buildAttribString('  leading spaces');
        $expect = ' leading spaces';
        $this->assertSame($expect, $attribStr);
    }


    public function testGetBytes()
    {
        $this->assertSame('1 kB', Utilities::getBytes('1kb'));
        $this->assertSame('1 kB', Utilities::getBytes('1024'));
        $this->assertSame('1 kB', Utilities::getBytes(1024));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetCallerInfo()
    {
        $callerInfo = $this->getCallerInfoHelper();
        $this->assertSame(array(
            'file' => __FILE__,
            'line' => __LINE__ - 3,
            'function' => __FUNCTION__,
            'class' => __CLASS__,
            'type' => '->',
        ), $callerInfo);
        $callerInfo = call_user_func(array($this, 'getCallerInfoHelper'));
        $this->assertSame(array(
            'file' => __FILE__,
            'line' => __LINE__ - 3,
            'function' => __FUNCTION__,
            'class' => __CLASS__,
            'type' => '->',
        ), $callerInfo);
    }

    private function getCallerInfoHelper()
    {
        return Utilities::getCallerInfo();
    }

    public function testGetIncludedFiles()
    {
        $filesA = get_included_files();
        $filesB = Utilities::getIncludedFiles();
        sort($filesA);
        sort($filesB);
        $this->assertArraySubset($filesA, $filesB);
    }

    public function testGetInterface()
    {
        $this->assertSame('cli', Utilities::getInterface());
    }

    /**
     * Test
     *
     * @return void
     *
     * @todo better test from cli
     */
    public function testGetResponseHeader()
    {
        $this->assertNull(Utilities::getResponseHeader());
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

    public function testIsList()
    {
        $this->assertFalse(Utilities::isList("string"));
        $this->assertTrue(Utilities::isList(array()));     // empty array = "list"
        $this->assertFalse(Utilities::isList(array(3=>'foo',2=>'bar',1=>'baz',0=>'nope')));
        $this->assertTrue(Utilities::isList(array(0=>'nope',1=>'baz',2=>'bar',3=>'foo')));
    }

    /**
     * Test
     *
     * @return void
     *
     * @todo better test
     */
    public function testMemoryLimit()
    {
        $this->assertNotNull(Utilities::memoryLimit());
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

    public function testRequestId()
    {
        $this->assertStringMatchesFormat('%x', Utilities::requestId());
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
     *
     * @dataProvider testSerializeLog
     */
    public function testUnserializeLog($serialized, $unserialized)
    {
        $log = Utilities::unserializeLog($serialized);
        $this->assertSame($unserialized, $log);
    }
}

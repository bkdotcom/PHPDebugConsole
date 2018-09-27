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
        $testStack = array(
            array(
                'attribs' => array(
                    'src' => '/path/to/image.png',
                    'width' => 80,
                    'height' => '100',
                    'class' => array('test','dupe','dupe'),
                    'title' => 'Pork & Beans',
                    'value' => '',      // value=""
                    'style' => array('position'=>'absolute','display'=>'inline-block'),
                    'dingus' => array('unknown array'),
                    'hidden' => false,
                    'foo' => false,     // not a valid boolean attrib - we'll not output regardless
                    'bar' => true,      // not a valid boolean attrib - we'll output anyhow : bar="bar"
                    'AUTOCOMPLETE',     // autocomplete="on"
                    'disabled',         // disabled="disabled"
                    'data-null' => null,
                    'data-false' => false,
                    'data-string' => 'wassup?',
                    'data-true' => true,
                    'data-array' => array('foo' => 'bar'),
                    'data-obj' => (object) array('key' => 'val'),
                ),
                'expect' => ''
                    .' autocomplete="on"'
                    .' bar="bar"'
                    .' class="dupe test"'
                    .' data-array="{&quot;foo&quot;:&quot;bar&quot;}"'
                    .' data-false="false"'
                    .' data-null="null"'
                    .' data-obj="{&quot;key&quot;:&quot;val&quot;}"'
                    .' data-string="wassup?"'
                    .' data-true="true"'
                    .' disabled="disabled"'
                    .' height="100"'
                    .' src="/path/to/image.png"'
                    .' style="display:inline-block;position:absolute;"'
                    .' title="Pork &amp; Beans"'
                    .' value=""'
                    .' width="80"',
            ),
            array(
                'attribs' => array(
                    'class' => array(),     // not output
                    'style' => array(),     // not output
                    'data-empty-string' => '',
                    'data-empty-array' => array(),
                    'data-empty-obj' => (object) array(),
                    'data-false' => false,
                    'data-null' => null,    // not output
                ),
                'expect' => ' data-empty-array="[]" data-empty-obj="{}" data-empty-string="" data-false="false" data-null="null"',
            ),
            array(
                'attribs' => '',
                'expect' => '',
            ),
            array(
                'attribs' => 'I\'m a string',
                'expect' => ' I\'m a string',
            ),
            array(
                // should return a single leading space
                'attribs' => '  leading spaces',
                'expect' => ' leading spaces',
            ),
        );
        foreach ($testStack as $test) {
            $ret = \bdk\Debug\Utilities::buildAttribString($test['attribs']);
            $this->assertSame($test['expect'], $ret);
        }
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
     * @return void
     */
    public function testParseAttribString()
    {
        $testStack = array(
            array(
                'params' => array(''),
                'expect' => array(),
            ),
            array(
                'params' => array(
                    ' '
                    .' placeholder="&quot;quotes&quot; &amp; ampersands"'
                    .' autocomplete=off'
                    .' required=required'
                    .' autofocus'
                    .' notabool'
                    .' value=""'
                    .' data-null="null"'
                    .' data-obj="{&quot;foo&quot;:&quot;bar&quot;}"'
                    .' data-str = "foo"'
                    .' data-zero="0"',
                ),
                'expect' => array(
                    'autocomplete' => 'off',
                    'autofocus' => true,
                    'data-null' => null,
                    'data-obj' => array('foo'=>'bar'),
                    'data-str' => 'foo',
                    'data-zero' => 0,
                    'notabool' => '',
                    'placeholder' => '"quotes" & ampersands',
                    'required' => true,
                    'value' => '',
                ),
            ),
            array(
                'params' => array(
                    'value="sun &amp; ski" data-true="true" data-false="false" data-null="null" data-list="[&quot;a&quot;,&quot;b&quot;]"',
                    false,
                ),
                'expect' => array(
                    'data-false' => 'false',
                    'data-list' => '["a","b"]',
                    'data-null' => 'null',
                    'data-true' => 'true',
                    'value' => 'sun & ski',
                ),
            ),
        );
        foreach ($testStack as $test) {
            $ret = call_user_func_array('\\bdk\\Debug\\Utilities::parseAttribString', $test['params']);
            $this->assertSame($test['expect'], $ret);
        }
    }

    /**
     * @return void
     */
    public function testParseTag()
    {
        $testStack = array(
            array(
                'tag' => '<div class="test" ><i>stuff</i> &amp; things</div>',
                'expect' => array(
                    'tagname'=>'div',
                    'attribs' => array(
                        'class'=>'test',
                    ),
                    'innerhtml'=>'<i>stuff</i> &amp; things',
                ),
            ),
            array(
                'tag' => '<input name="name" value="Billy" required/>',
                'expect' => array(
                    'tagname' => 'input',
                    'attribs' => array(
                        'name' => 'name',
                        'required' => true,
                        'value' => 'Billy',
                    ),
                    'innerhtml' => null,
                ),
            ),
            array(
                'tag' => '</ hr>',
                'expect' => array(
                    'tagname' => 'hr',
                    'attribs' => array(),
                    'innerhtml' => null,
                ),
            ),
        );
        foreach ($testStack as $test) {
            $ret = \bdk\Debug\Utilities::parseTag($test['tag']);
            $this->assertSame($test['expect'], $ret);
        }
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

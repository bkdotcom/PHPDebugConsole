<?php

/**
 * PHPUnit tests for Debug class
 */
class VarDumpTest extends PHPUnit_Framework_DOMTestCase
{

    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->debug = new \bdk\Debug\Debug(array(
            'collect' => true,
            'output' => true,
            'outputCss' => false,
            'outputScript' => false,
            'outputAs' => 'html',
        ));
    }

    /**
     * tearDown is executed after each test
     *
     * @return void
     */
    public function tearDown()
    {
        $this->debug->set('output', false);
    }

    /**
     * Util to output to console / help in creation of tests
     */
    public function output($label, $var)
    {
        fwrite(STDOUT, $label.' = '.print_r($var, true) . "\n");
    }

    public function dumpProvider()
    {
        $ts = time();
        $fh = fopen(__FILE__, 'r');
		// indented with tab
        $arrayDumpHtml = <<<'EOD'
<span class="t_array"><span class="t_keyword">Array</span><span class="t_punct">(</span>
<span class="array-inner">
	<span class="key-value"><span class="t_key">[0]</span> <span class="t_operator">=&gt;</span> <span class="t_string">a</span></span>
	<span class="key-value"><span class="t_key">[foo]</span> <span class="t_operator">=&gt;</span> <span class="t_string">bar</span></span>
	<span class="key-value"><span class="t_key">[1]</span> <span class="t_operator">=&gt;</span> <span class="t_string">c</span></span>
</span><span class="t_punct">)</span></span>
EOD;
		// indented with 4 spaces
		$arrayDumpText = <<<'EOD'
Array(
    [0] => "a"
    [foo] => "bar"
    [1] => "c"
)
EOD;
        // var, html, text script
        return array(
            array(null, '<span class="t_null">null</span>', 'null', 'null'),
            array(true, '<span class="t_bool true">true</span>', 'true', 'true'),
            array(false, '<span class="t_bool false">false</span>', 'false', 'false'),
            array('a "string"'."\r\n\tline 2",
                '<span class="t_string">a &quot;string&quot;<span class="ws_r"></span><span class="ws_n"></span>'."\n"
                    .'<span class="ws_t">'."\t".'</span>line 2</span>',
                '"a "string"'."\r\n\t".'line 2"',
                '"a \"string\"\r\n\tline 2"'),
            array("\xef\xbb\xbfPesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \x07 (a control char).",
                '<span class="t_string"><span class="binary">\xef\xbb\xbf</span>Pesky &lt;abbr title=&quot;Byte-Order-Mark&quot;&gt;BOM&lt;/abbr&gt; and <span class="binary">\x07</span> (a control char).</span>',
                '"\xef\xbb\xbfPesky <abbr title="Byte-Order-Mark">BOM</abbr> and \x07 (a control char)."',
                '"\\\\xef\\\\xbb\\\\xbfPesky <abbr title=\"Byte-Order-Mark\">BOM<\/abbr> and \\\\x07 (a control char)."'),
            array('10', '<span class="t_string numeric">10</span>', '"10"', '"10"'),
            array('10.10', '<span class="t_string numeric">10.10</span>', '"10.10"', '"10.10"'),
            array(10, '<span class="t_int">10</span>', 10, '10'),
            array(10.10, '<span class="t_float">10.1</span>', 10.10, '10.1'),
            array($ts, '<span class="t_int timestamp" title="'.date('Y-m-d H:i:s', $ts).'">'.$ts.'</span>', $ts, (string)$ts),
            array($fh, '<span class="t_resource">Resource id #'.(int)$fh.': stream</span>', 'Resource id #'.(int)$fh.': stream', '"Resource id #'.(int)$fh.': stream"'),
            array(array('a','foo'=>'bar','c'), $arrayDumpHtml, $arrayDumpText, '{"0":"a","foo":"bar","1":"c"}'),
        );
        fclose($fh);
    }

    /**
     * Test
     *
     * @dataProvider dumpProvider
     *
     * @return void
     */
    public function testDump($val, $html, $text, $script)
    {
        $dump = $this->debug->varDump->dump($val, 'html');
        // $this->output('dump', str_replace(array("\r","\n","\t"), array('\r','\n','\t'), $dump));
        $this->assertSame($html, $dump);
        $dump = $this->debug->varDump->dump($val, 'text');
        $this->assertSame($text, $dump);
        $dump = $this->debug->varDump->dump($val, 'script');
        $this->assertSame($script, $dump);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testDumpBinary()
    {
    	// tested via testDump
    }

    /**
     * Test
     *
     * @return void
     */
    public function testDumpTable()
    {
        $list = array(
            // note different order of keys / not all rows have all cols
            array('name'=>'Bob', 'age'=>'12', 'sex'=>'M', 'Naughty'=>false),
            array('Naughty'=>true, 'name'=>'Sally', 'extracol' => 'yes', 'sex'=>'F', 'age'=>'10'),
        );
        $html = $this->debug->varDump->dumpTable($list, 'table caption');
        $expect = <<<'EOD'
<table>
<caption>table caption</caption>
<thead><tr><th>&nbsp;</th><th>name</th><th scope="col">age</th><th scope="col">sex</th><th scope="col">Naughty</th><th scope="col">extracol</th></tr>
</thead>
<tbody>
<tr><td class="t_int">0</td><td class="t_string">Bob</td><td class="t_string numeric">12</td><td class="t_string">M</td><td class="t_bool false">false</td><td class="t_undefined"></td></tr>
<tr><td class="t_int">1</td><td class="t_string">Sally</td><td class="t_string numeric">10</td><td class="t_string">F</td><td class="t_bool true">true</td><td class="t_string">yes</td></tr>
</tbody>
</table>
EOD;
        // $this->output('table', $html);
        $this->assertSame($expect, $html);
        $html = $this->debug->varDump->dumpTable(array(), 'caption');
        $this->assertSame('<div class="m_log">caption = array()</div>', $html);
        $html = $this->debug->varDump->dumpTable('blah', 'caption');
        $this->assertSame('<div class="m_log">caption = <span class="t_string">blah</span></div>', $html);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGet()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetAbstraction()
    {
        // mostly alrady tested via logTest, infoTest, warnTest, errorTest....
        // test object inheritance
        $test = new \bdk\DebugTest\Test();
        $abs = $this->debug->varDump->getAbstraction($test);
        $this->assertArrayHasKey('inheritedProp', $abs['properties']);
        $this->assertSame(array(
            'INHERITED' => 'hello world',
            'MY_CONSTANT' => 'constant value',
        ), $abs['constants']);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testSet()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testVisualWhiteSpace()
    {
    	// tested via testDump
    }

    /**
     * Test thatt scalar reference vals get dereferenced
     * Sine passed by-value to log... nothing special being done
     *
     * @return void
     */
    public function testDereferenceBasic()
    {
        $src = 'success';
        $ref = &$src;
        $this->debug->log('ref', $ref);
        $src = 'fail';
        $output = $this->debug->output();
        $this->assertContains('success', $output);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testTypeResource()
    {
        // tested via testDump
    }
}

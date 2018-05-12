<?php

/**
 * PHPUnit tests for Debug class
 */
class TypeArrayTest extends DebugTestFramework
{

    public function providerTestMethod()
    {
		// indented with tab
        $arrayDumpHtml = <<<'EOD'
<div class="m_log"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
<span class="array-inner">
	<span class="key-value"><span class="t_key t_int">0</span> <span class="t_operator">=&gt;</span> <span class="t_string">a</span></span>
	<span class="key-value"><span class="t_key">foo</span> <span class="t_operator">=&gt;</span> <span class="t_string">bar</span></span>
	<span class="key-value"><span class="t_key t_int">1</span> <span class="t_operator">=&gt;</span> <span class="t_string">c</span></span>
</span><span class="t_punct">)</span></span></div>
EOD;
		// indented with 4 spaces
		$arrayDumpText = <<<'EOD'
array(
    [0] => "a"
    [foo] => "bar"
    [1] => "c"
)
EOD;
        /*
        $arrayDumpScript = array(
            'a',
            'foo' => 'bar',
            'c',
        );
        */
        // val, html, text script
        return array(
            array(
                'log',
                array(
                    array('a','foo'=>'bar','c')
                ),
                array(
                    'html' => $arrayDumpHtml,
                    'text' => $arrayDumpText,
                    'script' => 'console.log({"0":"a","foo":"bar","1":"c"});',
                ),
            ),
        );
    }

    /**
     * Test
     *
     * array value is a reference..
     * change value after logging..
     * logged value should reflect value at time of logging
     *
     * @return void
     */
    public function testDereferenceArray()
    {
        $test_val = 'success';
        $test_a = array(
            'ref' => &$test_val,
        );
        $this->debug->log('test_a', $test_a);
        $test_val = 'fail';
        $output = $this->debug->output();
        $this->assertContains('success', $output);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRecursiveArray()
    {
        $array = array();
        $array[] = &$array;
        $this->debug->log('array', $array);
        $abstraction = $this->debug->getData('log/0/1/1');
        $this->assertEquals(
            \bdk\Debug\Abstracter::RECURSION,
            $abstraction[0],
            'Did not find expected recursion'
        );
        $output = $this->debug->output();
        $test_a = array( 'foo' => 'bar' );
        $test_a['val'] = &$test_a;
        $this->debug->log('test_a', $test_a);
        $output = $this->debug->output();
        $this->assertContains('t_recursion', $output);
        $this->testMethod(
            'log',
            array($test_a),
            array(
                'chromeLogger' => array(
                    array(
                        array(
                            'foo' => 'bar',
                            'val' => 'array *RECURSION*'
                        ),
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-37: 56|[{"Type":"LOG"},{"foo":"bar","val":"array *RECURSION*"}]|',
                'html' => function ($strHtml) {
                    $this->assertSelectEquals('.key-value > .t_keyword', 'array', true, $strHtml);
                    $this->assertSelectEquals('.key-value > .t_recursion', '*RECURSION*', true, $strHtml);
                },
                'text' => array('contains' => '    [val] => array *RECURSION*'),
                'script' => 'console.log({"foo":"bar","val":"array *RECURSION*"});',
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRecursiveArray2()
    {
        /*
            $test_a is a circular reference
            $test_b references $test_a
        */
        $test_a = array();
        $test_a[] = &$test_a;
        $this->debug->log('test_a', $test_a);
        $test_b = array('foo', &$test_a, 'bar');
        $this->debug->log('test_b', $test_b);
        $output = $this->debug->output();
        $this->assertSelectCount('.t_recursion', 2, $output, 'Does not contain two recursion types');
    }
}

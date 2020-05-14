<?php

use bdk\Debug\Abstraction\Abstracter;

/**
 * PHPUnit tests for Debug class
 */
class TypeArrayTest extends DebugTestFramework
{

    public function providerTestMethod()
    {
		// indented with tab
        $arrayDumpHtml = <<<'EOD'
<li class="m_log"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
<ul class="array-inner list-unstyled">
	<li><span class="t_key t_int">0</span><span class="t_operator">=&gt;</span><span class="t_string">a</span></li>
	<li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">bar</span></li>
	<li><span class="t_key t_int">1</span><span class="t_operator">=&gt;</span><span class="t_string">c</span></li>
</ul><span class="t_punct">)</span></span></li>
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
                    array('a','foo' => 'bar','c')
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
        $testVal = 'success';
        $testA = array(
            'ref' => &$testVal,
        );
        $this->debug->log('testA', $testA);
        $testVal = 'fail';
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
        $abstraction = $this->debug->getData('log/0/args/1');
        $this->assertEquals(
            Abstracter::RECURSION,
            $abstraction[0],
            'Did not find expected recursion'
        );
        $output = $this->debug->output();
        $testA = array( 'foo' => 'bar' );
        $testA['val'] = &$testA;
        $this->debug->log('testA', $testA);
        $output = $this->debug->output();
        $this->assertContains('t_recursion', $output);
        $this->testMethod(
            'log',
            array($testA),
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
                    $this->assertSelectEquals('.array-inner > li > .t_keyword', 'array', true, $strHtml);
                    $this->assertSelectEquals('.array-inner > li > .t_recursion', '*RECURSION*', true, $strHtml);
                },
                'text' => array('contains' => '    [val] => array *RECURSION*'),
                'script' => 'console.log({"foo":"bar","val":"array *RECURSION*"});',
                'streamAnsi' => array('contains' => "    \e[38;5;245m[\e[38;5;83mval\e[38;5;245m]\e[38;5;130m => \e[0m\e[38;5;45marray \e[38;5;196m*RECURSION*\e[0m"),
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
            $testA is a circular reference
            $testB references $testA
        */
        $testA = array();
        $testA[] = &$testA;
        $this->debug->log('test_a', $testA);
        $testB = array('foo', &$testA, 'bar');
        $this->debug->log('testB', $testB);
        $output = $this->debug->output();
        $this->assertSelectCount('.t_recursion', 2, $output, 'Does not contain two recursion types');
    }
}

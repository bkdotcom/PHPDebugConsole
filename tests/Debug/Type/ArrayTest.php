<?php

namespace bdk\Test\Debug\Type;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Dump\BaseValue
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\TextAnsiValue
 * @covers \bdk\Debug\Dump\TextValue
 * @covers \bdk\Debug\Abstraction\Abstracter
 * @covers \bdk\Debug\Abstraction\Abstraction
 * @covers \bdk\Debug\Abstraction\AbstractArray
 */
class ArrayTest extends DebugTestFramework
{
    public function providerTestMethod()
    {
		// indented with tab
        $arrayDumpHtml = <<<'EOD'
<li class="m_log"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
<ul class="array-inner list-unstyled">
	<li><span class="t_int t_key">0</span><span class="t_operator">=&gt;</span><span class="t_string">a</span></li>
	<li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">bar</span></li>
	<li><span class="t_int t_key">1</span><span class="t_operator">=&gt;</span><span class="t_string">c</span></li>
    <li><span class="t_key">obj</span><span class="t_operator">=&gt;</span><div class="t_object" data-accessible="public"><span class="classname">stdClass</span>
        <dl class="object-inner">
        <dt class="properties">properties</dt>
        <dd class="property public"><span class="t_modifier_public">public</span> <span class="t_identifier">foo</span> <span class="t_operator">=</span> <span class="t_string">bar</span></dd>
        <dt class="methods">no methods</dt>
        </dl>
        </div></li>
</ul><span class="t_punct">)</span></span></li>
EOD;
		// indented with 4 spaces
		$arrayDumpText = <<<'EOD'
array(
    [0] => "a"
    [foo] => "bar"
    [1] => "c"
    [obj] => stdClass
        Properties:
        (public) foo = "bar"
        Methods: none!
)
EOD;
        /*
        $arrayDumpScript = array(
            'a',
            'foo' => 'bar',
            'c',
        );
        */
        $tests = array(
            'basic' => array(
                'log',
                array(
                    array(
                        'a',
                        'foo' => 'bar',
                        'c',
                        'obj' => (object) array('foo' => 'bar'),
                    )
                ),
                array(
                    'html' => $arrayDumpHtml,
                    'text' => $arrayDumpText,
                    'script' => 'console.log({"0":"a","foo":"bar","1":"c","obj":{"___class_name":"stdClass","(public) foo":"bar"}});',
                    'streamAnsi' => \str_replace('\e', "\e", '\e[38;5;45marray\e[38;5;245m(\e[0m' . "\n"
                        . '\e[38;5;245m[\e[96m0\e[38;5;245m]\e[38;5;130m => \e[0m\e[38;5;250m"\e[0ma\e[38;5;250m"\e[0m' . "\n"
                        . '\e[38;5;245m[\e[38;5;83mfoo\e[38;5;245m]\e[38;5;130m => \e[0m\e[38;5;250m"\e[0mbar\e[38;5;250m"\e[0m' . "\n"
                        . '\e[38;5;245m[\e[96m1\e[38;5;245m]\e[38;5;130m => \e[0m\e[38;5;250m"\e[0mc\e[38;5;250m"\e[0m' . "\n"
                        . '\e[38;5;245m[\e[38;5;83mobj\e[38;5;245m]\e[38;5;130m => \e[0m\e[1mstdClass\e[22m' . "\n"
                        . '\e[4mProperties:\e[24m' . "\n"
                        . '\e[38;5;250m(public)\e[0m \e[38;5;83mfoo\e[0m \e[38;5;130m=\e[0m \e[38;5;250m"\e[0mbar\e[38;5;250m"\e[0m' . "\n"
                        . 'Methods: none!' . "\n"
                        . '\e[38;5;245m)\e[0m'),
                    // 'wamp' => @todo
                ),
            ),
            'callable' => array(
                'log',
                array(
                    array(Debug::getInstance(), 'getInstance'),
                ),
                array(
                    'html' => '<li class="m_log"><span class="t_callable"><span class="t_type">callable</span> <span class="classname"><span class="namespace">bdk\</span>Debug</span><span class="t_operator">::</span><span class="t_identifier">getInstance</span></span></li>',
                    'text' => 'callable: bdk\Debug::getInstance',
                    'script' => 'console.log("callable: bdk\\\\Debug::getInstance");',
                    'streamAnsi' => "callable: \e[38;5;250mbdk\\\e[0m\e[1mDebug\e[22m\e[38;5;130m::\e[0m\e[1mgetInstance\e[22m",
                ),
            )
        );
        if (PHP_VERSION_ID >= 70000) {
            $filepath = \realpath(__DIR__ . '/../Fixture/Anonymous.php');
            $anonymous = require $filepath;
            $callable = array($anonymous['stdClass'], 'myMethod');
            $tests['anonymous callable'] = array(
                'log',
                array(
                    $callable,
                ),
                array(
                    'chromeLogger' => array(
                        array(
                            'callable: stdClass@anonymous::myMethod'
                        ),
                        null,
                        '',
                    ),
                    'firephp' => 'X-Wf-1-1-1-%d: 57|[{"Type":"LOG"},"callable: stdClass@anonymous::myMethod"]|',
                    'html' => '<li class="m_log"><span class="t_callable"><span class="t_type">callable</span> <span class="classname">stdClass@anonymous</span><span class="t_operator">::</span><span class="t_identifier">myMethod</span></span></li>',
                    'script' => 'console.log("callable: stdClass@anonymous::myMethod");',
                    'text' => 'callable: stdClass@anonymous::myMethod',
                    // 'wamp' =>
                ),
            );
        }
        return $tests;
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
        $this->assertStringContainsString('success', $output);
    }

    public function testGetAbstraction()
    {
        $this->testMethod(
            'log',
            array(
                $this->debug->abstracter->crateWithVals(
                    array('foo','bar'),
                    array(
                        'options' => array(
                            'showListKeys' => false,
                        ),
                    )
                ),
            ),
            array(
                'html' => '<li class="m_log"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                    <ul class="array-inner list-unstyled">
                    <li class="t_string">foo</li>
                    <li class="t_string">bar</li>
                    </ul><span class="t_punct">)</span></span></li>',
                'text' => 'array(
                    [0] => "foo"
                    [1] => "bar"
                )',
                'script' => 'console.log(["foo","bar"]);',
            )
        );
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
        $abstraction = $this->debug->data->get('log/0/args/1');
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
        $this->assertStringContainsString('t_recursion', $output);
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
                'script' => 'console.log({"foo":"bar","val":"array *RECURSION*"});',
                'streamAnsi' => array('contains' => "    \e[38;5;245m[\e[38;5;83mval\e[38;5;245m]\e[38;5;130m => \e[0m\e[38;5;45marray \e[38;5;196m*RECURSION*\e[0m"),
                'text' => array('contains' => '    [val] => array *RECURSION*'),
                'wamp' => array(
                    'log',
                    array(
                        array(
                            'foo' => 'bar',
                            'val' => Abstracter::RECURSION,
                            /*
                            '__debug_key_order__' => array(
                                'foo',
                                'val',
                            )
                            */
                        ),
                    ),
                    array(),
                ),
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

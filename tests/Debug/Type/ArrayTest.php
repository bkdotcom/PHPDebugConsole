<?php

namespace bdk\Test\Debug\Type;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Dump\AbstractValue
 * @covers \bdk\Debug\Dump\Base\Value
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\Value
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\Text\Value
 * @covers \bdk\Debug\Dump\TextAnsi\Value
 * @covers \bdk\Debug\Abstraction\Abstracter
 * @covers \bdk\Debug\Abstraction\Abstraction
 * @covers \bdk\Debug\Abstraction\AbstractArray
 */
class ArrayTest extends DebugTestFramework
{
    public static function providerTestMethod()
    {
		// indented with tab
        $arrayDumpHtml = '
            <li class="m_log"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
            <ul class="array-inner list-unstyled">
            	<li><span class="t_int t_key">0</span><span class="t_operator">=&gt;</span><span class="t_string">a</span></li>
            	<li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">bar</span></li>
            	<li><span class="t_int t_key">1</span><span class="t_operator">=&gt;</span><span class="t_string">c</span></li>
                <li><span class="t_key">obj</span><span class="t_operator">=&gt;</span><div class="groupByInheritance t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    ' . (PHP_VERSION_ID >= 80200
                        ? '<dt class="attributes">attributes</dt>
                            <dd class="attribute"><span class="classname">AllowDynamicProperties</span></dd>'
                        : ''
                    ) . '
                    <dt class="properties">properties</dt>
                    <dd class="property public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">foo</span> <span class="t_operator">=</span> <span class="t_string">bar</span></dd>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </div></li>
            </ul><span class="t_punct">)</span></span></li>';
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
                        . '\e[38;5;245m[\e[96m0\e[38;5;83;49m\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0ma\e[38;5;250m"\e[0m' . "\n"
                        . '\e[38;5;245m[\e[38;5;83mfoo\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0mbar\e[38;5;250m"\e[0m' . "\n"
                        . '\e[38;5;245m[\e[96m1\e[38;5;83;49m\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0mc\e[38;5;250m"\e[0m' . "\n"
                        . '\e[38;5;245m[\e[38;5;83mobj\e[38;5;245m]\e[38;5;224m => \e[0m\e[1mstdClass\e[22m' . "\n"
                        . '\e[4mProperties:\e[24m' . "\n"
                        . '\e[38;5;250m(public)\e[0m \e[38;5;83mfoo\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mbar\e[38;5;250m"\e[0m' . "\n"
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
                    'script' => 'console.log("callable: bdk\\\\Debug::getInstance");',
                    'streamAnsi' => "callable: \e[38;5;250mbdk\\\e[0m\e[1mDebug\e[22m\e[38;5;224m::\e[0m\e[1mgetInstance\e[22m",
                    'text' => 'callable: bdk\Debug::getInstance',
                ),
            ),
            'keys' => array(
                'log',
                array(
                    array(
                        "\xE2\x80\x8B" => 'zwsp',
                        "\xef\xbb\xbf" => 'bom',
                        "\xef\xbb\xbfbom\r\n\t\x07 \x1F \x7F \x00 \xc2\xa0<i>(nbsp)</i> \xE2\x80\x89(thsp), & \xE2\x80\x8B(zwsp)" => 'ctrl chars and whatnot',
                        "not\x80\xCF\x85tf8" => 'not utf8', // this forces the array to be stored as an abstraction
                        ' ' => 'space',
                        '' => 'empty',
                    ),
                ),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'keys' => array(
                                    'ade50251dade9edc27e822ebdc3e9664' => array(
                                        'brief' => false,
                                        'chunks' => array(
                                            ['utf8', 'not'],
                                            ['other', '80'],
                                            ['utf8', "\xCF\x85tf8"],
                                        ),
                                        'debug' => Abstracter::ABSTRACTION,
                                        'percentBinary' => 1 / 9 * 100,
                                        'strlen' => 9,
                                        'strlenValue' => 9,
                                        'type' => Type::TYPE_STRING,
                                        'typeMore' => Type::TYPE_STRING_BINARY,
                                        'value' => '',
                                    ),
                                ),
                                'type' => Type::TYPE_ARRAY,
                                'value' => array(
                                    "\xE2\x80\x8B" => 'zwsp',
                                    "\xef\xbb\xbf" => 'bom',
                                    "\xef\xbb\xbfbom\r\n\t\x07 \x1f \x7f \x00 \xc2\xa0<i>(nbsp)</i> \xE2\x80\x89(thsp), & \xE2\x80\x8B(zwsp)" => 'ctrl chars and whatnot',
                                    'ade50251dade9edc27e822ebdc3e9664' => 'not utf8',
                                    ' ' => 'space',
                                    '' => 'empty',
                                ),
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                        <ul class="array-inner list-unstyled">
                            <li><span class="t_key"><span class="unicode" data-code-point="200B" title="U-200B: Zero Width Space">\u{200b}</span></span><span class="t_operator">=&gt;</span><span class="t_string">zwsp</span></li>
                            <li><span class="t_key"><span class="unicode" data-code-point="FEFF" title="U-FEFF: BOM / Zero Width No-Break Space">\u{feff}</span></span><span class="t_operator">=&gt;</span><span class="t_string">bom</span></li>
                            <li><span class="t_key"><span class="unicode" data-code-point="FEFF" title="U-FEFF: BOM / Zero Width No-Break Space">\u{feff}</span>bom<span class="ws_r"></span><span class="ws_n"></span>
                        <span class="ws_t">%s</span><span class="char-control" title="\x07: BEL (bell)">␇</span> <span class="char-control" title="\x1f: US (unit separator)">␟</span> <span class="char-control" title="\x7f: DEL">␡</span> <span class="char-control" title="\x00: NUL">␀</span> <span class="unicode" data-code-point="00A0" title="U-00A0: NBSP">\u{00a0}</span>&lt;i&gt;(nbsp)&lt;/i&gt; <span class="unicode" data-code-point="2009" title="U-2009: Thin Space">\u{2009}</span>(thsp), &amp; <span class="unicode" data-code-point="200B" title="U-200B: Zero Width Space">\u{200b}</span>(zwsp)</span><span class="t_operator">=&gt;</span><span class="t_string">ctrl chars and whatnot</span></li>
                            <li><span class="t_key">not<span class="binary">\x80</span><span class="unicode" data-code-point="03C5" title="U-03C5: GREEK SMALL LETTER UPSILON">' . "\xCF\x85" . '</span>tf8</span><span class="t_operator">=&gt;</span><span class="t_string">not utf8</span></li>
                            <li><span class="t_key"> </span><span class="t_operator">=&gt;</span><span class="t_string">space</span></li>
                            <li><span class="t_key"></span><span class="t_operator">=&gt;</span><span class="t_string">empty</span></li>
                        </ul><span class="t_punct">)</span></span></li>',
                    'chromeLogger' => array(
                        array(
                            array(
                                '\u{200b}' => 'zwsp',
                                '\u{feff}' => 'bom',
                                '\u{feff}bom' . "\r\n\t" . '\x07 \x1f \x7f \x00 \u{00a0}<i>(nbsp)</i> \u{2009}(thsp), & \u{200b}(zwsp)' => 'ctrl chars and whatnot',
                                'not\x80\\u{03c5}tf8' => 'not utf8',
                                ' ' => 'space',
                                '' => 'empty',
                            ),
                        ),
                        null,
                        '',
                    ),
                    'firephp' => 'X-Wf-1-1-1-9: 239|[{"Type":"LOG"},{"\\\\u{200b}":"zwsp","\\\\u{feff}":"bom","\\\\u{feff}bom\\r\\n\\t\\\\x07 \\\\x1f \\\\x7f \\\\x00 \\\\u{00a0}<i>(nbsp)</i> \\\\u{2009}(thsp), & \\\\u{200b}(zwsp)":"ctrl chars and whatnot","not\\\\x80\\\\u{03c5}tf8":"not utf8"," ":"space","":"empty"}]|',
                    'script' => 'console.log({"\\\\u{200b}":"zwsp","\\\\u{feff}":"bom","\\\\u{feff}bom\\r\\n\\t\\\\x07 \\\\x1f \\\\x7f \\\\x00 \\\\u{00a0}<i>(nbsp)</i> \\\\u{2009}(thsp), & \\\\u{200b}(zwsp)":"ctrl chars and whatnot","not\\\\x80\\\\u{03c5}tf8":"not utf8"," ":"space","":"empty"});',
                    'streamAnsi' => "\e[38;5;45marray\e[38;5;245m(\e[0m
                        \e[38;5;245m[\e[38;5;83m\e[38;5;208m\\u{200b}\e[38;5;83;49m\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m\"\e[0mzwsp\e[38;5;250m\"\e[0m
                        \e[38;5;245m[\e[38;5;83m\e[38;5;208m\\u{feff}\e[38;5;83;49m\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m\"\e[0mbom\e[38;5;250m\"\e[0m
                        \e[38;5;245m[\e[38;5;83m\e[38;5;208m\\u{feff}\e[38;5;83;49mbom[\\r]
                            \e[38;5;208m\\x07\e[38;5;83;49m \e[38;5;208m\\x1f\e[38;5;83;49m \e[38;5;208m\\x7f\e[38;5;83;49m \e[38;5;208m\\x00\e[38;5;83;49m \e[38;5;208m\\u{00a0}\e[38;5;83;49m<i>(nbsp)</i> \e[38;5;208m\\u{2009}\e[38;5;83;49m(thsp), & \e[38;5;208m\\u{200b}\e[38;5;83;49m(zwsp)\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m\"\e[0mctrl chars and whatnot\e[38;5;250m\"\e[0m
                        \e[38;5;245m[\e[38;5;83mnot\e[30;48;5;250m80\e[38;5;83;49m\e[38;5;208m\\u{03c5}\e[38;5;83;49mtf8\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m\"\e[0mnot utf8\e[38;5;250m\"\e[0m
                        \e[38;5;245m[\e[38;5;83m \e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m\"\e[0mspace\e[38;5;250m\"\e[0m
                        \e[38;5;245m[\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m\"\e[0mempty\e[38;5;250m\"\e[0m
                        \e[38;5;245m)\e[0m",
                    'text' => 'array(
                        [\u{200b}] => "zwsp"
                        [\u{feff}] => "bom"
                        [\u{feff}bom' . "\r" . '
                            \x07 \x1f \x7f \x00 \u{00a0}<i>(nbsp)</i> \u{2009}(thsp), & \u{200b}(zwsp)] => "ctrl chars and whatnot"
                        [not\x80\u{03c5}tf8] => "not utf8"
                        [ ] => "space"
                        [] => "empty"
                    )',
                ),
            ),
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
                            'callable: stdClass@anonymous::myMethod',
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
        // $tests = \array_intersect_key($tests, \array_diff_key(\array_flip(array('keys'))));
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
        self::assertStringContainsString('success', $output);
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

    public function testMaxDepth()
    {
        $this->testMethod(
            'log',
            array(
                'array',
                array(
                    'foo' => 'bar',
                    'tooDeep' => array(),
                    'ding' => 'dong',
                ),
                $this->debug->meta('cfg', 'maxDepth', 1),
            ),
            array(
                'entry' => array(
                    'method' => 'log',
                    'args' => array(
                        'array',
                        array(
                            'foo' => 'bar',
                            'tooDeep' => array(
                                'debug' => Abstracter::ABSTRACTION,
                                'options' => array(
                                    'isMaxDepth' => true,
                                ),
                                'type' => Type::TYPE_ARRAY,
                                'value' => array(),
                            ),
                            'ding' => 'dong',
                        ),
                    ),
                    'meta' => array(),
                ),
                'html' => '<li class="m_log"><span class="no-quotes t_string">array</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                    <ul class="array-inner list-unstyled">
                    <li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">bar</span></li>
                    <li><span class="t_key">tooDeep</span><span class="t_operator">=&gt;</span><span class="max-depth t_array"><span class="t_keyword">array</span> <span class="t_maxDepth">*MAX DEPTH*</span></span></li>
                    <li><span class="t_key">ding</span><span class="t_operator">=&gt;</span><span class="t_string">dong</span></li>
                    </ul><span class="t_punct">)</span></span></li>',
                'script' => 'console.log("array",{"foo":"bar","tooDeep":"array *MAX DEPTH*","ding":"dong"});',
                'streamAnsi' => \str_replace('\e', "\e", 'array \e[38;5;245m=\e[0m \e[38;5;45marray\e[38;5;245m(\e[0m' . "\n"
                    . '\e[38;5;245m[\e[38;5;83mfoo\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0mbar\e[38;5;250m"\e[0m' . "\n"
                    . '\e[38;5;245m[\e[38;5;83mtooDeep\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;45marray \e[38;5;196m*MAX DEPTH*\e[0m' . "\n"
                    . '\e[38;5;245m[\e[38;5;83mding\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0mdong\e[38;5;250m"\e[0m' . "\n"
                    . '\e[38;5;245m)\e[0m'),
                'text' => 'array = array(
                    [foo] => "bar"
                    [tooDeep] => array *MAX DEPTH*
                    [ding] => "dong"
                    )',
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
        self::assertEquals(
            Abstracter::RECURSION,
            $abstraction[0],
            'Did not find expected recursion'
        );
        $output = $this->debug->output();
        $testA = array('foo' => 'bar');
        $testA['val'] = &$testA;
        $this->debug->log('testA', $testA);
        $output = $this->debug->output();
        self::assertStringContainsString('t_recursion', $output);
        $this->testMethod(
            'log',
            array($testA),
            array(
                'chromeLogger' => array(
                    array(
                        array(
                            'foo' => 'bar',
                            'val' => 'array *RECURSION*',
                        ),
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-37: 56|[{"Type":"LOG"},{"foo":"bar","val":"array *RECURSION*"}]|',
                'html' => static function ($strHtml) {
                    self::assertSelectEquals('.array-inner > li > .t_array > .t_keyword', 'array', true, $strHtml);
                    self::assertSelectEquals('.array-inner > li > .t_array > .t_recursion', '*RECURSION*', true, $strHtml);
                },
                'script' => 'console.log({"foo":"bar","val":"array *RECURSION*"});',
                'streamAnsi' => array('contains' => "    \e[38;5;245m[\e[38;5;83mval\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;45marray \e[38;5;196m*RECURSION*\e[0m"),
                'text' => array('contains' => '    [val] => array *RECURSION*'),
                'wamp' => array(
                    'log',
                    array(
                        array(
                            'foo' => 'bar',
                            'val' => Abstracter::RECURSION,
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
        self::assertSelectCount('.t_recursion', 2, $output, 'Does not contain two recursion types');
    }
}

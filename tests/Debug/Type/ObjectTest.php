<?php

namespace bdk\Test\Debug\Type;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Abstraction\Abstracter
 * @covers \bdk\Debug\Abstraction\Abstraction
 * @covers \bdk\Debug\Abstraction\AbstractObject
 * @covers \bdk\Debug\Abstraction\AbstractObjectConstants
 * @covers \bdk\Debug\Abstraction\AbstractObjectHelper
 * @covers \bdk\Debug\Abstraction\AbstractObjectMethodParams
 * @covers \bdk\Debug\Abstraction\AbstractObjectMethods
 * @covers \bdk\Debug\Abstraction\AbstractObjectProperties
 * @covers \bdk\Debug\Dump\Base
 * @covers \bdk\Debug\Dump\BaseValue
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\Helper
 * @covers \bdk\Debug\Dump\Html\HtmlObject
 * @covers \bdk\Debug\Dump\Html\ObjectConstants
 * @covers \bdk\Debug\Dump\Html\ObjectMethods
 * @covers \bdk\Debug\Dump\Html\ObjectProperties
 * @covers \bdk\Debug\Dump\Html\Value
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\TextAnsi
 * @covers \bdk\Debug\Dump\TextAnsiValue
 * @covers \bdk\Debug\Dump\TextValue
 */
class ObjectTest extends DebugTestFramework
{
    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Code Coverage:
        // clear the method cache so that we inspect the methods during the tests
        $this->helper->setProp('bdk\Debug\Abstraction\AbstractObjectMethods', 'methodCache', array());
    }

    public function providerTestMethod()
    {
        // val, html, text, script

        $text = <<<'EOD'
bdk\Test\Debug\Fixture\Test
  Properties:
    ✨ This object has a __get() method
    (public) propPublic = "redefined in Test (public)"
    (public) propStatic = "I'm Static"
    (public) someArray = array(
        [int] => 123
        [numeric] => "123"
        [string] => "cheese"
        [bool] => true
        [obj] => null
    )
    (protected ✨ magic-read) magicReadProp = "not null"
    (protected) propProtected = "defined only in TestBase (protected)"
    (private) debug = bdk\Debug NOT INSPECTED
    (private) instance = bdk\Test\Debug\Fixture\Test *RECURSION*
    (private excluded) propNoDebug
    (private) propPrivate = "redefined in Test (private) (alternate value via __debugInfo)"
    (🔒 private) testBasePrivate = "defined in TestBase (private)"
    (private) toString = "abracadabra"
    (private) toStrThrow = 0
    (✨ magic excluded) magicProp
    (debug) debugValue = "This property is debug only"
  Methods:
    public: 8
    protected: 1
    private: 1
    magic: 2
EOD;

        $ansi = <<<'EOD'
\e[38;5;250mbdk\Test\Debug\Fixture\\e[0m\e[1mTest\e[22m
    \e[4mProperties:\e[24m
        \e[38;5;250m✨ This object has a __get() method\e[0m
        \e[38;5;250m(public)\e[0m \e[38;5;83mpropPublic\e[0m \e[38;5;130m=\e[0m \e[38;5;250m"\e[0mredefined in Test (public)\e[38;5;250m"\e[0m
        \e[38;5;250m(public)\e[0m \e[38;5;83mpropStatic\e[0m \e[38;5;130m=\e[0m \e[38;5;250m"\e[0mI'm Static\e[38;5;250m"\e[0m
        \e[38;5;250m(public)\e[0m \e[38;5;83msomeArray\e[0m \e[38;5;130m=\e[0m \e[38;5;45marray\e[38;5;245m(\e[0m
            \e[38;5;245m[\e[38;5;83mint\e[38;5;245m]\e[38;5;130m => \e[0m\e[96m123\e[0m
            \e[38;5;245m[\e[38;5;83mnumeric\e[38;5;245m]\e[38;5;130m => \e[0m\e[38;5;250m"\e[96m123\e[38;5;250m"\e[0m
            \e[38;5;245m[\e[38;5;83mstring\e[38;5;245m]\e[38;5;130m => \e[0m\e[38;5;250m"\e[0mcheese\e[38;5;250m"\e[0m
            \e[38;5;245m[\e[38;5;83mbool\e[38;5;245m]\e[38;5;130m => \e[0m\e[32mtrue\e[0m
            \e[38;5;245m[\e[38;5;83mobj\e[38;5;245m]\e[38;5;130m => \e[0m\e[38;5;250mnull\e[0m
        \e[38;5;245m)\e[0m
        \e[38;5;250m(protected ✨ magic-read)\e[0m \e[38;5;83mmagicReadProp\e[0m \e[38;5;130m=\e[0m \e[38;5;250m"\e[0mnot null\e[38;5;250m"\e[0m
        \e[38;5;250m(protected)\e[0m \e[38;5;83mpropProtected\e[0m \e[38;5;130m=\e[0m \e[38;5;250m"\e[0mdefined only in TestBase (protected)\e[38;5;250m"\e[0m
        \e[38;5;250m(private)\e[0m \e[38;5;83mdebug\e[0m \e[38;5;130m=\e[0m \e[38;5;9mNOT INSPECTED\e[0m
        \e[38;5;250m(private)\e[0m \e[38;5;83minstance\e[0m \e[38;5;130m=\e[0m \e[38;5;9m*RECURSION*\e[0m
        \e[38;5;250m(private excluded)\e[0m \e[38;5;83mpropNoDebug\e[0m
        \e[38;5;250m(private)\e[0m \e[38;5;83mpropPrivate\e[0m \e[38;5;130m=\e[0m \e[38;5;250m"\e[0mredefined in Test (private) (alternate value via __debugInfo)\e[38;5;250m"\e[0m
        \e[38;5;250m(🔒 private)\e[0m \e[38;5;83mtestBasePrivate\e[0m \e[38;5;130m=\e[0m \e[38;5;250m"\e[0mdefined in TestBase (private)\e[38;5;250m"\e[0m
        \e[38;5;250m(private)\e[0m \e[38;5;83mtoString\e[0m \e[38;5;130m=\e[0m \e[38;5;250m"\e[0mabracadabra\e[38;5;250m"\e[0m
        \e[38;5;250m(private)\e[0m \e[38;5;83mtoStrThrow\e[0m \e[38;5;130m=\e[0m \e[96m0\e[0m
        \e[38;5;250m(✨ magic excluded)\e[0m \e[38;5;83mmagicProp\e[0m
        \e[38;5;250m(debug)\e[0m \e[38;5;83mdebugValue\e[0m \e[38;5;130m=\e[0m \e[38;5;250m"\e[0mThis property is debug only\e[38;5;250m"\e[0m
    \e[4mMethods:\e[24m
        public\e[38;5;245m:\e[0m \e[96m8\e[0m
        protected\e[38;5;245m:\e[0m \e[96m1\e[0m
        private\e[38;5;245m:\e[0m \e[96m1\e[0m
        magic\e[38;5;245m:\e[0m \e[96m2\e[0m
EOD;
        $ansi = \str_replace('\e', "\e", $ansi);

        $text2 = <<<'EOD'
bdk\Test\Debug\Fixture\Test2
  Properties:
    ✨ This object has a __get() method
    (protected ✨ magic-read) magicReadProp = "not null"
    (✨ magic) magicProp = undefined
  Methods:
    public: 3
    magic: 1
EOD;

        $crate = new \bdk\Debug\Route\WampCrate(\bdk\Debug::getInstance());

        $abs1 = Debug::getInstance()->abstracter->getAbstraction(new \bdk\Test\Debug\Fixture\Test(), 'log');
        $cratedAbs1 = $crate->crate($abs1);
        $cratedAbs1 = $this->helper->crate($cratedAbs1);

        $abs2 = Debug::getInstance()->abstracter->getAbstraction(new \bdk\Test\Debug\Fixture\Test2(), 'log');
        $cratedAbs2 = $crate->crate($abs2);
        $cratedAbs2 = $this->helper->crate($cratedAbs2);


        return array(
            // 0
            array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Test(),
                ),
                array(
                    'entry' => function ($logEntry) {
                        $objAbs = $logEntry['args'][0];
                        $this->assertAbstractionType($objAbs);
                    },
                    'html' => function ($str) {
                        $this->assertStringStartsWith(
                            '<li class="m_log"><div class="t_object" data-accessible="public">'
                            . '<span class="t_string t_stringified" title="__toString()">abracadabra</span>' . "\n"
                            . '<span class="classname" title="PhpDoc Summary' . "\n"
                            . "\n"
                            . 'PhpDoc Description"><span class="namespace">bdk\Test\Debug\Fixture\</span>Test</span>',
                            $str
                        );
                        $this->assertSelectCount('dl.object-inner', 1, $str);

                        // extends
                        $this->assertStringContainsString('<dt>extends</dt>' . "\n" .
                            '<dd class="extends"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestBase</span></dd>', $str);

                        // implements
                        if (\defined('HHVM_VERSION')) {
                            $this->assertStringContainsString(\implode("\n", array(
                                '<dt>implements</dt>',
                                '<dd class="interface"><span class="classname">Stringish</span></dd>',
                                '<dd class="interface"><span class="classname">XHPChild</span></dd>',
                            )), $str);
                        } elseif (PHP_VERSION_ID >= 80000) {
                            $this->assertStringContainsString(\implode("\n", array(
                                '<dt>implements</dt>',
                                '<dd class="interface"><span class="classname">Stringable</span></dd>',
                            )), $str);
                        } else {
                            $this->assertStringNotContainsString('<dt>implements</dt>', $str);
                        }

                        // constants
                        $this->assertStringContainsString(
                            '<dt class="constants">constants</dt>' . "\n"
                            . '<dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier"'
                                . (PHP_VERSION_ID >= 70100
                                    ? ' title="Inherited description"'
                                    : ''
                                ) . '>INHERITED</span> <span class="t_operator">=</span> <span class="t_string">defined in TestBase</span></dd>' . "\n"
                            . '<dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier"'
                                . (PHP_VERSION_ID >= 70100
                                    ? ' title="constant documentation"'
                                    : ''
                                ) . '>MY_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test</span></dd>',
                            $str
                        );

                        // properties
                        $expect = \implode("\n", array(
                            '<dt class="properties">properties <span class="text-muted">(via __debugInfo)</span></dt>',
                            '<dd class="info magic">This object has a <code>__get</code> method</dd>',
                            '<dd class="property public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Public Property.">propPublic</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test (public)</span></dd>',
                            '<dd class="isStatic property public"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">propStatic</span> <span class="t_operator">=</span> <span class="t_string">I\'m Static</span></dd>',
                            '<dd class="property public"><span class="t_modifier_public">public</span> <span class="t_identifier">someArray</span> <span class="t_operator">=</span> <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>',
                            '<ul class="array-inner list-unstyled">',
                            "\t" . '<li><span class="t_key">int</span><span class="t_operator">=&gt;</span><span class="t_int">123</span></li>',
                            "\t" . '<li><span class="t_key">numeric</span><span class="t_operator">=&gt;</span><span class="t_string" data-type-more="numeric">123</span></li>',
                            "\t" . '<li><span class="t_key">string</span><span class="t_operator">=&gt;</span><span class="t_string">cheese</span></li>',
                            "\t" . '<li><span class="t_key">bool</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>',
                            "\t" . '<li><span class="t_key">obj</span><span class="t_operator">=&gt;</span><span class="t_null">null</span></li>',
                            '</ul><span class="t_punct">)</span></span></dd>',
                            '<dd class="inherited magic-read property protected" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_protected">protected</span> <span class="t_modifier_magic-read">magic-read</span> <span class="t_type">bool</span> <span class="t_identifier" title="Read Only!">magicReadProp</span> <span class="t_operator">=</span> <span class="t_string">not null</span></dd>',
                            '<dd class="inherited property protected" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_protected">protected</span> <span class="t_identifier">propProtected</span> <span class="t_operator">=</span> <span class="t_string">defined only in TestBase (protected)</span></dd>',
                            '<dd class="private property"><span class="t_modifier_private">private</span> <span class="t_identifier">debug</span> <span class="t_operator">=</span> <div class="t_object" data-accessible="public"><span class="classname"><span class="namespace">bdk\</span>Debug</span>',
                            '<span class="excluded">NOT INSPECTED</span></div></dd>',
                            '<dd class="private property"><span class="t_modifier_private">private</span> <span class="t_identifier">instance</span> <span class="t_operator">=</span> <div class="t_object" data-accessible="private"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>Test</span>',
                            '<span class="t_recursion">*RECURSION*</span></div></dd>',
                            '<dd class="debuginfo-excluded private property"><span class="t_modifier_private">private</span> <span class="t_identifier">propNoDebug</span> <span class="t_operator">=</span> <span class="t_string">not included in __debugInfo</span></dd>',
                            '<dd class="debuginfo-value private property"><span class="t_modifier_private">private</span> <span class="t_type">string</span> <span class="t_identifier" title="Private Property.">propPrivate</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test (private) (alternate value via __debugInfo)</span></dd>',
                            '<dd class="inherited private private-ancestor property" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_private">private</span> <span>(<i class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestBase</i>)</span> <span class="t_type">string</span> <span class="t_identifier" title="Inherited desc">testBasePrivate</span> <span class="t_operator">=</span> <span class="t_string">defined in TestBase (private)</span></dd>',
                            '<dd class="private property"><span class="t_modifier_private">private</span> <span class="t_identifier">toString</span> <span class="t_operator">=</span> <span class="t_string">abracadabra</span></dd>',
                            '<dd class="private property"><span class="t_modifier_private">private</span> <span class="t_identifier">toStrThrow</span> <span class="t_operator">=</span> <span class="t_int">0</span></dd>',
                            '<dd class="debuginfo-excluded inherited magic property" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_magic">magic</span> <span class="t_type">bool</span> <span class="t_identifier" title="I\'m avail via __get()">magicProp</span></dd>',
                            '<dd class="debuginfo-value property"><span class="t_modifier_debug">debug</span> <span class="t_identifier">debugValue</span> <span class="t_operator">=</span> <span class="t_string">This property is debug only</span></dd>',
                            '<dt class="methods">methods</dt>'
                        ));
                        if (PHP_VERSION_ID >= 80100) {
                            $expect = \str_replace('\'', '&#039;', $expect);
                        }
                        // echo 'expect = ' . $expect . "\n";
                        // echo 'actual = ' . $str . "\n";
                        $this->assertStringContainsString($expect, $str);

                        // methods
                        $expect = \implode("\n", array(
                            '<dt class="methods">methods</dt>',
                            '<dd class="info magic">This object has a <code>__call</code> method</dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Constructor',
                            '',
                            'Constructor description">__construct</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="value __toString will return;">$toString</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">abracadabra</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_type">int</span> <span class="t_parameter-name" title="0: don\'t, 1: throw, 2: throw &amp; catch">$toStrThrow</span> <span class="t_operator">=</span> <span class="t_int t_parameter-default">0</span></span><span class="t_punct">)</span></dd>',
                            '<dd class="inherited method public" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="t_identifier" title="call magic method">__call</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="Method being called">$name</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_type">array</span> <span class="t_parameter-name" title="Arguments passed">$args</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">mixed</span></dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="magic method">__debugInfo</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span title="property=&gt;value array"><span class="t_type">array</span></span></dd>',
                            '<dd class="inherited method public" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="t_identifier" title="get magic method">__get</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="what we\'re getting">$key</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">mixed</span></dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="toString magic method">__toString</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">string</span><br />',
                                '<span class="t_string">abracadabra</span></dd>',
                            '<dd class="isDeprecated isFinal method public" data-deprecated-desc="this method is bad and should feel bad"><span class="t_modifier_final">final</span> <span class="t_modifier_public">public</span> <span class="t_identifier" title="This method is public">methodPublic</span><span class="t_punct">(</span><span class="parameter"><span class="t_type"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>SomeClass</span></span> <span class="t_parameter-name" title="first param',
                                'two-line description!">$param1</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_type">array</span> <span class="t_parameter-name" title="second param">$param2</span> <span class="t_operator">=</span> <span class="t_array t_parameter-default"><span class="t_keyword">array</span><span class="t_punct">()</span></span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dd class="inherited method public" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="t_identifier">testBasePublic</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                            '<dd class="inherited isStatic method public" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">testBaseStatic</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                            '<dd class="method protected"><span class="t_modifier_protected">protected</span> <span class="t_identifier" title="This method is protected">methodProtected</span><span class="t_punct">(</span><span class="parameter"><span class="t_type"><span class="classname"><span class="namespace">bdk\Debug\Abstraction\</span>Abstraction</span><span class="t_punct">[]</span></span> <span class="t_parameter-name" title="first param">$param1</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dd class="method private"><span class="t_modifier_private">private</span> <span class="t_identifier" title="This method is private">methodPrivate</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">mixed</span> <span class="t_parameter-name" title="first param (passed by ref)">&amp;$param1</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_type"><span class="classname"><span class="namespace">bdk\PubSub\</span>Event</span><span class="t_punct">[]</span></span> <span class="t_parameter-name" title="second param (passed by ref)">&amp;$param2</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_type">bool</span> <span class="t_parameter-name" title="3rd param not in method signature">...$param3</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dd class="inherited magic method" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_magic">magic</span> <span class="t_identifier" title="I\'m a magic method">presto</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$foo</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_type">int</span> <span class="t_parameter-name">$int</span> <span class="t_operator">=</span> <span class="t_int t_parameter-default">1</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_parameter-name">$bool</span> <span class="t_operator">=</span> <span class="t_bool t_parameter-default" data-type-more="true">true</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_parameter-name">$null</span> <span class="t_operator">=</span> <span class="t_null t_parameter-default">null</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dd class="inherited isStatic magic method" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_magic">magic</span> <span class="t_modifier_static">static</span> <span class="t_identifier" title="I\'m a static magic method">prestoStatic</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name">$noDefault</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_parameter-name">$arr</span> <span class="t_operator">=</span> <span class="t_array t_parameter-default"><span class="t_keyword">array</span><span class="t_punct">()</span></span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_parameter-name">$opts</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">array(\'a\'=&gt;\'ay\',\'b\'=&gt;\'bee\')</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_parameter-name">$val</span> <span class="t_operator">=</span> <span class="t_const t_parameter-default" title="value: &quot;defined in TestBase&quot;"><span class="classname">self</span><span class="t_operator">::</span><span class="t_identifier">MY_CONSTANT</span></span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dt>phpDoc</dt>',
                        ));
                        if (PHP_VERSION_ID >= 80100) {
                            $expect = \str_replace('\'', '&#039;', $expect);
                        }

                        // echo 'expect = ' . $expect . "\n";
                        // echo 'actual = ' . $str . "\n";
                        $this->assertStringContainsString($expect, $str);

                        // phpdoc
                        $this->assertStringContainsString(\implode("\n", array(
                            '<dt>phpDoc</dt>',
                            '<dd class="phpdoc phpdoc-link"><span class="phpdoc-tag">link</span><span class="t_operator">:</span> <a href="http://www.bradkent.com/php/debug" target="_blank">PHPDebugConsole Homepage</a></dd>',
                            '</dl>',
                        )), $str);
                    },
                    'script' => 'console.log({"___class_name":"bdk\\\Test\\\Debug\\\Fixture\\\Test","(public) propPublic":"redefined in Test (public)","(public) propStatic":"I\'m Static","(public) someArray":{"int":123,"numeric":"123","string":"cheese","bool":true,"obj":null},"(protected ✨ magic-read) magicReadProp":"not null","(protected) propProtected":"defined only in TestBase (protected)","(private) debug":"(object) bdk\\\Debug NOT INSPECTED","(private) instance":"(object) bdk\\\Test\\\Debug\\\Fixture\\\Test *RECURSION*","(private excluded) propNoDebug":"not included in __debugInfo","(private) propPrivate":"redefined in Test (private) (alternate value via __debugInfo)","(🔒 private) testBasePrivate":"defined in TestBase (private)","(private) toString":"abracadabra","(private) toStrThrow":0,"(✨ magic excluded) magicProp":undefined,"(debug) debugValue":"This property is debug only"});',
                    'streamAnsi' => $ansi,
                    'text' => $text,
                    'wamp' => array(
                        'log',
                        array(
                            $cratedAbs1,
                        ),
                    ),
                )
            ),
            // 1
            array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Test('This is the song that never ends.  Yes, it goes on and on my friend.  Some people started singing it not knowing what it was.  And they\'ll never stop singing it forever just because.  This is the song that never ends...'),
                    Debug::meta('cfg', 'stringMaxLen', 150),   // this will store abstracted/truncated value...     test that "more bytes" is calculated correctly
                ),
                array(
                    'html' => function ($str) {
                        $this->assertStringContainsString('<span class="t_string t_string_trunc t_stringified" title="__toString()">This is the song that never ends.  Yes, it goes on and on my friend.  Some people started singing it&hellip; <i>(119 more bytes)</i></span>', $str);
                    }
                ),
            ),
            // 2
            array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Test2(),
                ),
                array(
                    'html' => function ($str) {
                        // properties
                        $expect = \implode("\n", array(
                            '<dt class="properties">properties</dt>',
                            '<dd class="info magic">This object has a <code>__get</code> method</dd>',
                            '<dd class="inherited magic-read property protected" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_protected">protected</span> <span class="t_modifier_magic-read">magic-read</span> <span class="t_type">bool</span> <span class="t_identifier" title="Read Only!">magicReadProp</span> <span class="t_operator">=</span> <span class="t_string">not null</span></dd>',
                            '<dd class="inherited magic property" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_magic">magic</span> <span class="t_type">bool</span> <span class="t_identifier" title="I\'m avail via __get()">magicProp</span></dd>',
                        ));
                        if (PHP_VERSION_ID >= 80100) {
                            $expect = \str_replace('\'', '&#039;', $expect);
                        }
                        $this->assertStringContainsString($expect, $str);

                        // methods
                        $constName = \defined('HHVM_VERSION')
                            ? '<span class="classname">\\bdk\\Test\\\Debug\\\Test2Base</span><span class="t_operator">::</span><span class="t_identifier">WORD</span>'
                            : '<span class="classname">self</span><span class="t_operator">::</span><span class="t_identifier">WORD</span>';

                        $expect = \implode("\n", array(
                            '<dt class="methods">methods</dt>',
                            '<dd class="info magic">This object has a <code>__call</code> method</dd>',
                            '<dd class="inherited method public" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_public">public</span> <span class="t_identifier" title="magic method">__call</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="Method being called">$name</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_type">array</span> <span class="t_parameter-name" title="Arguments passed">$args</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">mixed</span></dd>',
                            '<dd class="inherited method public" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_public">public</span> <span class="t_identifier" title="get magic method">__get</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="what we\'re getting">$key</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">mixed</span></dd>',
                            \version_compare(PHP_VERSION, '5.4.6', '>=')
                                ? '<dd class="inherited method public" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Test constant as default value">constDefault</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="only php &gt;= 5.4.6 can get the name of the constant used">$param</span> <span class="t_operator">=</span> <span class="t_const t_parameter-default" title="value: &quot;bird&quot;">' . $constName . '</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>'
                                : '<dd class="inherited method public" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Test constant as default value">constDefault</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="only php &gt;= 5.4.6 can get the name of the constant used">$param</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">bird</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dd class="inherited magic method" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_magic">magic</span> <span class="t_identifier" title="test constant as param">methConstTest</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$mode</span> <span class="t_operator">=</span> <span class="t_const t_parameter-default" title="value: &quot;bird&quot;"><span class="classname">self</span><span class="t_operator">::</span><span class="t_identifier">WORD</span></span></span><span class="t_punct">)</span></dd>',
                            '</dl>',
                        ));
                        if (PHP_VERSION_ID >= 80100) {
                            $expect = \str_replace('\'', '&#039;', $expect);
                        }

                        // echo 'expect = ' . $expect . "\n";
                        // echo 'str = ' . $str . "\n";
                        $this->assertStringContainsString($expect, $str);
                    },
                    'script' => 'console.log({"___class_name":"bdk\\\Test\\\Debug\\\Fixture\\\Test2","(protected ✨ magic-read) magicReadProp":"not null","(✨ magic) magicProp":undefined});',
                    'text' => $text2,
                    'wamp' => array(
                        'log',
                        array(
                            $cratedAbs2,
                        ),
                    ),
                ),
            ),
            'phpDocCollectFalse' => array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Test(),
                    Debug::meta('cfg', 'phpDocCollect', false),
                ),
                array(
                    'entry' => function (LogEntry $logEntry) {
                        $objAbs = $logEntry['args'][0];
                        $this->assertSame(
                            array('desc' => null, 'summary' => null),
                            \array_intersect_key($objAbs['phpDoc'], \array_flip(array('desc','summary')))
                        );
                        foreach ($objAbs['constants'] as $const) {
                            $this->assertNull($const['desc']);
                        }
                        foreach ($objAbs['properties'] as $name => $prop) {
                            $this->assertNull($prop['desc']);
                        }
                        foreach ($objAbs['methods'] as $name => $method) {
                            $this->assertSame(
                                array('desc' => null, 'summary' => null),
                                \array_intersect_key($method['phpDoc'], \array_flip(array('desc','summary')))
                            );
                        }
                    },
                ),
            ),
            'phpDocOutputFalse' => array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Test(),
                    Debug::meta('cfg', 'phpDocOutput', false),
                ),
                array(
                    'entry' => function (LogEntry $logEntry) {
                        // quick confirm that was collected
                        $objAbs = $logEntry['args'][0];
                        $this->assertSame(
                            array(
                                'desc' => 'PhpDoc Description',
                                'summary' => 'PhpDoc Summary',
                            ),
                            \array_intersect_key($objAbs['phpDoc'], \array_flip(array('desc','summary')))
                        );
                    },
                    'html' => function ($html, LogEntry $logEntry) {
                        \preg_match_all('/title="([^"]+)"/s', $html, $matches);
                        $matches = \array_diff($matches[1], array(
                            '__toString()',
                            'value: &quot;defined in TestBase&quot;',
                        ));
                        $this->assertEmpty($matches, 'Html should not contain phpDoc summary & descriptions');
                    }
                ),
            ),
            'methodCollectFalse' => array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Test(),
                    Debug::meta('cfg', array(
                        'methodCollect' => false,
                    )),
                ),
                array(
                    'entry' => function ($logEntry) {
                        $objAbs = $logEntry['args'][0];
                        $values = $objAbs->getValues();
                        $this->assertFalse(($values['cfgFlags'] & AbstractObject::METHOD_COLLECT) === AbstractObject::METHOD_COLLECT);
                        $this->assertTrue(($values['cfgFlags'] & AbstractObject::METHOD_OUTPUT) === AbstractObject::METHOD_OUTPUT);
                    },
                    'html' => function ($str) {
                        $this->assertStringContainsString(\implode("\n", array(
                            '<dt class="methods">methods <i>not collected</i></dt>',
                            '<dt>phpDoc</dt>',
                        )), $str);
                    },
                    'streamAnsi' => function ($str) {
                        $containsMethods = preg_match('/methods/i', $str) === 1;
                        $this->assertFalse($containsMethods, 'Output should not contain methods');
                    },
                    'text' => function ($str) {
                        $containsMethods = preg_match('/methods/i', $str) === 1;
                        $this->assertFalse($containsMethods, 'Output should not contain methods');
                    }
                ),
            ),
            // 3
            array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Utility\PhpDocExtends(),
                ),
                array(
                    'html' => function ($html) {
                        $expect = '<dt class="constants">constants</dt>' . "\n"
                            . '<dd class="constant public"><span class="t_modifier_public">public</span> <span class="t_identifier"'
                            . (PHP_VERSION_ID >= 70100
                                ? ' title="PhpDocImplements summary"'
                                : ''
                            )
                            .'>SOME_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">never change</span></dd>' . "\n"
                            . '<dt class="properties">properties</dt>' . "\n"
                            . '<dd class="property public"><span class="t_modifier_public">public</span> <span class="t_type">string</span> <span class="t_identifier" title="$someProperty summary: desc">someProperty</span> <span class="t_operator">=</span> <span class="t_string">St. James Place</span></dd>' . "\n"
                            . '<dd class="magic property"><span class="t_modifier_magic">magic</span> <span class="t_type">bool</span> <span class="t_identifier" title="I\'m avail via __get()">magicProp</span></dd>' . "\n"
                            . '<dd class="magic-read property"><span class="t_modifier_magic-read">magic-read</span> <span class="t_type">bool</span> <span class="t_identifier" title="Read Only!">magicReadProp</span></dd>' . "\n"
                            . '<dt class="methods">methods</dt>' . "\n"
                            . '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="SomeInterface summary' . "\n"
                            . '' . "\n"
                            . 'SomeInterface description">someMethod</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Utility\bdk\Test\Debug\Fixture\</span>SomeInterface</span></span></dd>' . "\n"
                            . '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="PhpDocImplements summary' . "\n"
                            . '' . "\n"
                            . 'PhpDocImplements desc">someMethod2</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>' . "\n"
                            . '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="PhpDocExtends summary' . "\n"
                            . '' . "\n"
                            . 'PhpDocExtends desc / PhpDocImplements desc">someMethod3</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>';
                        if (PHP_VERSION_ID >= 80100) {
                            $expect = \str_replace('\'', '&#039;', $expect);
                        }
                        // echo 'expect = ' . $expect . "\n";
                        // echo 'html = ' . $html . "\n";
                        $this->assertStringContainsString($expect, $html);
                    },
                ),
            ),

            'phpStanTypes' => array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\ArrayDocs(),
                ),
                array(
                    'html' => function ($html) {
                        // echo 'html = ' . $html . "\n";
                        $expect = \trim(\preg_replace('/^\s+/m', '', '
                            <li class="m_log"><div class="t_object" data-accessible="public"><span class="classname" title="&quot;Array Shapes&quot; and &quot;General Arrays&quot;"><span class="namespace">bdk\Test\Debug\Fixture\</span>ArrayDocs</span>
                            <dl class="object-inner">
                            <dt class="properties">properties</dt>
                            <dd class="property public"><span class="t_modifier_public">public</span> <span class="t_type">non-empty-array</span><span class="t_punct">&lt;</span><span class="t_type">string</span><span class="t_punct">,</span> <span class="t_type">array</span><span class="t_punct">&lt;</span><span class="t_type">int</span><span class="t_punct">,</span> <span class="t_type">int</span><span class="t_punct">|</span><span class="t_type">string</span><span class="t_punct">&gt;</span><span class="t_punct">|</span><span class="t_type">int</span><span class="t_punct">|</span><span class="t_type">string</span><span class="t_punct">&gt;</span><span class="t_type"><span class="t_punct">[]</span></span> <span class="t_identifier" title="General Description">general</span> <span class="t_operator">=</span> <span class="t_null">null</span></dd>
                            <dd class="property public"><span class="t_modifier_public">public</span> <span class="t_type">null</span><span class="t_punct">|</span><span class="t_type"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>"literal"</span></span><span class="t_punct">|</span><span class="t_type"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>123</span></span> <span class="t_identifier" title="Union test">literal</span> <span class="t_operator">=</span> <span class="t_null">null</span></dd>
                            <dd class="property public"><span class="t_modifier_public">public</span> <span class="t_type">array</span><span class="t_punct">{</span><span class="t_string">name</span><span class="t_punct">:</span> <span class="t_type">string</span><span class="t_punct">,</span> <span class="t_string">value</span><span class="t_punct">:</span> <span class="t_type">positive-int</span><span class="t_punct">|</span><span class="t_type">string</span><span class="t_punct">,</span> <span class="t_string">foo</span><span class="t_punct">:</span> <span class="t_type"><span class="classname">bar</span></span><span class="t_punct">,</span> <span class="t_string">number</span><span class="t_punct">:</span> <span class="t_type">42</span><span class="t_punct">,</span> <span class="t_string">string</span><span class="t_punct">:</span> <span class="t_string t_type">theory</span><span class="t_punct">}</span> <span class="t_identifier" title="Shape Description">shape</span> <span class="t_operator">=</span> <span class="t_null">null</span></dd>
                            <dt class="methods">methods</dt>
                            <dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Method description">myMethod</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">int</span><span class="t_punct">|</span><span class="t_type">string</span> <span class="t_parameter-name" title="I&#039;m a description">$foo</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>
                            <dt>phpDoc</dt>
                            <dd class="phpdoc phpdoc-link"><span class="phpdoc-tag">link</span><span class="t_operator">:</span> <a href="https://phpstan.org/writing-php-code/phpdoc-types#array-shapes" target="_blank">https://phpstan.org/writing-php-code/phpdoc-types#array-shapes</a></dd>
                            <dd class="phpdoc phpdoc-link"><span class="phpdoc-tag">link</span><span class="t_operator">:</span> <a href="https://phpstan.org/writing-php-code/phpdoc-types#general-arrays" target="_blank">https://phpstan.org/writing-php-code/phpdoc-types#general-arrays</a></dd>
                            </dl>
                            </div></li>
                        '));
                        if (PHP_VERSION_ID < 80100) {
                            $expect = \str_replace('&#039;', '\'', $expect);
                        }
                        // echo 'expect = ' . $expect . "\n";
                        // echo 'actual = ' . $html . "\n";
                        $this->assertStringContainsString($expect, $html);
                    },
                ),
            ),

            'sortByName' => array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Test(),
                    Debug::meta('cfg', 'objectSort', 'name'),
                ),
                array(
                    'entry' => function (LogEntry $entry) {
                        // echo print_r($entry->getValues(), true) . "\n";
                        $this->assertSame(array(
                            'INHERITED',
                            'MY_CONSTANT',
                        ), \array_keys($entry['args'][0]['constants']));
                        $this->assertSame(array(
                            'debug',
                            'debugValue',
                            'instance',
                            'magicProp',
                            'magicReadProp',
                            'propNoDebug',
                            'propPrivate',
                            'propProtected',
                            'propPublic',
                            'propStatic',
                            'someArray',
                            'testBasePrivate',
                            'toString',
                            'toStrThrow',
                        ), \array_keys($entry['args'][0]['properties']));
                        $this->assertSame(array(
                            '__construct',
                            '__call',
                            '__debugInfo',
                            '__get',
                            '__toString',
                            'methodPrivate',
                            'methodProtected',
                            'methodPublic',
                            'presto',
                            'prestoStatic',
                            'testBasePublic',
                            'testBaseStatic',
                        ), \array_keys($entry['args'][0]['methods']));
                    },
                ),
            ),
        );
    }

    /**
     * v 1.0 = fatal error
     *
     * @return void
     */
    public function testDereferenceObject()
    {
        $testVal = 'success A';
        $testO = new \bdk\Test\Debug\Fixture\Test();
        $testO->propPublic = &$testVal;
        $this->debug->log('test_o', $testO);
        $testVal = 'success B';
        $this->debug->log('test_o', $testO);
        $testVal = 'fail';
        $output = $this->debug->output();
        $this->assertStringContainsString('success A', $output);
        $this->assertStringContainsString('success B', $output);
        $this->assertStringNotContainsString('fail', $output);
        $this->assertSame('fail', $testO->propPublic);   // prop should be 'fail' at this point
    }

    /**
     * Test
     *
     * @return void
     */
    public function testAbstraction()
    {
        // mostly tested via logTest, infoTest, warnTest, errorTest....
        // test object inheritance
        $test = new \bdk\Test\Debug\Fixture\Test();
        $abs = $this->debug->abstracter->getAbstraction($test);

        $this->assertSame('object', $abs['type']);
        $this->assertSame('bdk\Test\Debug\Fixture\Test', $abs['className']);
        $this->assertSame(
            array('bdk\Test\Debug\Fixture\TestBase'),
            $abs['extends']
        );
        $expect = array();
        if (\defined('HHVM_VERSION')) {
            $expect = array('Stringish','XHPChild'); // hhvm-3.25 has XHPChild
        } elseif (PHP_VERSION_ID >= 80000) {
            $expect = array('Stringable');
        }
        $this->assertSame($expect, $abs['implements']);
        $this->assertSame(
            array(
                'INHERITED' => array(
                    'attributes' => array(),
                    'desc' => PHP_VERSION_ID >= 70100
                        ? 'Inherited description'
                        : null,
                    'isFinal' => false,
                    'value' => 'defined in TestBase',
                    'visibility' => 'public',
                ),
                'MY_CONSTANT' => array(
                    'attributes' => array(),
                    'desc' => PHP_VERSION_ID >= 70100
                        ? 'constant documentation'
                        : null,
                    'isFinal' => false,
                    'value' => 'redefined in Test',
                    'visibility' => 'public',
                ),
            ),
            $abs['constants']
        );
        $this->assertArraySubset(
            array(
                'desc' => 'PhpDoc Description',
                'summary' => 'PhpDoc Summary',
            ),
            $abs['phpDoc']
        );
        $this->assertTrue($abs['viaDebugInfo']);

        //    Properties
        // $this->assertArrayNotHasKey('propNoDebug', $abs['properties']);
        $this->assertTrue($abs['properties']['propNoDebug']['debugInfoExcluded']);
        $this->assertTrue($abs['properties']['debug']['value']['isExcluded']);
        $this->assertTrue($abs['properties']['instance']['value']['isRecursion']);
        $this->assertArraySubset(
            array(
                'attributes' => array(),
                'isPromoted' => false,
                'originallyDeclared' => 'bdk\Test\Debug\Fixture\TestBase',
                'overrides' => 'bdk\Test\Debug\Fixture\TestBase',
                'value' => 'redefined in Test (public)',
                'valueFrom' => 'value',
                'visibility' => 'public',
            ),
            $abs['properties']['propPublic']
        );
        $this->assertArraySubset(
            array(
                'isPromoted' => false,
                'valueFrom' => 'value',
                'visibility' => 'public',
                // 'value' => 'This property is debug only',
            ),
            $abs['properties']['someArray']
        );
        $this->assertArraySubset(
            array(
                'isPromoted' => false,
                'visibility' => 'protected',
                'value' => 'defined only in TestBase (protected)',
                'inheritedFrom' => 'bdk\Test\Debug\Fixture\TestBase',
                'overrides' => null,
                'originallyDeclared' => 'bdk\Test\Debug\Fixture\TestBase',
                'valueFrom' => 'value',
            ),
            $abs['properties']['propProtected']
        );
        $this->assertArraySubset(
            array(
                'attributes' => array(),
                'inheritedFrom' => null,
                'isPromoted' => false,
                'originallyDeclared' => 'bdk\Test\Debug\Fixture\TestBase',
                'overrides' => 'bdk\Test\Debug\Fixture\TestBase',
                'value' => 'redefined in Test (private) (alternate value via __debugInfo)',
                'valueFrom' => 'debugInfo',
                'visibility' => 'private',
            ),
            $abs['properties']['propPrivate']
        );
        $this->assertArraySubset(
            array(
                'attributes' => array(),
                'inheritedFrom' => 'bdk\Test\Debug\Fixture\TestBase',
                'isPromoted' => false,
                'originallyDeclared' => null,
                'overrides' => null,
                'value' => 'defined in TestBase (private)',
                'valueFrom' => 'value',
                'visibility' => 'private',
            ),
            $abs['properties']['testBasePrivate']
        );
        $this->assertArraySubset(
            array(
                'attributes' => array(),
                'isPromoted' => false,
                'value' => 'This property is debug only',
                'valueFrom' => 'debugInfo',
            ),
            $abs['properties']['debugValue']
        );

        //    Methods
        $this->assertArrayNotHasKey('testBasePrivate', $abs['methods']);
        $this->assertTrue($abs['methods']['methodPublic']['isDeprecated']);
        $this->assertSame(
            array(
                'attributes' => array(),
                'implements' => null,
                'inheritedFrom' => null,
                'isAbstract' => false,
                'isDeprecated' => true,
                'isFinal' => true,
                'isStatic' => false,
                'params' => array(
                    array(
                        'attributes' => array(),
                        'defaultValue' => Abstracter::UNDEFINED,
                        'desc' => 'first param' . "\n" . 'two-line description!',
                        'isOptional' => false,
                        'isPromoted' => false,
                        'name' => '$param1',
                        'type' => 'bdk\\Test\\Debug\\Fixture\\SomeClass',
                    ),
                    array(
                        'attributes' => array(),
                        'defaultValue' => array(),
                        'desc' => 'second param',
                        'isOptional' => true,
                        'isPromoted' => false,
                        'name' => '$param2',
                        'type' => 'array',
                    ),
                ),
                'phpDoc' => array(
                    'deprecated' => array(
                        array(
                            'desc' => 'this method is bad and should feel bad',
                        ),
                    ),
                    'desc' => null,
                    'summary' => 'This method is public',
                ),
                'return' => array(
                    'desc' => null,
                    'type' => 'void',
                ),
                'visibility' => 'public',
            ),
            $abs['methods']['methodPublic']
        );
    }

    /**
     * Test Anonymous classes
     *
     * @requires PHP >= 7.0
     */
    public function testAnonymousClass()
    {
        if (PHP_VERSION_ID < 70000) {
            // @requires not working in 4.8.36
            $this->markTestSkipped('anonymous classes are a php 7.0 thing');
        }
        $filepath = TEST_DIR . '/Debug/Fixture/Anonymous.php';
        $line = 6;
        $anonymous = require $filepath;
        $this->testMethod(
            'log',
            array(
                'anonymous',
                $anonymous['stdClass'],
            ),
            array(
                // 'entry' => $entry,
                'chromeLogger' => array(
                    array(
                        'anonymous',
                        array(
                            '___class_name' => 'stdClass@anonymous',
                            '(debug) file' => $filepath,
                            '(debug) line' => $line,
                        ),
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-%d: %d|[{"Label":"anonymous","Type":"LOG"},{"___class_name":"stdClass@anonymous","(debug) file":"' . $filepath . '","(debug) line":' . $line . '}]|',
                'html' => '<li class="m_log"><span class="no-quotes t_string">anonymous</span> = <div class="t_object" data-accessible="public"><span class="classname">stdClass@anonymous</span>
                    <dl class="object-inner">
                    <dt>extends</dt>
                        <dd class="extends"><span class="classname">stdClass</span></dd>
                    <dt class="properties">properties</dt>
                        <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">string</span> <span class="t_identifier">file</span> <span class="t_operator">=</span> <span class="t_string">' . $filepath . '</span></dd>
                        <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">int</span> <span class="t_identifier">line</span> <span class="t_operator">=</span> <span class="t_int">' . $line . '</span></dd>
                    <dt class="methods">methods</dt>
                        <dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Anonymous method">myMethod</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>
                    </dl>
                    </div></li>',
                'script' => 'console.log("anonymous",{"___class_name":"stdClass@anonymous","(debug) file":"' . $filepath . '","(debug) line":' . $line . '});',
                'text' => 'anonymous = stdClass@anonymous
                    Properties:
                    (debug) file = "%s/PHPDebugConsole/tests/Debug/Fixture/Anonymous.php"
                    (debug) line = %d
                    Methods:
                    public: 1',
                // 'wamp' => $entry,
            )
        );
        // anonymous callable tested via ArrayTest
    }

    public function testDom()
    {
        $domDoc = new \DOMDocument();
        $domDoc->loadXML('<node>content</node>');
        $domNode = $domDoc->getElementsByTagName('node')->item(0);
        $this->testMethod(
            'log',
            array(
                $domDoc,
                $domNode,
            ),
            array(
                'entry' => function (LogEntry $entry) {
                    $arg0 = $entry['args'][0];
                    $this->assertSame('DOMDocument', $arg0['className']);

                    $arg1 = $entry['args'][1];
                    $this->assertSame('DOMElement', $arg1['className']);
                    // echo json_encode($this->helper->logEntryToArray($entry), JSON_PRETTY_PRINT) . "\n";
                },
            )
        );
    }

    /**
     * Test Promoted Params
     *
     * @requires PHP >= 8.0
     */
    public function testPromotedParam()
    {
        if (PHP_VERSION_ID < 80000) {
            // @requires not working in 4.8.36
            $this->markTestSkipped('attributes classes are a php 8.0 thing');
        }
        $test = new \bdk\Test\Debug\Fixture\Php80(42);
        $this->testMethod(
            'log',
            array(
                $test,
            ),
            array(
                'entry' => function (LogEntry $logEntry) {
                    $abs = $logEntry['args'][0];
                    $this->assertTrue($abs['properties']['arg1']['isPromoted']);
                    $this->assertTrue($abs['methods']['__construct']['params'][0]['isPromoted']);
                    $this->assertSame('Attributed & promoted param', $abs['properties']['arg1']['desc']);
                },
                'html' => function ($html) {
                    $propExpect = \str_replace('\\', '\\\\', '<dd class="isPromoted property public" data-attributes="[{&quot;name&quot;:&quot;bdk\\Test\\Debug\\Fixture\\ExampleParamAttribute&quot;,&quot;arguments&quot;:[]}]"><span class="t_modifier_public">public</span> <span class="t_type">int</span> <span class="t_identifier" title="Attributed &amp; promoted param">arg1</span> <span class="t_operator">=</span> <span class="t_int">42</span></dd>');
                    $methExpect = \str_replace('\\', '\\\\', '<span class="isPromoted parameter" data-attributes="[{&quot;name&quot;:&quot;bdk\\Test\\Debug\\Fixture\\ExampleParamAttribute&quot;,&quot;arguments&quot;:[]}]"><span class="t_type">int</span> <span class="t_parameter-name" title="Attributed &amp; promoted param">$arg1</span></span>');
                    $attrExpect = '<dt class="attributes">attributes</dt>' . "\n"
                        . '<dd class="attribute"><span class="classname"><span class="namespace">bdk\\Test\\Debug\\Fixture\\</span>ExampleClassAttribute</span><span class="t_punct">(</span><span class="t_string">foo</span><span class="t_punct">,</span> <span class="t_int">' . PHP_VERSION_ID . '</span><span class="t_punct">,</span> <span class="t_parameter-name">name</span><span class="t_punct t_colon">:</span><span class="t_string">bar</span><span class="t_punct">)</span></dd>';
                    $this->assertStringContainsString($propExpect, $html);
                    $this->assertStringContainsString($methExpect, $html);
                    $this->assertStringContainsString($attrExpect, $html);
                },
            )
        );
    }

    /**
     * Test Php 8.1 features
     *
     * @requires PHP >= 8.1
     */
    public function testPhp81()
    {
        if (PHP_VERSION_ID < 80100) {
            // @requires not working in 4.8.36
            $this->markTestSkipped('Test requires Php >= 8.1');
        }
        $test = new \bdk\Test\Debug\Fixture\Php81(42);
        $this->testMethod(
            'log',
            array(
                $test,
            ),
            array(
                'entry' => function (LogEntry $logEntry) {
                    $abs = $logEntry['args'][0];
                    $this->assertTrue($abs['properties']['title']['isReadOnly']);
                    $this->assertTrue($abs['constants']['FINAL_CONST']['isFinal']);

                    // $this->assertTrue($abs['methods']['__construct']['params'][0]['isPromoted']);
                    // $this->assertSame('Attributed & promoted param', $abs['properties']['arg1']['desc']);
                },
                'html' => function ($html) {
                    $constExpect = '<dd class="constant final public"><span class="t_modifier_public">public</span> <span class="t_modifier_final">final</span> <span class="t_identifier">FINAL_CONST</span> <span class="t_operator">=</span> <span class="t_string">foo</span></dd>';
                    $this->assertStringContainsString($constExpect, $html);

                    $propExpect = '<dd class="isPromoted isReadOnly property public"><span class="t_modifier_public">public</span> <span class="t_modifier_readonly">readonly</span> <span class="t_type">string</span> <span class="t_identifier">title</span> <span class="t_operator">=</span> <span class="t_string" data-type-more="numeric">42</span></dd>';
                    $this->assertStringContainsString($propExpect, $html);
                },
            )
        );
    }

    /**
     * Test Attributes
     *
     * @requires PHP >= 8.0
     */
    public function testAttributes()
    {
        if (PHP_VERSION_ID < 80000) {
            // @requires not working in 4.8.36
            $this->markTestSkipped('attributes classes are a php 8.0 thing');
        }
        $test = new \bdk\Test\Debug\Fixture\Php80(42);
        $this->testMethod(
            'log',
            array(
                $test,
            ),
            array(
                'entry' => function (LogEntry $logEntry) {
                    $abs = $logEntry['args'][0];
                    $attribNamespace = 'bdk\\Test\\Debug\\Fixture\\';
                    $this->assertSame(
                        array(
                            array(
                                'name' =>  $attribNamespace . 'ExampleClassAttribute',
                                'arguments' => array(
                                    'foo',
                                    PHP_VERSION_ID,
                                    'name' => 'bar',
                                ),
                            ),
                        ),
                        $abs['attributes']
                    );
                    $this->assertSame(
                        array(
                            array(
                                'name' =>  $attribNamespace . 'ExampleConstAttribute',
                                'arguments' => array(),
                            ),
                        ),
                        $abs['constants']['FOO']['attributes']
                    );
                    $this->assertSame(
                        array(
                            array(
                                'name' =>  $attribNamespace . 'ExampleMethodAttribute',
                                'arguments' => array(),
                            ),
                        ),
                        $abs['methods']['__construct']['attributes']
                    );
                    $this->assertSame(
                        array(
                            array(
                                'name' =>  $attribNamespace . 'ExampleParamAttribute',
                                'arguments' =>  array(),
                            ),
                        ),
                        $abs['methods']['__construct']['params'][0]['attributes']
                    );
                    $this->assertSame(
                        // this property (and it's attributes came via parameter promotion)
                        array(
                            array(
                                'name' =>  $attribNamespace . 'ExampleParamAttribute',
                                'arguments' =>  array(),
                            ),
                        ),
                        $abs['properties']['arg1']['attributes']
                    );
                    $this->assertSame(
                        array(
                            array(
                                'name' =>  $attribNamespace . 'ExamplePropAttribute',
                                'arguments' =>  array(),
                            ),
                        ),
                        $abs['properties']['id']['attributes']
                    );
                },
                /*
                'html' => function ($html) {
                    // @todo
                },
                */
            )
        );
    }

    public function testFinal()
    {
        $this->testMethod(
            'log',
            array(
                new \bdk\Test\Debug\Fixture\TestFinal(),
            ),
            array(
                'entry' => function (LogEntry $logEntry) {
                    $this->assertTrue($logEntry['args'][0]['isFinal']);
                },
                'html' => array(
                    'contains' => '<dt class="t_modifier_final">final</dt>' . "\n",
                ),
            )
        );
    }

    public function testVariadic()
    {
        if (\version_compare(PHP_VERSION, '5.6', '<')) {
            return;
        }
        $testVar = new \bdk\Test\Debug\Fixture\TestVariadic();
        $abs = $this->debug->abstracter->getAbstraction($testVar);
        $this->assertSame('...$moreParams', $abs['methods']['methodVariadic']['params'][1]['name']);
    }

    public function testVariadicByReference()
    {
        if (\version_compare(PHP_VERSION, '5.6', '<')) {
            return;
        }
        if (\defined('HHVM_VERSION')) {
            return;
        }
        $testVarByRef = new \bdk\Test\Debug\Fixture\TestVariadicByReference();
        $abs = $this->debug->abstracter->getAbstraction($testVarByRef);
        $this->assertSame('&...$moreParams', $abs['methods']['methodVariadicByReference']['params'][1]['name']);
    }

    public function testCollectPropertyValues()
    {
        $callable = function (Abstraction $abs) {
            $abs['collectPropertyValues'] = false;
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);
        $abs = $this->debug->abstracter->getAbstraction((object) array('foo' => 'bar'));
        $this->debug->eventManager->unSubscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);

        $this->assertSame(Abstracter::UNDEFINED, $abs['properties']['foo']['value']);
    }

    public function testPropertyOverrideValues()
    {
        $callable = function (Abstraction $abs) {
            $abs['propertyOverrideValues']['foo'] = 'new value';
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);
        $abs = $this->debug->abstracter->getAbstraction((object) array('foo' => 'bar'));
        $this->debug->eventManager->unSubscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);

        $this->assertSame('new value', $abs['properties']['foo']['value']);
        $this->assertSame('debug', $abs['properties']['foo']['valueFrom']);

        $callable = function (Abstraction $abs) {
            $abs['propertyOverrideValues']['foo'] = array(
                'desc' => 'I describe foo',
                'value' => 'new value',
            );
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);
        $abs = $this->debug->abstracter->getAbstraction((object) array('foo' => 'bar'));
        $this->debug->eventManager->unSubscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);

        $this->assertSame('I describe foo', $abs['properties']['foo']['desc']);
        $this->assertSame('new value', $abs['properties']['foo']['value']);
        $this->assertSame('debug', $abs['properties']['foo']['valueFrom']);

        // __debugInfo + override
        $test = new \bdk\Test\Debug\Fixture\Test();
        $callable = function (Abstraction $abs) {
            $abs['propertyOverrideValues']['propPrivate'] = 'new value';
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);
        $abs = $this->debug->abstracter->getAbstraction($test);
        $this->debug->eventManager->unSubscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);

        $this->assertSame('new value', $abs['properties']['propPrivate']['value']);
        $this->assertSame('debug', $abs['properties']['propPrivate']['valueFrom']);
    }

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testGetAbstraction()
    {
    }
    */

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testGetMethods()
    {
    }
    */

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testGetParams()
    {
    }
    */

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testParamTypeHint()
    {
    }
    */

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testGetProperties()
    {
    }
    */

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testParseDocComment()
    {
    }
    */

    /**
     * test handling __debugInfo magic method
     *
     * @return void
     */
    public function testDebugInfo()
    {
        $test = new \bdk\Test\Debug\Fixture\Test();
        $this->debug->log('test', $test);
        $abstraction = $this->debug->data->get('log/0/args/1');
        $props = $abstraction['properties'];
        $this->assertArrayNotHasKey('propHidden', $props, 'propHidden shouldn\'t be debugged');
        // debugValue
        $this->assertSame('This property is debug only', $props['debugValue']['value']);
        $this->assertEquals('debug', $props['debugValue']['visibility']);
        // propPrivate
        $this->assertStringEndsWith('(alternate value via __debugInfo)', $props['propPrivate']['value']);
        $this->assertSame('debugInfo', $props['propPrivate']['valueFrom']);
    }

    /**
     * v 1.0 = fatal error
     *
     * @return void
     */
    public function testRecursiveObjectProp1()
    {
        $test = new \bdk\Test\Debug\Fixture\Test();
        $test->propPublic = array();
        $test->propPublic[] = &$test->propPublic;
        $this->debug->log('test', $test);
        $abstraction = $this->debug->data->get('log/0/args/1');
        $this->assertEquals(
            Abstracter::RECURSION,
            $abstraction['properties']['propPublic']['value'][0],
            'Did not find expected recursion'
        );
        $output = $this->debug->output();
        $select = '.m_log
            > .t_object > .object-inner
            > .property
            > .t_array .array-inner > li'
            // > .t_array
            . '> .t_recursion';
        $this->assertSelectCount($select, 1, $output);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRecursiveObjectProp2()
    {
        $test = new \bdk\Test\Debug\Fixture\Test();
        $test->propPublic = &$test;
        $this->debug->log('test', $test);
        $abstraction = $this->debug->data->get('log/0/args/1');
        $this->assertEquals(
            true,
            $abstraction['properties']['propPublic']['value']['isRecursion'],
            'Did not find expected recursion'
        );
        $this->debug->output();
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRecursiveObjectProp3()
    {
        $test = new \bdk\Test\Debug\Fixture\Test();
        $test->propPublic = array( &$test );
        $this->debug->log('test', $test);
        $abstraction = $this->debug->data->get('log/0/args/1');
        $this->assertEquals(
            true,
            $abstraction['properties']['propPublic']['value'][0]['isRecursion'],
            'Did not find expected recursion'
        );
        $this->debug->output();
    }

    /**
     * Test
     *
     * @return void
     */
    public function testCrossRefObjects()
    {
        $testOa = new \bdk\Test\Debug\Fixture\Test();
        $testOb = new \bdk\Test\Debug\Fixture\Test();
        $testOa->propPublic = 'this is object a';
        $testOb->propPublic = 'this is object b';
        $testOa->ob = $testOb;
        $testOb->oa = $testOa;
        $this->debug->log('test_oa', $testOa);
        $abstraction = $this->debug->data->get('log/0/args/1');
        $this->assertEquals(
            true,
            $abstraction['properties']['ob']['value']['properties']['oa']['value']['isRecursion'],
            'Did not find expected recursion'
        );
        $this->debug->output();
    }

    public function testScope()
    {
        $obj = new \bdk\Test\Debug\Fixture\ScopeTest();
        $obj->callsDebug();
        $logEntry = $this->debug->data->get('log/__end__');
        $abs = $logEntry['args'][0];
        $this->assertSame($abs['className'], $abs['scopeClass']);
    }
}

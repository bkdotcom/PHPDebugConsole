<?php

namespace bdk\Test\Debug\Type;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;
use bdk\Test\Debug\Fixture\TestObj;
use bdk\Test\Debug\Helper;
use PHP_CodeSniffer\Tokenizers\PHP;
use ReflectionObject;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Abstraction\Abstracter
 * @covers \bdk\Debug\Abstraction\Abstraction
 * @covers \bdk\Debug\Abstraction\AbstractObject
 * @covers \bdk\Debug\Abstraction\Object\AbstractInheritable
 * @covers \bdk\Debug\Abstraction\Object\Abstraction
 * @covers \bdk\Debug\Abstraction\Object\Constants
 * @covers \bdk\Debug\Abstraction\Object\Definition
 * @covers \bdk\Debug\Abstraction\Object\Helper
 * @covers \bdk\Debug\Abstraction\Object\MethodParams
 * @covers \bdk\Debug\Abstraction\Object\Methods
 * @covers \bdk\Debug\Abstraction\Object\Properties
 * @covers \bdk\Debug\Abstraction\Object\PropertiesDom
 * @covers \bdk\Debug\Abstraction\Object\PropertiesInstance
 * @covers \bdk\Debug\Abstraction\Object\PropertiesPhpDoc
 * @covers \bdk\Debug\Abstraction\Object\Subscriber
 * @covers \bdk\Debug\Abstraction\Type
 * @covers \bdk\Debug\Dump\AbstractValue
 * @covers \bdk\Debug\Dump\Base
 * @covers \bdk\Debug\Dump\Base\BaseObject
 * @covers \bdk\Debug\Dump\Base\Value
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\Helper
 * @covers \bdk\Debug\Dump\Html\HtmlObject
 * @covers \bdk\Debug\Dump\Html\Object\AbstractSection
 * @covers \bdk\Debug\Dump\Html\Object\Cases
 * @covers \bdk\Debug\Dump\Html\Object\Constants
 * @covers \bdk\Debug\Dump\Html\Object\ExtendsImplements
 * @covers \bdk\Debug\Dump\Html\Object\Methods
 * @covers \bdk\Debug\Dump\Html\Object\PhpDoc
 * @covers \bdk\Debug\Dump\Html\Object\Properties
 * @covers \bdk\Debug\Dump\Html\Value
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\Text\TextObject
 * @covers \bdk\Debug\Dump\Text\Value
 * @covers \bdk\Debug\Dump\TextAnsi
 * @covers \bdk\Debug\Dump\TextAnsi\TextAnsiObject
 * @covers \bdk\Debug\Dump\TextAnsi\Value
 *
 * @phpcs:disable Generic.Arrays.ArrayIndent.KeyIncorrect
 */
class ObjectTest extends DebugTestFramework
{
    protected static $testObj;

    public static function providerTestMethod()
    {
        $text = <<<'EOD'
bdk\Test\Debug\Fixture\TestObj
  Properties:
    ✨ This object has a __get() method
    ⚠ (public) baseDynamic = "duo"
    ⚠ (public) dynamic = "dynomite!"
    ⟳ (public) propPublic = "redefined in Test (public)"
    (public) propStatic = "I'm Static"
    (public) someArray = array(
      [int] => 123
      [numeric] => "123"
      [string] => "cheese"
      [bool] => true
      [obj] => stdClass
        Properties:
          (public) foo = "bar"
        Methods: none!
    )
    ↳ (✨ magic excluded) magicProp
    ↳ (✨ magic-read protected) magicReadProp = "not null"
    ↳ (protected) propProtected = "defined only in TestBase (protected)"
    (private) debug = bdk\Debug NOT INSPECTED
    (private) instance = bdk\Test\Debug\Fixture\TestObj *RECURSION*
    (private excluded) propNoDebug
    ⟳ (private) propPrivate = "redefined in Test (private) (alternate value via __debugInfo)"
    ↳ (🔒 private) testBasePrivate = "defined in TestBase (private)"
    (private) toString = "abracadabra"
    (private) toStrThrow = 0
    (debug) debugValue = "This property is debug only"
  Methods:
    public: 9
    protected: 1
    private: 1
    magic: 2
EOD;

        $ansi = <<<'EOD'
\e[38;5;250mbdk\Test\Debug\Fixture\\e[0m\e[1mTestObj\e[22m
  \e[4mProperties:\e[24m
    \e[38;5;250m✨ This object has a __get() method\e[0m
    \e[38;5;148m⚠\e[0m \e[38;5;250m(public)\e[0m \e[38;5;83mbaseDynamic\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mduo\e[38;5;250m"\e[0m
    \e[38;5;148m⚠\e[0m \e[38;5;250m(public)\e[0m \e[38;5;83mdynamic\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mdynomite!\e[38;5;250m"\e[0m
    \e[38;5;250m⟳\e[0m \e[38;5;250m(public)\e[0m \e[38;5;83mpropPublic\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mredefined in Test (public)\e[38;5;250m"\e[0m
    \e[38;5;250m(public)\e[0m \e[38;5;83mpropStatic\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mI'm Static\e[38;5;250m"\e[0m
    \e[38;5;250m(public)\e[0m \e[38;5;83msomeArray\e[0m \e[38;5;224m=\e[0m \e[38;5;45marray\e[38;5;245m(\e[0m
      \e[38;5;245m[\e[38;5;83mint\e[38;5;245m]\e[38;5;224m => \e[0m\e[96m123\e[0m
      \e[38;5;245m[\e[38;5;83mnumeric\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0m\e[96m123\e[0m\e[38;5;250m"\e[0m
      \e[38;5;245m[\e[38;5;83mstring\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0mcheese\e[38;5;250m"\e[0m
      \e[38;5;245m[\e[38;5;83mbool\e[38;5;245m]\e[38;5;224m => \e[0m\e[32mtrue\e[0m
      \e[38;5;245m[\e[38;5;83mobj\e[38;5;245m]\e[38;5;224m => \e[0m\e[1mstdClass\e[22m
        \e[4mProperties:\e[24m
            \e[38;5;250m(public)\e[0m \e[38;5;83mfoo\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mbar\e[38;5;250m"\e[0m
        Methods: none!
    \e[38;5;245m)\e[0m
    \e[38;5;250m↳\e[0m \e[38;5;250m(✨ magic excluded)\e[0m \e[38;5;83mmagicProp\e[0m
    \e[38;5;250m↳\e[0m \e[38;5;250m(✨ magic-read protected)\e[0m \e[38;5;83mmagicReadProp\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mnot null\e[38;5;250m"\e[0m
    \e[38;5;250m↳\e[0m \e[38;5;250m(protected)\e[0m \e[38;5;83mpropProtected\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mdefined only in TestBase (protected)\e[38;5;250m"\e[0m
    \e[38;5;250m(private)\e[0m \e[38;5;83mdebug\e[0m \e[38;5;224m=\e[0m \e[38;5;250mbdk\\e[0m\e[1mDebug\e[22m \e[38;5;9mNOT INSPECTED\e[0m
    \e[38;5;250m(private)\e[0m \e[38;5;83minstance\e[0m \e[38;5;224m=\e[0m \e[38;5;250mbdk\Test\Debug\Fixture\\e[0m\e[1mTestObj\e[22m \e[38;5;196m*RECURSION*\e[0m
    \e[38;5;250m(private excluded)\e[0m \e[38;5;83mpropNoDebug\e[0m
    \e[38;5;250m⟳\e[0m \e[38;5;250m(private)\e[0m \e[38;5;83mpropPrivate\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mredefined in Test (private) (alternate value via __debugInfo)\e[38;5;250m"\e[0m
    \e[38;5;250m↳\e[0m \e[38;5;250m(🔒 private)\e[0m \e[38;5;83mtestBasePrivate\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mdefined in TestBase (private)\e[38;5;250m"\e[0m
    \e[38;5;250m(private)\e[0m \e[38;5;83mtoString\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mabracadabra\e[38;5;250m"\e[0m
    \e[38;5;250m(private)\e[0m \e[38;5;83mtoStrThrow\e[0m \e[38;5;224m=\e[0m \e[96m0\e[0m
    \e[38;5;250m(debug)\e[0m \e[38;5;83mdebugValue\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mThis property is debug only\e[38;5;250m"\e[0m
  \e[4mMethods:\e[24m
    public\e[38;5;245m: \e[96m9\e[0m
    protected\e[38;5;245m: \e[96m1\e[0m
    private\e[38;5;245m: \e[96m1\e[0m
    magic\e[38;5;245m: \e[96m2\e[0m
EOD;
        $ansi = \str_replace('\e', "\e", $ansi);

        $text2 = <<<'EOD'
bdk\Test\Debug\Fixture\Test2
  Properties:
    ✨ This object has a __get() method
    ↳ (✨ magic) magicProp = undefined
    ↳ (✨ magic-read protected) magicReadProp = "not null"
  Methods:
    public: 3
    magic: 1
EOD;

        $crate = new \bdk\Debug\Route\WampCrate(Debug::getInstance());

        self::$testObj = new TestObj();
        self::$testObj->methodPublic((object) array());
        $abs1 = Debug::getInstance()->abstracter->getAbstraction(self::$testObj, 'log');
        $cratedAbs1 = $crate->crate($abs1);
        $cratedAbs1 = \bdk\Test\Debug\Helper::crate($cratedAbs1);
        // as provider method is static, but test is not static...
        //   we need to populate "scopeClass"
        $cratedAbs1['scopeClass'] = __CLASS__;

        $abs2 = Debug::getInstance()->abstracter->getAbstraction(new \bdk\Test\Debug\Fixture\Test2(), 'log');
        $cratedAbs2 = $crate->crate($abs2);
        $cratedAbs2 = \bdk\Test\Debug\Helper::crate($cratedAbs2);
        $cratedAbs2['scopeClass'] = __CLASS__;

        $tests = array(
            'closure' => array(
                'log',
                array(
                    static function ($foo, $bar) {
                        return $foo . $bar;
                    },
                ),
                array(
                    'entry' => static function ($logEntry) {
                        $objAbs = $logEntry['args'][0];
                        self::assertAbstractionType($objAbs);
                        $values = $objAbs->getValues();
                        self::assertSame('Closure', $values['className']);
                        $line = __LINE__ - 10;
                        self::assertSame(array(
                            'extensionName' => false,
                            'fileName' => __FILE__,
                            'startLine' => $line,
                        ), $values['definition']);
                        \array_walk($values['properties'], static function ($propInfo, $propName) use ($line) {
                            // echo \json_encode($propInfo, JSON_PRETTY_PRINT);
                            $values = \array_intersect_key($propInfo, \array_flip(array(
                                'value',
                                'valueFrom',
                                'visibility',
                            )));
                            switch ($propName) {
                                case 'debug.file':
                                    self::assertSame(array(
                                        'value' => __FILE__,
                                        'valueFrom' => 'debug',
                                        'visibility' => ['debug'],
                                    ), $values);
                                    break;
                                case 'debug.line':
                                    self::assertSame(array(
                                        'value' => $line,
                                        'valueFrom' => 'debug',
                                        'visibility' => ['debug'],
                                    ), $values);
                                    break;
                            }
                        });
                    },
                    'html' => '<li class="m_log"><div class="groupByInheritance t_object" data-accessible="public"><span class="classname">Closure</span>
                        <dl class="object-inner">
                        <dt class="modifiers">modifiers</dt>
                        <dd class="t_modifier_final">final</dd>
                        <dt class="properties">properties</dt>
                        <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string">file</span> <span class="t_operator">=</span> <span class="t_string">' . __FILE__ . '</span></dd>
                        <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">int</span> <span class="no-quotes t_identifier t_string">line</span> <span class="t_operator">=</span> <span class="t_int">%d</span></dd>
                        <dt class="methods">methods</dt>
                        <dd class="method private"><span class="t_modifier_private">private</span> <span class="t_identifier">__construct</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>
                        <dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier">__invoke</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$foo</span></span><span class="t_punct">,</span>
                            <span class="parameter"><span class="t_parameter-name">$bar</span></span><span class="t_punct">)</span></dd>
                        %a
                        </dl>
                        </div></li>',
                ),
            ),

            'stdClass' => array(
                'log',
                array(
                    (object) array(
                        "\xE2\x80\x8B" => 'zwsp',
                        "\xef\xbb\xbf" => 'bom',
                        "\xef\xbb\xbfbom\r\n\t\x07 \x1F \x7F \xc2\xa0<i>(nbsp)</i> \xE2\x80\x89(thsp), & \xE2\x80\x8B(zwsp)" => 'ctrl chars and whatnot',
                        "not\x80\xCF\x85tf8" => 'not utf8', // this forces the array to be stored as an abstraction
                        ' ' => 'space',
                        // '' => 'empty', // invalid for php < 7.0
                    ),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $abs = $logEntry['args'][0];
                        self::assertSame(array(
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
                        ), Helper::deObjectifyData($abs['keys']));
                        self::assertSame(array(
                            "\xE2\x80\x8B",
                            "\xef\xbb\xbf",
                            "\xef\xbb\xbfbom\r\n\t\x07 \x1F \x7F \xc2\xa0<i>(nbsp)</i> \xE2\x80\x89(thsp), & \xE2\x80\x8B(zwsp)",
                            ' ',
                            // '',
                            'ade50251dade9edc27e822ebdc3e9664',
                        ), \array_keys($abs['properties']));
                    },
                    'streamAnsi' => \str_replace('\e', "\e", '
                        \e[1mstdClass\e[22m
                           \e[4mProperties:\e[24m
                             \e[38;5;250m(public)\e[0m \e[38;5;83m\e[38;5;250m"\e[38;5;83;49m \e[38;5;250m"\e[38;5;83;49m\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mspace\e[38;5;250m"\e[0m
                             \e[38;5;250m(public)\e[0m \e[38;5;83mnot\e[30;48;5;250m80\e[38;5;83;49m\e[34;48;5;14mυ\e[38;5;83;49mtf8\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mnot utf8\e[38;5;250m"\e[0m
                             \e[38;5;250m(public)\e[0m \e[38;5;83m\e[34;48;5;14m\u{200b}\e[38;5;83;49m\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mzwsp\e[38;5;250m"\e[0m
                             \e[38;5;250m(public)\e[0m \e[38;5;83m\e[34;48;5;14m\u{feff}\e[38;5;83;49m\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mbom\e[38;5;250m"\e[0m
                             \e[38;5;250m(public)\e[0m \e[38;5;83m\e[38;5;250m"\e[38;5;83;49m\e[34;48;5;14m\u{feff}\e[38;5;83;49mbom[\r]
                             \e[34;48;5;14m\x07\e[38;5;83;49m \e[34;48;5;14m\x1f\e[38;5;83;49m \e[34;48;5;14m\x7f\e[38;5;83;49m \e[34;48;5;14m\u{00a0}\e[38;5;83;49m<i>(nbsp)</i> \e[34;48;5;14m\u{2009}\e[38;5;83;49m(thsp), & \e[34;48;5;14m\u{200b}\e[38;5;83;49m(zwsp)\e[38;5;250m"\e[38;5;83;49m\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mctrl chars and whatnot\e[38;5;250m"\e[0m
                           Methods: none!
                    '),
                    'text' => 'stdClass
                      Properties:
                        (public) " " = "space"
                        (public) not\x80\u{03c5}tf8 = "not utf8"
                        (public) \u{200b} = "zwsp"
                        (public) \u{feff} = "bom"
                        (public) "\u{feff}bom[\r]
                            \x07 \x1f \x7f \u{00a0}<i>(nbsp)</i> \u{2009}(thsp), & \u{200b}(zwsp)" = "ctrl chars and whatnot"
                      Methods: none!',
                ),
            ),

            'testObj' => array(
                'log',
                array(
                    self::$testObj,
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $objAbs = $logEntry['args'][0];
                        self::assertAbstractionType($objAbs);
                        self::assertSame(array(
                            array(
                                'desc' => 'when toStrThrow is `1`',
                                'type' => 'Exception',
                            ),
                        ), $objAbs['methods']['__toString']['phpDoc']['throws']);
                    },
                    'html' => static function ($str) {
                        self::assertStringStartsWith(
                            '<li class="m_log"><div class="groupByInheritance t_object" data-accessible="public">'
                            . '<span class="t_string t_stringified" title="__toString()">abracadabra</span>' . "\n"
                            . '<span class="classname" title="PhpDoc Summary' . "\n"
                            . "\n"
                            . 'PhpDoc Description"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestObj</span>',
                            $str
                        );

                        // obj + someArray.obj
                        self::assertSelectCount('dl.object-inner', 2, $str);

                        // extends
                        self::assertStringContainsString('<dt>extends</dt>' . "\n" .
                            '<dd class="extends"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestBase</span></dd>', $str);

                        // implements
                        if (PHP_VERSION_ID >= 80000) {
                            self::assertStringContainsString(\implode("\n", array(
                                '<dt>implements</dt>',
                                '<dd class="interface"><span class="classname">Stringable</span></dd>',
                            )), $str);
                        } else {
                            self::assertStringNotContainsString('<dt>implements</dt>', $str);
                        }

                        // constants
                        $expect = PHP_VERSION_ID >= 70100
                            ? '<dt class="constants">constants</dt>' . "\n"
                                . '<dd class="constant public" data-declared-prev="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string" title="constant documentation">MY_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test</span></dd>' . "\n"
                                . '<dd class="heading">Inherited from <span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestBase</span></dd>' . "\n"
                                . '<dd class="constant public" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string" title="Inherited description">INHERITED</span> <span class="t_operator">=</span> <span class="t_string">defined in TestBase</span></dd>' . "\n"
                            : '<dt class="constants">constants</dt>' . "\n"
                                . '<dd class="constant public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">INHERITED</span> <span class="t_operator">=</span> <span class="t_string">defined in TestBase</span></dd>' . "\n"
                                . '<dd class="constant public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">MY_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test</span></dd>';
                        self::assertStringContainsString($expect, $str);

                        // properties
                        $expect = \implode("\n", array(
                            '<dt class="properties">properties <span class="text-muted">(via __debugInfo)</span></dt>',
                            '<dd class="info magic">This object has <code>__get</code> and <code>__set</code> methods</dd>',
                            '<dd class="isDynamic property public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">baseDynamic</span> <span class="t_operator">=</span> <span class="t_string">duo</span></dd>',
                            '<dd class="isDynamic property public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">dynamic</span> <span class="t_operator">=</span> <span class="t_string">dynomite!</span></dd>',
                            '<dd class="property public" data-declared-prev="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string" title="Public Property.">propPublic</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test (public)</span></dd>',
                            '<dd class="isStatic property public"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="no-quotes t_identifier t_string">propStatic</span> <span class="t_operator">=</span> <span class="t_string">I\'m Static</span></dd>',
                            '<dd class="property public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">someArray</span> <span class="t_operator">=</span> <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>',
                            '<ul class="array-inner list-unstyled">',
                            "\t" . '<li><span class="t_key">int</span><span class="t_operator">=&gt;</span><span class="t_int">123</span></li>',
                            "\t" . '<li><span class="t_key">numeric</span><span class="t_operator">=&gt;</span><span class="t_string" data-type-more="numeric">123</span></li>',
                            "\t" . '<li><span class="t_key">string</span><span class="t_operator">=&gt;</span><span class="t_string">cheese</span></li>',
                            "\t" . '<li><span class="t_key">bool</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>',
                            "\t" . '<li><span class="t_key">obj</span><span class="t_operator">=&gt;</span><div class="groupByInheritance t_object" data-accessible="public"><span class="classname">stdClass</span>',
                            (PHP_VERSION_ID >= 80200
                                ? '<dl class="object-inner">' . "\n"
                                    . '<dt class="attributes">attributes</dt>' . "\n"
                                    . '<dd class="attribute"><span class="classname">AllowDynamicProperties</span></dd>'
                                : '<dl class="object-inner">'),
                            '<dt class="properties">properties</dt>',
                            '<dd class="property public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">foo</span> <span class="t_operator">=</span> <span class="t_string">bar</span></dd>',
                            '<dt class="methods">no methods</dt>',
                            '</dl>',
                            '</div></li>',
                            '</ul><span class="t_punct">)</span></span></dd>',
                            '<dd class="private property"><span class="t_modifier_private">private</span> <span class="no-quotes t_identifier t_string">debug</span> <span class="t_operator">=</span> <div class="t_object"><span class="classname"><span class="namespace">bdk\</span>Debug</span>',
                            '<span class="excluded">NOT INSPECTED</span></div></dd>',
                            '<dd class="private property"><span class="t_modifier_private">private</span> <span class="no-quotes t_identifier t_string">instance</span> <span class="t_operator">=</span> <div class="t_object"><span class="classname" title="PhpDoc Summary',
                            '',
                            'PhpDoc Description"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestObj</span>',
                            '<span class="t_recursion">*RECURSION*</span></div></dd>',
                            '<dd class="debuginfo-excluded private property"><span class="t_modifier_private">private</span> <span class="no-quotes t_identifier t_string">propNoDebug</span> <span class="t_operator">=</span> <span class="t_string">not included in __debugInfo</span></dd>',
                            '<dd class="debuginfo-value private property" data-declared-prev="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_private">private</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string" title="Private Property.">propPrivate</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test (private) (alternate value via __debugInfo)</span></dd>',
                            '<dd class="private property"><span class="t_modifier_private">private</span> <span class="no-quotes t_identifier t_string">toString</span> <span class="t_operator">=</span> <span class="t_string">abracadabra</span></dd>',
                            '<dd class="private property"><span class="t_modifier_private">private</span> <span class="no-quotes t_identifier t_string">toStrThrow</span> <span class="t_operator">=</span> <span class="t_int">0</span></dd>',
                            '<dd class="debuginfo-value property"><span class="t_modifier_debug">debug</span> <span class="no-quotes t_identifier t_string">debugValue</span> <span class="t_operator">=</span> <span class="t_string">This property is debug only</span></dd>',
                            '<dd class="heading">Inherited from <span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestBase</span></dd>',
                            '<dd class="debuginfo-excluded magic property" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_magic">magic</span> <span class="t_type">bool</span> <span class="no-quotes t_identifier t_string" title="I\'m avail via __get()">magicProp</span></dd>',
                            '<dd class="magic-read property protected" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_magic-read">magic-read</span> <span class="t_modifier_protected">protected</span> <span class="t_type">bool</span> <span class="no-quotes t_identifier t_string" title="Read Only!">magicReadProp</span> <span class="t_operator">=</span> <span class="t_string">not null</span></dd>',
                            '<dd class="property protected" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_protected">protected</span> <span class="no-quotes t_identifier t_string">propProtected</span> <span class="t_operator">=</span> <span class="t_string">defined only in TestBase (protected)</span></dd>',
                            '<dd class="private private-ancestor property" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_private">private</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string" title="Inherited desc">testBasePrivate</span> <span class="t_operator">=</span> <span class="t_string">defined in TestBase (private)</span></dd>',
                            '<dt class="methods">methods</dt>',
                        ));
                        if (PHP_VERSION_ID >= 80100) {
                            $expect = \str_replace('\'', '&#039;', $expect);
                        }
                        // echo 'expect = ' . $expect . "\n\n";
                        // echo 'actual = ' . $str . "\n";
                        self::assertStringContainsString($expect, $str);

                        // methods
                        $expect = \implode("\n", array(
                            '<dt class="methods">methods</dt>',
                            '<dd class="info magic">This object has a <code>__call</code> method</dd>',
                            '<dd class="method public" data-declared-prev="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Constructor',
                            '',
                            'Constructor description">__construct</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="value __toString will return;">$toString</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">abracadabra</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_type">int</span> <span class="t_parameter-name" title="0: don&#039;t, 1: throw, 2: throw &amp;amp; catch">$toStrThrow</span> <span class="t_operator">=</span> <span class="t_int t_parameter-default">0</span></span><span class="t_punct">)</span></dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="magic method">__debugInfo</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span title="property=&amp;gt;value array"><span class="t_type">array</span></span></dd>',
                            '<dd class="method public" ' . (PHP_VERSION_ID >= 80000 ? 'data-implements="Stringable" ' : '' ) . 'data-throws="[{&quot;desc&quot;:&quot;when toStrThrow is `1`&quot;,&quot;type&quot;:&quot;Exception&quot;}]"><span class="t_modifier_public">public</span> <span class="t_identifier" title="toString magic method',
                                '',
                                'Long Description">__toString</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">string</span>',
                                '<h3>static variables</h3>',
                                '<ul class="list-unstyled">',
                                    '<li><span class="no-quotes t_identifier t_string">static</span><span class="t_operator">=</span> <span class="t_string">I&#039;m static</span></li>',
                                '</ul>',
                                '<h3>return value</h3>',
                                '<ul class="list-unstyled"><li><span class="return-value t_string">abracadabra</span></li></ul></dd>',
                            '<dd class="isDeprecated isFinal method public" data-deprecated-desc="this method is bad and should feel bad"><span class="t_modifier_final">final</span> <span class="t_modifier_public">public</span> <span class="t_identifier" title="This method is public">methodPublic</span><span class="t_punct">(</span><span class="parameter"><span class="t_type"><span class="classname">stdClass</span></span> <span class="t_parameter-name" title="first param',
                                'two-line description!">$param1</span></span><span class="t_punct">,</span>',
                                    '<span class="parameter"><span class="t_type">array</span> <span class="t_parameter-name" title="second param">$param2</span> <span class="t_operator">=</span> <span class="t_array t_parameter-default"><span class="t_keyword">array</span><span class="t_punct">()</span></span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span>',
                                '<h3>static variables</h3>',
                                '<ul class="list-unstyled">',
                                '<li><span class="no-quotes t_identifier t_string">foo</span><span class="t_operator">=</span> <span class="t_int">42</span></li>',
                                '<li><span class="no-quotes t_identifier t_string">bar</span><span class="t_operator">=</span> <span class="t_string">test</span></li>',
                                '<li><span class="no-quotes t_identifier t_string">baz</span><span class="t_operator">=</span> <div class="t_object"><span class="classname" title="PhpDoc Summary',
                                    '',
                                    'PhpDoc Description"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestObj</span>',
                                    '<span class="t_recursion">*RECURSION*</span></div></li>',
                                    '</ul></dd>',
                            '<dd class="method protected"><span class="t_modifier_protected">protected</span> <span class="t_identifier" title="This method is protected">methodProtected</span><span class="t_punct">(</span><span class="parameter"><span class="t_type"><span class="classname"><span class="namespace">bdk\Debug\Abstraction\</span>Abstraction</span><span class="t_punct">[]</span></span> <span class="t_parameter-name" title="first param">$param1</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dd class="method private"><span class="t_modifier_private">private</span> <span class="t_identifier" title="This method is private">methodPrivate</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">mixed</span> <span class="t_parameter-name" title="first param (passed by ref)">&amp;$param1</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_type"><span class="classname"><span class="namespace">bdk\PubSub\</span>Event</span><span class="t_punct">[]</span></span> <span class="t_parameter-name" title="second param (passed by ref)">&amp;$param2</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_type">bool</span> <span class="t_parameter-name" title="3rd param not in method signature">...$param3</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dd class="heading">Inherited from <span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestBase</span></dd>',
                            '<dd class="method public" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="t_identifier" title="call magic method">__call</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="Method being called">$name</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_type">array</span> <span class="t_parameter-name" title="Arguments passed">$args</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">mixed</span></dd>',
                            '<dd class="method public" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="t_identifier" title="get magic method">__get</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="what we&#039;re getting">$key</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">mixed</span></dd>',
                            '<dd class="method public" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="t_identifier" title="set magic method">__set</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="what we&#039;re setting">$key</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_type">mixed</span> <span class="t_parameter-name" title="value">$val</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dd class="method public" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="t_identifier">testBasePublic</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                            '<dd class="isStatic method public" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">testBaseStatic</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                            '<dd class="magic method" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_magic">magic</span> <span class="t_identifier" title="I&#039;m a magic method">presto</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$foo</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_type">int</span> <span class="t_parameter-name">$int</span> <span class="t_operator">=</span> <span class="t_int t_parameter-default">1</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_parameter-name">$bool</span> <span class="t_operator">=</span> <span class="t_bool t_parameter-default" data-type-more="true">true</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_parameter-name">$null</span> <span class="t_operator">=</span> <span class="t_null t_parameter-default">null</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dd class="isStatic magic method" data-inherited-from="bdk\Test\Debug\Fixture\TestBase"><span class="t_modifier_magic">magic</span> <span class="t_modifier_static">static</span> <span class="t_identifier" title="I&#039;m a static magic method">prestoStatic</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name">$noDefault</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_parameter-name">$arr</span> <span class="t_operator">=</span> <span class="t_array t_parameter-default"><span class="t_keyword">array</span><span class="t_punct">()</span></span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_parameter-name">$opts</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">array(&#039;a&#039;=&gt;&#039;ay&#039;,&#039;b&#039;=&gt;&#039;bee&#039;)</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_parameter-name">$val</span> <span class="t_operator">=</span> <span class="t_const t_parameter-default" title="value: &quot;defined in TestBase&quot;"><span class="classname">self</span><span class="t_operator">::</span><span class="t_identifier">MY_CONSTANT</span></span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dt>phpDoc</dt>',
                        ));
                        if (PHP_VERSION_ID < 80100) {
                            $expect = \str_replace('&#039;', '\'', $expect);
                        }
                        // echo 'expect = ' . $expect . "\n\n";
                        // echo 'actual = ' . $str . "\n";
                        self::assertStringContainsString($expect, $str);

                        // phpdoc
                        self::assertStringContainsString(\implode("\n", array(
                            '<dt>phpDoc</dt>',
                            '<dd class="phpdoc phpdoc-link"><span class="phpdoc-tag">link</span><span class="t_operator">:</span> <a href="http://www.bradkent.com/php/debug" target="_blank">PHPDebugConsole Homepage</a></dd>',
                            '</dl>',
                        )), $str);
                    },
                    'script' => 'console.log({"___class_name":"bdk\\\\Test\\\\Debug\\\\Fixture\\\\TestObj","(public) baseDynamic":"duo","(public) dynamic":"dynomite!","(public) propPublic":"redefined in Test (public)","(public) propStatic":"I\'m Static","(public) someArray":{"int":123,"numeric":"123","string":"cheese","bool":true,"obj":{"___class_name":"stdClass","(public) foo":"bar"}},"(✨ magic excluded) magicProp":undefined,"(✨ magic-read protected) magicReadProp":"not null","(protected) propProtected":"defined only in TestBase (protected)","(private) debug":"(object) bdk\\\\Debug NOT INSPECTED","(private) instance":"(object) bdk\\\\Test\\\\Debug\\\\Fixture\\\\TestObj *RECURSION*","(private excluded) propNoDebug":"not included in __debugInfo","(private) propPrivate":"redefined in Test (private) (alternate value via __debugInfo)","(🔒 private) testBasePrivate":"defined in TestBase (private)","(private) toString":"abracadabra","(private) toStrThrow":0,"(debug) debugValue":"This property is debug only"});',
                    'streamAnsi' => $ansi,
                    'text' => $text,
                    'wamp' => array(
                        'log',
                        array(
                            $cratedAbs1,
                        ),
                    ),
                ),
            ),

            'stringMaxLen' => array(
                'log',
                array(
                    new TestObj('This is the song that never ends.  Yes, it goes on and on my friend.  Some people started singing it not knowing what it was.  And they\'ll never stop singing it forever just because.  This is the song that never ends...'),
                    Debug::meta('cfg', 'stringMaxLen', 150),   // this will store abstracted/truncated value...     test that "more bytes" is calculated correctly
                ),
                array(
                    'html' => static function ($str) {
                        self::assertStringContainsString('<span class="t_string t_string_trunc t_stringified" title="__toString()">This is the song that never ends.  Yes, it goes on and on my friend.  Some people started singing it&hellip; <i>(119 more bytes)</i></span>', $str);
                    },
                ),
            ),

            'test2' => array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Test2(),
                ),
                array(
                    'html' => static function ($html) {
                        // properties
                        $expect = \implode("\n", array(
                            '<dt class="properties">properties</dt>',
                            '<dd class="info magic">This object has a <code>__get</code> method</dd>',
                            '<dd class="heading">Inherited from <span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>Test2Base</span></dd>',
                            '<dd class="magic property" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_magic">magic</span> <span class="t_type">bool</span> <span class="no-quotes t_identifier t_string" title="I\'m avail via __get()">magicProp</span></dd>',
                            '<dd class="magic-read property protected" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_magic-read">magic-read</span> <span class="t_modifier_protected">protected</span> <span class="t_type">bool</span> <span class="no-quotes t_identifier t_string" title="Read Only!">magicReadProp</span> <span class="t_operator">=</span> <span class="t_string">not null</span></dd>',
                        ));
                        if (PHP_VERSION_ID >= 80100) {
                            $expect = \str_replace('\'', '&#039;', $expect);
                        }
                        // echo 'expect = ' . $expect . "\n";
                        // echo 'actual = ' . $html . "\n";
                        self::assertStringContainsString($expect, $html);

                        // methods
                        $constName = \defined('HHVM_VERSION')
                            ? '<span class="classname">\\bdk\\Test\\\Debug\\\Test2Base</span><span class="t_operator">::</span><span class="t_identifier">WORD</span>'
                            : '<span class="classname">self</span><span class="t_operator">::</span><span class="t_identifier">WORD</span>';

                        $expect = \implode("\n", array(
                            '<dt class="methods">methods</dt>',
                            '<dd class="info magic">This object has a <code>__call</code> method</dd>',
                            '<dd class="heading">Inherited from <span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>Test2Base</span></dd>',
                            '<dd class="method public" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_public">public</span> <span class="t_identifier" title="magic method">__call</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="Method being called">$name</span></span><span class="t_punct">,</span>',
                                '<span class="parameter"><span class="t_type">array</span> <span class="t_parameter-name" title="Arguments passed">$args</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">mixed</span></dd>',
                            '<dd class="method public" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_public">public</span> <span class="t_identifier" title="get magic method">__get</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="what we\'re getting">$key</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">mixed</span></dd>',
                            \version_compare(PHP_VERSION, '5.4.6', '>=')
                                ? '<dd class="method public" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Test constant as default value">constDefault</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="only php &amp;gt;= 5.4.6 can get the name of the constant used">$param</span> <span class="t_operator">=</span> <span class="t_const t_parameter-default" title="value: &quot;bird&quot;">' . $constName . '</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>'
                                : '<dd class="method public" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Test constant as default value">constDefault</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="only php &amp;gt;= 5.4.6 can get the name of the constant used">$param</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">bird</span></span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>',
                            '<dd class="magic method" data-inherited-from="bdk\Test\Debug\Fixture\Test2Base"><span class="t_modifier_magic">magic</span> <span class="t_identifier" title="test constant as param">methConstTest</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$mode</span> <span class="t_operator">=</span> <span class="t_const t_parameter-default" title="value: &quot;bird&quot;"><span class="classname">self</span><span class="t_operator">::</span><span class="t_identifier">WORD</span></span></span><span class="t_punct">)</span></dd>',
                            '</dl>',
                        ));
                        if (PHP_VERSION_ID >= 80100) {
                            $expect = \str_replace('\'', '&#039;', $expect);
                        }
                        // echo 'expect = ' . $expect . "\n";
                        // echo 'actual = ' . $html . "\n";
                        self::assertStringContainsString($expect, $html);
                    },
                    'script' => 'console.log({"___class_name":"bdk\\\\Test\\\\Debug\\\\Fixture\\\\Test2","(✨ magic) magicProp":undefined,"(✨ magic-read protected) magicReadProp":"not null"});',
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
                    new TestObj(),
                    Debug::meta('cfg', 'phpDocCollect', false),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $objAbs = $logEntry['args'][0];
                        $inheritedVals = $objAbs->getInheritedValues();
                        $instanceVals = $objAbs->getInstanceValues();
                        self::assertTrue(($objAbs['cfgFlags'] & AbstractObject::PHPDOC_COLLECT) === 0);
                        self::assertSame(
                            // we collect the classes's php doc regardless of config
                            array(
                                'desc' => 'PhpDoc Description',
                                'summary' => 'PhpDoc Summary',
                            ),
                            \array_intersect_key($objAbs['phpDoc'], \array_flip(array('desc','summary')))
                        );

                        self::assertCount(2, $objAbs['constants']);
                        self::assertCount(2, $inheritedVals['constants']);
                        self::assertArrayNotHasKey('constants', $instanceVals);

                        if (PHP_VERSION_ID >= 70100) {
                            // constant reflection is php 7.1+
                            foreach ($objAbs['constants'] as $name => $const) {
                                // definition still collects everything regardless
                                self::assertNotEmpty($const['phpDoc']['summary'], $name . ' summary is empty');
                            }
                        }

                        $propsWithSummary = array('magicProp','magicReadProp','propPrivate','propPublic','testBasePrivate');
                        foreach ($objAbs['properties'] as $name => $prop) {
                            \in_array($name, $propsWithSummary, true)
                                ? self::assertNotSame('', $prop['phpDoc']['summary'])
                                : self::assertSame('', $prop['phpDoc']['summary']);
                        }

                        /*
                        foreach ($objAbs['methods'] as $method) {
                            self::assertSame(
                                array('desc' => null, 'summary' => null),
                                \array_intersect_key($method['phpDoc'], \array_flip(array('desc','summary')))
                            );
                        }
                        */
                    },
                ),
            ),

            'phpDocOutputFalse' => array(
                'log',
                array(
                    new TestObj(),
                    Debug::meta('cfg', 'phpDocOutput', false),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        // quick confirm that was collected
                        $objAbs = $logEntry['args'][0];
                        self::assertSame(
                            array(
                                'desc' => 'PhpDoc Description',
                                'summary' => 'PhpDoc Summary',
                            ),
                            \array_intersect_key($objAbs['phpDoc'], \array_flip(array('desc','summary')))
                        );
                    },
                    'html' => static function ($html, LogEntry $logEntry) {
                        \preg_match_all('/title="([^"]+)"/s', $html, $matches);
                        $matches = \array_diff($matches[1], array(
                            '__toString()',
                            'value: &quot;defined in TestBase&quot;',
                        ));
                        self::assertEmpty($matches, 'Html should not contain phpDoc summary & descriptions');
                    },
                ),
            ),

            'methodCollectFalse' => array(
                'log',
                array(
                    new TestObj(),
                    Debug::meta('cfg', array(
                        'methodCollect' => false,
                    )),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $objAbs = $logEntry['args'][0];
                        $values = $objAbs->getValues();
                        self::assertFalse(($values['cfgFlags'] & AbstractObject::METHOD_COLLECT) === AbstractObject::METHOD_COLLECT);
                        self::assertTrue(($values['cfgFlags'] & AbstractObject::METHOD_OUTPUT) === AbstractObject::METHOD_OUTPUT);
                    },
                    'html' => static function ($str) {
                        self::assertStringContainsString(\implode("\n", array(
                            '<dt class="methods">methods <i>not collected</i></dt>',
                            '<dt>phpDoc</dt>',
                        )), $str);
                    },
                    'streamAnsi' => static function ($str) {
                        $containsMethods = \preg_match('/methods/i', $str) === 1;
                        self::assertFalse($containsMethods, 'Output should not contain methods');
                    },
                    'text' => static function ($str) {
                        $containsMethods = \preg_match('/methods/i', $str) === 1;
                        self::assertFalse($containsMethods, 'Output should not contain methods');
                    },
                ),
            ),

            'phpDocExtends' => array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Utility\PhpDocExtends(),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $abs = $logEntry['args'][0];
                        self::assertSame(array(
                            'attributes' => array(),
                            'declaredLast' => PHP_VERSION_ID >= 70100
                                ? 'bdk\Test\Debug\Fixture\Utility\PhpDocExtends'
                                : null,
                            'declaredOrig' => 'bdk\Test\Debug\Fixture\SomeInterface',
                            'declaredPrev' => PHP_VERSION_ID >= 70100
                                ? 'bdk\Test\Debug\Fixture\SomeInterface'
                                : null,
                            'isFinal' => false,
                            'phpDoc' => array(
                                'desc' => '',
                                'summary' => PHP_VERSION_ID >= 70100
                                    ? 'Interface summary'
                                    : '',
                            ),
                            'type' => null,
                            'value' => 'never change',
                            'visibility' => 'public',
                        ), $abs['constants']['SOME_CONSTANT']);
                    },
                    'html' => static function ($html) {
                        $expect = '<dt class="constants">constants</dt>' . "\n"
                            . (PHP_VERSION_ID >= 70100
                                ? '<dd class="constant public" data-declared-prev="bdk\Test\Debug\Fixture\SomeInterface"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string" title="Interface summary">SOME_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">never change</span></dd>'
                                : '<dd class="constant public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">SOME_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">never change</span></dd>'
                            ) . "\n"
                            // . '<dd class="heading">Inherited from <span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Utility\</span>PhpDocImplements</span></dd>' . "\n"
                            . '<dt class="properties">properties</dt>' . "\n"
                            . '<dd class="property public" data-declared-prev="bdk\Test\Debug\Fixture\Utility\PhpDocImplements"><span class="t_modifier_public">public</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string" title="$someProperty summary' . "\n"
                                . "\n"
                                . 'desc">someProperty</span> <span class="t_operator">=</span> <span class="t_string">St. James Place</span></dd>' . "\n"
                            . '<dd class="magic property"><span class="t_modifier_magic">magic</span> <span class="t_type">bool</span> <span class="no-quotes t_identifier t_string" title="I\'m avail via __get()">magicProp</span></dd>' . "\n"
                            . '<dd class="magic-read property"><span class="t_modifier_magic-read">magic-read</span> <span class="t_type">bool</span> <span class="no-quotes t_identifier t_string" title="Read Only!">magicReadProp</span></dd>' . "\n"
                            // . '<dd class="heading">Inherited from <span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\Utility\</span>PhpDocImplements</span></dd>' . "\n"
                            . '<dt class="methods">methods</dt>' . "\n"
                            . '<dd class="method public" data-declared-prev="bdk\Test\Debug\Fixture\Utility\PhpDocImplements" data-implements="bdk\Test\Debug\Fixture\SomeInterface"><span class="t_modifier_public">public</span> <span class="t_identifier" title="SomeInterface summary' . "\n"
                            . '' . "\n"
                            . 'Tests that self resolves to fully qualified SomeInterface">someMethod</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>SomeInterface</span></span></dd>' . "\n"
                            . '<dd class="method public" data-declared-prev="bdk\Test\Debug\Fixture\Utility\PhpDocImplements" data-implements="bdk\Test\Debug\Fixture\SomeInterface"><span class="t_modifier_public">public</span> <span class="t_identifier" title="SomeInterface summary">someMethod2</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>' . "\n"
                            . '<dd class="method public" data-declared-prev="bdk\Test\Debug\Fixture\Utility\PhpDocImplements"><span class="t_modifier_public">public</span> <span class="t_identifier" title="PhpDocExtends summary' . "\n"
                            . '' . "\n"
                            . 'PhpDocExtends desc / PhpDocImplements desc">someMethod3</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>';
                        if (PHP_VERSION_ID >= 80100) {
                            $expect = \str_replace('\'', '&#039;', $expect);
                        }
                        // echo 'expect = ' . $expect . "\n\n";
                        // echo 'actual = ' . $html . "\n";
                        self::assertStringContainsString($expect, $html);
                    },
                ),
            ),

            'phpStanTypes' => array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\ArrayDocs(),
                ),
                array(
                    'html' => static function ($html) {
                        // echo 'html = ' . $html . "\n";
                        $expect = \trim(\preg_replace('/^\s+/m', '', '
                            <li class="m_log"><div class="groupByInheritance t_object" data-accessible="public"><span class="classname" title="&amp;quot;Array Shapes&amp;quot; and &amp;quot;General Arrays&amp;quot;"><span class="namespace">bdk\Test\Debug\Fixture\</span>ArrayDocs</span>
                            <dl class="object-inner">
                            <dt class="properties">properties</dt>
                            <dd class="property public"><span class="t_modifier_public">public</span> <span class="t_type">non-empty-array</span><span class="t_punct">&lt;</span><span class="t_type">string</span><span class="t_punct">,</span> <span class="t_type">array</span><span class="t_punct">&lt;</span><span class="t_type">int</span><span class="t_punct">,</span> <span class="t_type">int</span><span class="t_punct">|</span><span class="t_type">string</span><span class="t_punct">&gt;</span><span class="t_punct">|</span><span class="t_type">int</span><span class="t_punct">|</span><span class="t_type">string</span><span class="t_punct">&gt;</span><span class="t_type"><span class="t_punct">[]</span></span> <span class="no-quotes t_identifier t_string" title="General Description">general</span> <span class="t_operator">=</span> <span class="t_null">null</span></dd>
                            <dd class="property public"><span class="t_modifier_public">public</span> <span class="t_type">null</span><span class="t_punct">|</span><span class="t_string t_type">literal</span><span class="t_punct">|</span><span class="t_type">123</span> <span class="no-quotes t_identifier t_string" title="Union test">literal</span> <span class="t_operator">=</span> <span class="t_null">null</span></dd>
                            <dd class="property public"><span class="t_modifier_public">public</span> <span class="t_type">array</span><span class="t_punct">{</span><span class="t_string">name</span><span class="t_punct">:</span> <span class="t_type">string</span><span class="t_punct">,</span> <span class="t_string">value</span><span class="t_punct">:</span> <span class="t_type">positive-int</span><span class="t_punct">|</span><span class="t_type">string</span><span class="t_punct">,</span> <span class="t_string">foo</span><span class="t_punct">:</span> <span class="t_type"><span class="classname">bar</span></span><span class="t_punct">,</span> <span class="t_string">number</span><span class="t_punct">:</span> <span class="t_type">42</span><span class="t_punct">,</span> <span class="t_string">string</span><span class="t_punct">:</span> <span class="t_string t_type">theory</span><span class="t_punct">}</span> <span class="no-quotes t_identifier t_string" title="Shape Description">shape</span> <span class="t_operator">=</span> <span class="t_null">null</span></dd>
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
                        self::assertStringContainsString($expect, $html);
                    },
                ),
            ),

            // php 8.4+
            'propertyAsymVisibility' => array(
                'log',
                array(
                    PHP_VERSION_ID >= 80400
                        ? new \bdk\Test\Debug\Fixture\PropertyAsymVisibility()
                        : null,
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $expect = require __DIR__ . '/../data/PropertyAsymVisibility.php';
                        $actual = Helper::deObjectifyData($logEntry['args'][0]->getValues(), true, false, true);
                        // \bdk\Debug::varDump('expect', $expect);
                        // \bdk\Debug::varDump('actual', $actual);
                        self::assertSame($expect, $actual);
                    },
                    'html' => static function ($html) {
                        $expect = require __DIR__ . '/../data/PropertyAsymVisibility_html.php';
                        // echo 'expect: ' . $expect . "\n\n";
                        // echo 'actual: ' . $html . "\n\n";
                        self::assertStringMatchesFormatNormalized($expect, $html);
                    },
                ),
            ),

            // php 8.4+
            'propertyHooks' => array(
                'log',
                array(
                    PHP_VERSION_ID >= 80400
                        ? new \bdk\Test\Debug\Fixture\PropertyHooks()
                        : null,
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $expect = require __DIR__ . '/../data/PropertyHooks.php';
                        $actual = Helper::deObjectifyData($logEntry['args'][0]->getValues(), true, false, true);
                        // \bdk\Debug::varDump('expect', $expect);
                        // \bdk\Debug::varDump('actual', $actual);
                        self::assertSame($expect, $actual);
                    },
                    'html' => static function ($html) {
                        $expect = require __DIR__ . '/../data/PropertyHooks_html.php';
                        // echo 'expect: ' . $expect . "\n\n";
                        // echo 'actual: ' . $html . "\n\n";
                        self::assertStringMatchesFormatNormalized($expect, $html);
                    },
                ),
            ),

            'sortByName' => array(
                'log',
                array(
                    new TestObj(),
                    Debug::meta('cfg', 'objectSort', 'name'),
                ),
                array(
                    'entry' => static function (LogEntry $entry) {
                        // echo print_r($entry->getValues(), true) . "\n";
                        $abs = $entry['args'][0];
                        $constNames = \array_keys($abs['constants']);
                        \sort($constNames);
                        $propNames = \array_keys($abs['properties']);
                        \sort($propNames, SORT_STRING | SORT_FLAG_CASE);
                        $methodNames = \array_keys($abs['methods']);
                        \sort($methodNames);
                        self::assertSame(array(
                            'INHERITED',
                            'MY_CONSTANT',
                        ), $constNames);
                        self::assertSame(array(
                            'baseDynamic',
                            'debug',
                            'debugValue',
                            'dynamic',
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
                        ), $propNames);
                        self::assertSame(array(
                            '__call',
                            '__construct',
                            '__debugInfo',
                            '__get',
                            '__set',
                            '__toString',
                            'methodPrivate',
                            'methodProtected',
                            'methodPublic',
                            'presto',
                            'prestoStatic',
                            'testBasePublic',
                            'testBaseStatic',
                        ), $methodNames);
                    },
                ),
            ),

            'keys' => array(
                'log',
                array(
                    (object) array(
                        "\xE2\x80\x8B" => 'zwsp',
                        "\xef\xbb\xbf" => 'bom',
                        "\xef\xbb\xbfbom\r\n\t\x07 \x1F \x7F \xc2\xa0<i>(nbsp)</i> \xE2\x80\x89(thsp), & \xE2\x80\x8B(zwsp)" => 'ctrl chars and whatnot',
                        ' ' => 'space',
                        '' => 'empty',
                    ),
                ),
                array(
                    'html' => '<li class="m_log"><div class="groupByInheritance t_object" data-accessible="public"><span class="classname">stdClass</span>
                        <dl class="object-inner">
                        %A<dt class="properties">properties</dt>
                        ' . (PHP_VERSION_ID >= 70400 ? '<dd class="property public"><span class="t_modifier_public">public</span> <span class="t_identifier t_string"></span> <span class="t_operator">=</span> <span class="t_string">empty</span></dd>' . "\n" : '')
                        . '<dd class="property public"><span class="t_modifier_public">public</span> <span class="t_identifier t_string"> </span> <span class="t_operator">=</span> <span class="t_string">space</span></dd>
                        <dd class="property public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string"><span class="char-ws" data-code-point="200B" title="U-200B: Zero Width Space">\u{200b}</span></span> <span class="t_operator">=</span> <span class="t_string">zwsp</span></dd>
                        <dd class="property public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string"><span class="char-ws" data-code-point="FEFF" title="U-FEFF: BOM / Zero Width No-Break Space">\u{feff}</span></span> <span class="t_operator">=</span> <span class="t_string">bom</span></dd>
                        <dd class="property public"><span class="t_modifier_public">public</span> <span class="t_identifier t_string"><span class="char-ws" data-code-point="FEFF" title="U-FEFF: BOM / Zero Width No-Break Space">\u{feff}</span>bom<span class="ws_r"></span><span class="ws_n"></span>
                        <span class="ws_t">%s</span><span class="char-control" title="\x07: BEL (bell)">␇</span> <span class="char-control" title="\x1f: US (unit separator)">␟</span> <span class="char-control" title="\x7f: DEL">␡</span> <span class="char-ws" data-code-point="00A0" title="U-00A0: NBSP">\u{00a0}</span>&lt;i&gt;(nbsp)&lt;/i&gt; <span class="char-ws" data-code-point="2009" title="U-2009: Thin Space">\u{2009}</span>(thsp), &amp; <span class="char-ws" data-code-point="200B" title="U-200B: Zero Width Space">\u{200b}</span>(zwsp)</span> <span class="t_operator">=</span> <span class="t_string">ctrl chars and whatnot</span></dd>
                        %A</dl>
                        </div></li>',
                    'script' => 'console.log({"___class_name":"stdClass",' . (PHP_VERSION_ID >= 70400 ? '"(public) ":"empty",' : '') . '"(public)  ":"space","(public) \\\u{200b}":"zwsp","(public) \\\u{feff}":"bom","(public) \\\u{feff}bom\r\n\t\\\x07 \\\x1f \\\x7f \\\u{00a0}<i>(nbsp)</i> \\\u{2009}(thsp), & \\\u{200b}(zwsp)":"ctrl chars and whatnot"});',
                    'text' => 'stdClass
                          Properties:
                            ' . (PHP_VERSION_ID >= 70400 ? '(public) "" = "empty"' . "\n" : '')
                            . '(public) " " = "space"
                            (public) \u{200b} = "zwsp"
                            (public) \u{feff} = "bom"
                            (public) "\u{feff}bom%A
                                %A\x07 \x1f \x7f \u{00a0}<i>(nbsp)</i> \u{2009}(thsp), & \u{200b}(zwsp)" = "ctrl chars and whatnot"
                          Methods: none!',
                ),
            ),

            'constants' => array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\ParamConstants(),
                ),
                array(
                    'chromeLogger' => array(
                        array(
                            array(
                                '___class_name' => 'bdk\Test\Debug\Fixture\ParamConstants',
                            ),
                        ),
                        null,
                        '',
                    ),
                    'firephp' => 'X-Wf-1-1-1-%d: 78|[{"Type":"LOG"},{"___class_name":"bdk\\\\Test\\\\Debug\\\\Fixture\\\\ParamConstants"}]|',
                    'html' => '<li class="m_log"><div class="groupByInheritance t_object" data-accessible="public"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>ParamConstants</span>
                        <dl class="object-inner">
                            <dt class="constants">constants</dt>
                            <dd class="constant public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">CLASS_CONST</span> <span class="t_operator">=</span> <span class="t_string">bar</span></dd>
                            <dt class="properties">no properties</dt>
                            <dt class="methods">methods</dt>
                            <dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier">test</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$foo</span> <span class="t_operator">=</span> <span class="t_const t_parameter-default" title="value: &quot;bar&quot;"><span class="classname">self</span><span class="t_operator">::</span><span class="t_identifier">CLASS_CONST</span></span></span><span class="t_punct">,</span>
                            <span class="parameter"><span class="t_parameter-name">$bar</span> <span class="t_operator">=</span> <span class="t_const t_parameter-default" title="value: 0"><span class="t_identifier">SEEK_SET</span></span></span><span class="t_punct">,</span>
                            <span class="parameter"><span class="t_parameter-name">$baz</span> <span class="t_operator">=</span> <span class="t_const t_parameter-default" title="value: &quot;foo&quot;"><span class="namespace">bdk\Test\Debug\Fixture\</span><span class="t_identifier">NAMESPACE_CONST</span></span></span><span class="t_punct">,</span>
                            <span class="parameter"><span class="t_parameter-name">$biz</span> <span class="t_operator">=</span> <span class="t_const t_parameter-default" title="value: &quot;defined in TestBase&quot;"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestBase</span><span class="t_operator">::</span><span class="t_identifier">MY_CONSTANT</span></span></span><span class="t_punct">)</span></dd>
                        </dl>
                        </div></li>',
                    'script' => 'console.log({"___class_name":"bdk\\\\Test\\\\Debug\\\\Fixture\\\\ParamConstants"});',
                    'streamAnsi' => "\e[38;5;250mbdk\Test\Debug\Fixture\\\e[0m\e[1mParamConstants\e[22m
                        Properties: none!
                        \e[4mMethods:\e[24m
                        public\e[38;5;245m: \e[96m1\e[0m",
                    'text' => 'bdk\Test\Debug\Fixture\ParamConstants
                        Properties: none!
                        Methods:
                        public: 1',
                    'wamp' => static function ($messages) {
                        $abs = $messages[0]['args'][1][0]['classDefinitions']['bdk\Test\Debug\Fixture\ParamConstants'];
                        $params = $abs['methods']['test']['params'];
                        $defaultValuesExpect = array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'name' => 'self::CLASS_CONST',
                                'type' => Type::TYPE_CONST,
                                'value' => 'bar',
                            ),
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'name' => 'SEEK_SET',
                                'type' => Type::TYPE_CONST,
                                'value' => 0,
                            ),
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'name' => 'bdk\Test\Debug\Fixture\NAMESPACE_CONST',
                                'type' => Type::TYPE_CONST,
                                'value' => 'foo',
                            ),
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'name' => 'bdk\Test\Debug\Fixture\TestBase::MY_CONSTANT',
                                'type' => Type::TYPE_CONST,
                                'value' => 'defined in TestBase',
                            ),
                        );
                        foreach ($params as $i => $param) {
                            self::assertSame($param['defaultValue'], $defaultValuesExpect[$i]);
                        }
                    },
                ),
            ),
        );

        if (PHP_VERSION_ID < 80400) {
            unset($tests['propertyAsymVisibility'], $tests['propertyHooks']);
        }

        // $tests = \array_intersect_key($tests, \array_flip(array('propertyHooks')));

        return $tests;
    }

    /**
     * v 1.0 = fatal error
     *
     * @return void
     */
    public function testDereferenceObject()
    {
        $testVal = 'success A';
        $testO = new TestObj();
        $testO->propPublic = &$testVal;
        $this->debug->log('test_o', $testO);
        $testVal = 'success B';
        $this->debug->log('test_o', $testO);
        $testVal = 'fail';
        $output = $this->debug->output();
        self::assertStringContainsString('success A', $output);
        self::assertStringContainsString('success B', $output);
        self::assertStringNotContainsString('fail', $output);
        self::assertSame('fail', $testO->propPublic);   // prop should be 'fail' at this point
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
        $abs = $this->debug->abstracter->getAbstraction(self::$testObj);

        self::assertSame('object', $abs['type']);
        self::assertSame('bdk\Test\Debug\Fixture\TestObj', $abs['className']);
        self::assertSame(
            array('bdk\Test\Debug\Fixture\TestBase'),
            $abs['extends']
        );
        $expect = array();
        if (\defined('HHVM_VERSION')) {
            $expect = array('Stringish', 'XHPChild'); // hhvm-3.25 has XHPChild
        } elseif (PHP_VERSION_ID >= 80000) {
            $expect = array('Stringable');
        }
        self::assertSame($expect, $abs['implements']);

        $constants = $abs['constants'];
        \ksort($constants);
        self::assertSame(array(
            'INHERITED' => array(
                'attributes' => array(),
                'declaredLast' => PHP_VERSION_ID >= 70100
                    ? 'bdk\Test\Debug\Fixture\TestBase'
                    : null,
                'declaredOrig' => 'bdk\Test\Debug\Fixture\TestBase',
                'declaredPrev' => null,
                'isFinal' => false,
                'phpDoc' => array(
                    'desc' => '',
                    'summary' => PHP_VERSION_ID >= 70100
                        ? 'Inherited description'
                        : '',
                ),
                'type' => null,
                'value' => 'defined in TestBase',
                'visibility' => 'public',
            ),
            'MY_CONSTANT' => array(
                'attributes' => array(),
                'declaredLast' => PHP_VERSION_ID >= 70100
                    ? 'bdk\Test\Debug\Fixture\TestObj'
                    : null,
                'declaredOrig' => 'bdk\Test\Debug\Fixture\TestBase',
                'declaredPrev' => PHP_VERSION_ID >= 70100
                    ? 'bdk\Test\Debug\Fixture\TestBase'
                    : null,
                'isFinal' => false,
                'phpDoc' => array(
                    'desc' => '',
                    'summary' => PHP_VERSION_ID >= 70100
                        ? 'constant documentation'
                        : '',
                ),
                'type' => null,
                'value' => 'redefined in Test',
                'visibility' => 'public',
            ),
        ), $constants);

        self::assertArraySubset(
            array(
                'desc' => 'PhpDoc Description',
                'summary' => 'PhpDoc Summary',
            ),
            $abs['phpDoc']
        );
        self::assertTrue($abs['viaDebugInfo']);

        //    Properties
        // self::assertArrayNotHasKey('propNoDebug', $abs['properties']);
        self::assertTrue($abs['properties']['propNoDebug']['debugInfoExcluded']);
        self::assertTrue($abs['properties']['debug']['value']['isExcluded']);
        self::assertTrue($abs['properties']['instance']['value']['isRecursion']);
        self::assertArraySubset(
            array(
                'declaredLast' => null,
                'declaredOrig' => null,
                'declaredPrev' => null,
                'value' => 'duo',
            ),
            $abs['properties']['baseDynamic']
        );
        self::assertArraySubset(
            array(
                'declaredLast' => null,
                'declaredOrig' => null,
                'declaredPrev' => null,
                'value' => 'dynomite!',
            ),
            $abs['properties']['dynamic']
        );
        self::assertArraySubset(
            array(
                'attributes' => array(),
                'declaredLast' => 'bdk\Test\Debug\Fixture\TestObj',
                'declaredOrig' => 'bdk\Test\Debug\Fixture\TestBase',
                'declaredPrev' => 'bdk\Test\Debug\Fixture\TestBase',
                'isPromoted' => false,
                'value' => 'redefined in Test (public)',
                'valueFrom' => 'value',
                'visibility' => ['public'],
            ),
            $abs['properties']['propPublic']
        );
        self::assertArraySubset(
            array(
                'isPromoted' => false,
                'valueFrom' => 'value',
                'visibility' => ['public'],
                // 'value' => 'This property is debug only',
            ),
            $abs['properties']['someArray']
        );
        self::assertArraySubset(
            array(
                'declaredLast' => 'bdk\Test\Debug\Fixture\TestBase',
                'declaredOrig' => 'bdk\Test\Debug\Fixture\TestBase',
                'declaredPrev' => null,
                'isPromoted' => false,
                'value' => 'defined only in TestBase (protected)',
                'valueFrom' => 'value',
                'visibility' => ['protected'],
            ),
            $abs['properties']['propProtected']
        );
        self::assertArraySubset(
            array(
                'attributes' => array(),
                'declaredLast' => 'bdk\Test\Debug\Fixture\TestObj',
                'declaredOrig' => 'bdk\Test\Debug\Fixture\TestBase',
                'declaredPrev' => 'bdk\Test\Debug\Fixture\TestBase',
                'isPromoted' => false,
                'value' => 'redefined in Test (private) (alternate value via __debugInfo)',
                'valueFrom' => 'debugInfo',
                'visibility' => ['private'],
            ),
            $abs['properties']['propPrivate']
        );
        self::assertArraySubset(
            array(
                'attributes' => array(),
                'declaredLast' => 'bdk\Test\Debug\Fixture\TestBase',
                'declaredOrig' => 'bdk\Test\Debug\Fixture\TestBase',
                'declaredPrev' => null,
                'isPromoted' => false,
                'value' => 'defined in TestBase (private)',
                'valueFrom' => 'value',
                'visibility' => ['private'],
            ),
            $abs['properties']['testBasePrivate']
        );
        self::assertArraySubset(
            array(
                'attributes' => array(),
                'declaredLast' => null,
                'declaredOrig' => null,
                'declaredPrev' => null,
                'isPromoted' => false,
                'value' => 'This property is debug only',
                'valueFrom' => 'debugInfo',
            ),
            $abs['properties']['debugValue']
        );

        //    Methods
        self::assertArrayNotHasKey('testBasePrivate', $abs['methods']);
        self::assertTrue($abs['methods']['methodPublic']['isDeprecated']);
        self::assertSame(
            array(
                'attributes' => array(),
                'declaredLast' => 'bdk\Test\Debug\Fixture\TestObj',
                'declaredOrig' => 'bdk\Test\Debug\Fixture\TestObj',
                'declaredPrev' => null,
                'implements' => null,
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
                        'isPassedByReference' => false,
                        'isPromoted' => false,
                        'isVariadic' => false,
                        'name' => 'param1',
                        'type' => 'stdClass',
                    ),
                    array(
                        'attributes' => array(),
                        'defaultValue' => array(),
                        'desc' => 'second param',
                        'isOptional' => true,
                        'isPassedByReference' => false,
                        'isPromoted' => false,
                        'isVariadic' => false,
                        'name' => 'param2',
                        'type' => 'array',
                    ),
                ),
                'phpDoc' => array(
                    'deprecated' => array(
                        array(
                            'desc' => 'this method is bad and should feel bad',
                            'version' => null,
                        ),
                    ),
                    'desc' => '',
                    'summary' => 'This method is public',
                ),
                'return' => array(
                    'desc' => '',
                    'type' => 'void',
                ),
                // 'staticVars' => array()
                'visibility' => 'public',
            ),
            \array_diff_key($abs['methods']['methodPublic'], \array_flip(array('staticVars')))
        );
        self::assertSame(42, $abs['methods']['methodPublic']['staticVars']['foo']);
        self::assertSame('test', $abs['methods']['methodPublic']['staticVars']['bar']);
        self::assertTrue($abs['methods']['methodPublic']['staticVars']['baz']['isRecursion']);
    }

    /**
     * Test Anonymous classes
     */
    public function testAnonymousClass()
    {
        if (PHP_VERSION_ID < 70000) {
            // @requires not working in 4.8.36
            self::markTestSkipped('anonymous classes are a php 7.0 thing');
        }
        $fixtureDir = TEST_DIR . '/Debug/Fixture';
        $filepath = $fixtureDir . '/Anonymous.php';
        $anonymous = require $filepath;
        $line = 40;

        $this->testMethod(
            'log',
            array(
                'anonymous',
                $anonymous['stdClass'],
            ),
            array(
                'chromeLogger' => array(
                    array(
                        'anonymous',
                        array(
                            '___class_name' => 'stdClass@anonymous',
                            '(public) thing' => 'hammer',
                            '(debug) file' => $filepath,
                            '(debug) line' => $line,
                        ),
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-%d: %d|[{"Label":"anonymous","Type":"LOG"},{"___class_name":"stdClass@anonymous","(public) thing":"hammer","(debug) file":"' . $filepath . '","(debug) line":' . $line . '}]|',
                'html' => '<li class="m_log"><span class="no-quotes t_string">anonymous</span> = <div class="groupByInheritance t_object" data-accessible="public"><span class="classname" title="I extend stdClass">stdClass@anonymous</span>
                    <dl class="object-inner">
                    ' . (PHP_VERSION_ID >= 80000
                        ? '<dt class="attributes">attributes</dt>
                            <dd class="attribute"><span class="classname">AnonymousAttribute</span></dd>'
                        : '') . '
                    <dt>extends</dt>
                        <dd class="extends"><span class="classname">stdClass</span></dd>
                    <dt>implements</dt>
                        <dd class="implements">
                        <ul class="list-unstyled">
                        <li><span class="interface toggle-off"><span class="classname">IteratorAggregate</span></span>
                        <ul class="list-unstyled">
                        <li><span class="interface"><span class="classname">Traversable</span></span></li>
                        </ul>
                        </li>
                        </ul>
                        </dd>
                    <dt class="constants">constants</dt>
                        <dd class="constant public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">TWELVE</span> <span class="t_operator">=</span> <span class="t_int">12</span></dd>
                    <dt class="properties">properties</dt>
                        <dd class="property public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">thing</span> <span class="t_operator">=</span> <span class="t_string">hammer</span></dd>
                        <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string">file</span> <span class="t_operator">=</span> <span class="t_string">' . $filepath . '</span></dd>
                        <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">int</span> <span class="no-quotes t_identifier t_string">line</span> <span class="t_operator">=</span> <span class="t_int">' . $line . '</span></dd>
                    <dt class="methods">methods</dt>
                        <dd class="method public" data-implements="IteratorAggregate"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Implements Iterator Aggregate">getIterator</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type"><span class="classname">Traversable</span></span></dd>
                        <dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier" title="Anonymous method">myMethod</span><span class="t_punct">(</span><span class="t_punct">)</span><span class="t_punct t_colon">:</span> <span class="t_type">void</span></dd>
                    </dl>
                    </div></li>',
                'script' => 'console.log("anonymous",{"___class_name":"stdClass@anonymous","(public) thing":"hammer","(debug) file":"' . $filepath . '","(debug) line":' . $line . '});',
                'text' => 'anonymous = stdClass@anonymous
                    Properties:
                    (public) thing = "hammer"
                    (debug) file = "%s/PHPDebugConsole/tests/Debug/Fixture/Anonymous.php"
                    (debug) line = %d
                    Methods:
                    public: 2',
            )
        );

        $this->testMethod(
            'log',
            array(
                'anonymous',
                $anonymous['anonymous'],
            ),
            array(
                'entry' => static function (LogEntry $logEntry) use ($anonymous, $filepath) {
                    $reflector = new ReflectionObject($anonymous['anonymous']);
                    $abs = $logEntry['args'][1];
                    self::assertArraySubset(array(
                        // 'className' => "\x00default\x00",
                        'className' => 'class@anonymous|' . md5($reflector->getName()),
                        'definition' => array(
                            'extensionName' => false,
                            'fileName' => $filepath,
                            'startLine' => 12,
                        ),
                    ), $abs->getInheritedValues());
                    self::assertArraySubset(array(
                        'className' => 'class@anonymous',
                        /*
                        'definition' => array(
                            'extensionName' => false,
                            'fileName' => $filepath,
                            'startLine' => 9,
                        ),
                        */
                    ), $abs->getValues());

                    self::assertSame(array(
                        'A',
                    ), \array_keys($abs['constants']));

                    $propNames = \array_keys($abs['properties']);
                    \sort($propNames);
                    self::assertSame(array(
                        'b',
                        'debug.file',
                        'debug.line',
                    ), $propNames);
                    self::assertSame(array(
                        'anon',
                    ), \array_keys($abs['methods']));
                },
            )
        );

        $this->testMethod(
            'log',
            array(
                'anonymous',
                $anonymous['test1'],
            ),
            array(
                'entry' => static function (LogEntry $logEntry) use ($anonymous, $filepath) {
                    $reflector = new ReflectionObject($anonymous['test1']);
                    $abs = $logEntry['args'][1];

                    self::assertArraySubset(array(
                        'className' => 'bdk\Test\Debug\Fixture\AnonBase@anonymous|' . \md5($reflector->getName()),
                        'definition' => array(
                            'extensionName' => false,
                            'fileName' => $filepath,
                            'startLine' => 69,
                        ),
                    ), $abs->getInheritedValues());

                    self::assertArraySubset(array(
                        'className' => 'bdk\\Test\\Debug\\Fixture\\AnonBase@anonymous',
                        /*
                        'definition' => array(
                            'extensionName' => false,
                            'fileName' => $filepath,
                            'startLine' => 54,
                        ),
                        */
                    ), $abs->getValues());

                    self::assertSame(array(
                        'PI',
                        'ONE',
                    ), \array_keys($abs->getInheritedValues()['constants']));

                    self::assertSame(array(
                        'color',
                        'foo',
                        'pro',
                        'debug.file',
                        'debug.line',
                    ), \array_keys($abs->getInheritedValues()['properties']));

                    self::assertSame(array(
                        'magic',
                        'test',
                        'test1',
                    ), \array_keys($abs->getInheritedValues()['methods']));

                    self::assertArraySubset(
                        array(
                            'value' => 1,
                        ),
                        $abs['constants']['ONE']
                    );
                    self::assertArraySubset(
                        array(
                            'value' => 3.14159265359,
                        ),
                        $abs['constants']['PI']
                    );

                    $propNames = \array_keys($abs->getInstanceValues()['properties']);
                    \sort($propNames);
                    self::assertSame(array(
                        'foo',
                        'pro',
                    ), $propNames);
                    self::assertArraySubset(
                        array(
                            'attributes' => array(),
                            'declaredLast' => 'bdk\Test\Debug\Fixture\AnonBase',
                            'declaredOrig' => 'bdk\Test\Debug\Fixture\AnonBase',
                            'declaredPrev' => null,
                            'isPromoted' => false,
                            'value' => 'bar',
                            'valueFrom' => 'value',
                            'visibility' => ['private'],
                        ),
                        $abs['properties']['foo']
                    );
                    self::assertArraySubset(
                        array(
                            'attributes' => array(),
                            'declaredLast' => 'bdk\Test\Debug\Fixture\AnonBase@anonymous',
                            'declaredOrig' => 'bdk\Test\Debug\Fixture\AnonBase@anonymous',
                            'declaredPrev' => null,
                            'isPromoted' => false,
                            'value' => 'red',
                            'valueFrom' => 'value',
                            'visibility' => ['public'],
                        ),
                        $abs['properties']['color']
                    );

                    self::assertArraySubset(
                        array(
                            'declaredLast' => 'bdk\Test\Debug\Fixture\AnonBase',
                        ),
                        $abs['methods']['test']
                    );
                    self::assertArraySubset(
                        array(
                            'declaredLast' => 'bdk\Test\Debug\Fixture\AnonBase@anonymous',
                        ),
                        $abs['methods']['test1']
                    );
                },
            )
        );

        $this->testMethod(
            'log',
            array(
                'anonymous',
                $anonymous['test2'],
            ),
            array(
                'entry' => static function (LogEntry $logEntry) use ($anonymous, $filepath) {
                    $reflector = new ReflectionObject($anonymous['test2']);
                    $abs = $logEntry['args'][1];

                    self::assertArraySubset(array(
                        'className' => 'bdk\Test\Debug\Fixture\AnonBase@anonymous|' . \md5($reflector->getName()),
                        'definition' => array(
                            'extensionName' => false,
                            'fileName' => $filepath,
                            'startLine' => 80,
                        ),
                    ), $abs->getInheritedValues());
                    self::assertArraySubset(array(
                        'className' => 'bdk\\Test\\Debug\\Fixture\\AnonBase@anonymous',
                        /*
                        'definition' => array(
                            'extensionName' => false,
                            'fileName' => $filepath,
                            'startLine' => 65,
                        ),
                        */
                    ), $abs->getValues());

                    self::assertSame(array(
                        'PI',
                        'ONE',
                        // 'PRIVATE_CONST',
                    ), \array_keys($abs->getInheritedValues()['constants']));
                    self::assertSame(array(
                        'color',
                        'foo',
                        'pro',
                        'debug.file',
                        'debug.line',
                    ), \array_keys($abs->getInheritedValues()['properties']));
                    self::assertSame(array(
                        'test',
                        'test2',
                    ), \array_keys($abs->getInheritedValues()['methods']));
                },
            )
        );
        // anonymous callable tested via ArrayTest
        self::assertSame(array(
            "\x00default\x00",
            'stdClass@anonymous|' . \md5((new ReflectionObject($anonymous['stdClass']))->getName()),
            'class@anonymous|' . \md5((new ReflectionObject($anonymous['anonymous']))->getName()),
            'bdk\Test\Debug\Fixture\AnonBase@anonymous|' . \md5((new ReflectionObject($anonymous['test1']))->getName()),
            'bdk\Test\Debug\Fixture\AnonBase@anonymous|' . \md5((new ReflectionObject($anonymous['test2']))->getName()),
        ), \array_keys($this->debug->data->get('classDefinitions')));
    }

    public function testDateTime()
    {
        $dateTime = new \DateTime();
        $this->testMethod(
            'log',
            array(
                'dateTime',
                $dateTime,
            ),
            array(
                'entry' => static function (LogEntry $logEntry) use ($dateTime) {
                    // Note:  DateTime in PHP < 7.4 has public properties!
                    $abs = $logEntry['args'][1];
                    self::assertSame($dateTime->format(\DateTime::ISO8601), $abs['stringified']);
                },
                'html' => static function ($htmlActual) use ($dateTime) {
                    self::assertStringContainsString('<li class="m_log"><span class="no-quotes t_string">dateTime</span> = <div class="groupByInheritance t_object" data-accessible="public"><span class="t_string t_stringified">' . $dateTime->format(\DateTime::ISO8601) . '</span>', $htmlActual);
                },
            )
        );
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
                'entry' => static function (LogEntry $entry) {
                    $arg0 = $entry['args'][0];
                    self::assertSame('DOMDocument', $arg0['className']);

                    $arg1 = $entry['args'][1];
                    self::assertSame('DOMElement', $arg1['className']);
                    // echo json_encode($this->helper->logEntryToArray($entry), JSON_PRETTY_PRINT) . "\n";
                },
            )
        );
    }

    public function testInterface()
    {
        $interface = 'bdk\HttpMessage\ServerRequestExtendedInterface';
        $abs = $this->debug->abstracter->abstractObject->getAbstraction($interface);
        $this->testMethod(
            'log',
            array(
                $abs,
            ),
            array(
                'entry' => static function (LogEntry $logEntry) {
                    $abs = $logEntry['args'][0];
                    self::assertSame(array(
                        'Psr\Http\Message\ServerRequestInterface' => array(
                            'Psr\Http\Message\RequestInterface' => array(
                                'Psr\Http\Message\MessageInterface',
                            ),
                        ),
                    ), $abs['extends']);
                },
                'html' => static function ($html) {
                    $expect = '<dt>extends</dt>
                        <dd class="extends">
                            <ul class="list-unstyled">
                            <li><span class="extends"><span class="classname"><span class="namespace">Psr\Http\Message\</span>ServerRequestInterface</span></span>
                                <ul class="list-unstyled">
                                <li><span class="extends"><span class="classname"><span class="namespace">Psr\Http\Message\</span>RequestInterface</span></span>
                                        <ul class="list-unstyled">
                                    <li><span class="extends"><span class="classname"><span class="namespace">Psr\Http\Message\</span>MessageInterface</span></span></li>
                                    </ul>
                                </li>
                                </ul>
                            </li>
                            </ul>
                        </dd>';
                    $expect = \preg_replace('/^\s+/m', '', $expect);
                    self::assertStringContainsString($expect, $html);
                    self::assertStringNotContainsString('<dt>implements</dt>', $html);
                },
            )
        );
    }

    /**
     * Test Promoted Params
     */
    public function testPromotedParam()
    {
        if (PHP_VERSION_ID < 80000) {
            // @requires not working in 4.8.36
            self::markTestSkipped('promoted params are a php 8.0 thing');
        }
        $test = new \bdk\Test\Debug\Fixture\Php80(42);
        $this->testMethod(
            'log',
            array(
                $test,
            ),
            array(
                'entry' => static function (LogEntry $logEntry) {
                    $abs = $logEntry['args'][0];
                    self::assertTrue($abs['properties']['arg1']['isPromoted']);
                    self::assertTrue($abs['methods']['__construct']['params'][0]['isPromoted']);
                    self::assertSame('Attributed &amp; promoted param', $abs['properties']['arg1']['phpDoc']['summary']);
                },
                'html' => static function ($html) {
                    $propExpect = \str_replacE('\\', '\\\\', '<dd class="isPromoted property public" data-attributes="[{&quot;arguments&quot;:[],&quot;name&quot;:&quot;bdk\\Test\\Debug\\Fixture\\ExampleParamAttribute&quot;}]"><span class="t_modifier_public">public</span> <span class="t_type">int</span> <span class="no-quotes t_identifier t_string" title="Attributed &amp;amp; promoted param">arg1</span> <span class="t_operator">=</span> <span class="t_int">42</span></dd>');
                    $methExpect = \str_replace('\\', '\\\\', '<span class="isPromoted parameter" data-attributes="[{&quot;arguments&quot;:[],&quot;name&quot;:&quot;bdk\\Test\\Debug\\Fixture\\ExampleParamAttribute&quot;}]"><span class="t_type">int</span> <span class="t_parameter-name" title="Attributed &amp;amp; promoted param">$arg1</span></span>');
                    $attrExpect = '<dt class="attributes">attributes</dt>' . "\n"
                        . '<dd class="attribute"><span class="classname"><span class="namespace">bdk\\Test\\Debug\\Fixture\\</span>ExampleClassAttribute</span><span class="t_punct">(</span><span class="t_string">foo</span><span class="t_punct">,</span> <span class="t_int">' . PHP_VERSION_ID . '</span><span class="t_punct">,</span> <span class="t_parameter-name">name</span><span class="t_punct t_colon">:</span><span class="t_string">bar</span><span class="t_punct">)</span></dd>';
                    self::assertStringContainsString($propExpect, $html);
                    self::assertStringContainsString($methExpect, $html);
                    self::assertStringContainsString($attrExpect, $html);
                },
            )
        );
    }

    /**
     * Test Php 8.1 features
     */
    public function testPhp81()
    {
        if (PHP_VERSION_ID < 80100) {
            // @requires not working in 4.8.36
            self::markTestSkipped('Test requires Php >= 8.1');
        }
        $this->testMethod(
            'log',
            array(
                new \bdk\Test\Debug\Fixture\Php81(42),
            ),
            array(
                'entry' => static function (LogEntry $logEntry) {
                    $abs = $logEntry['args'][0];
                    self::assertTrue($abs['properties']['title']['isReadOnly']);
                    self::assertTrue($abs['constants']['FINAL_CONST']['isFinal']);

                    self::assertTrue($abs['methods']['__construct']['params'][0]['isPromoted']);
                    // self::assertSame('Attributed & promoted param', $abs['properties']['arg1']['desc']);
                },
                'html' => static function ($html) {
                    $constExpect = '<dd class="constant isFinal public"><span class="t_modifier_final">final</span> <span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">FINAL_CONST</span> <span class="t_operator">=</span> <span class="t_string">foo</span></dd>';
                    self::assertStringContainsString($constExpect, $html);

                    $propExpect = '<dd class="isPromoted isReadOnly property ' . (PHP_VERSION_ID >= 80400 ? 'protected-set ' : '') . 'public">'
                        . '<span class="t_modifier_public">public</span> '
                        . (PHP_VERSION_ID >= 80400 ? '<span class="t_modifier_protected-set">protected(set)</span> ' : '' )
                        . '<span class="t_modifier_readonly">readonly</span> '
                        . '<span class="t_type">string</span> <span class="no-quotes t_identifier t_string">title</span> <span class="t_operator">=</span> <span class="t_string" data-type-more="numeric">42</span></dd>';
                    self::assertStringContainsString($propExpect, $html);
                },
            )
        );
    }

    /**
     * Test Php 8.2 features
     */
    public function testPhp82()
    {
        if (PHP_VERSION_ID < 80200) {
            self::markTestSkipped('Test requires Php >= 8.2');
        }
        $this->testMethod(
            'log',
            array(
                new \bdk\Test\Debug\Fixture\Php82readonly(
                    'look, but don\'t touch',
                    'active',
                    \time()
                ),
            ),
            array(
                'entry' => static function (LogEntry $logEntry) {
                    $abs = $logEntry['args'][0];
                    self::assertTrue($abs['isReadOnly']);
                    self::assertCount(4, $abs['properties']);
                    \array_walk($abs['properties'], static function ($propInfo) {
                        self::assertTrue($propInfo['isReadOnly']);
                    });
                },
                'html' => static function ($html) {
                    self::assertStringContainsString(
                        '<dt class="modifiers">modifiers</dt>' . "\n"
                            . '<dd class="t_modifier_final">final</dd>' . "\n"
                            . '<dd class="t_modifier_readonly">readonly</dd>',
                        $html
                    );
                    self::assertStringContainsString(
                        '<dd class="isPromoted isReadOnly property ' . (PHP_VERSION_ID >= 80400 ? 'protected-set ' : '') . 'public">'
                            . '<span class="t_modifier_public">public</span> '
                            . (PHP_VERSION_ID >= 80400 ? '<span class="t_modifier_protected-set">protected(set)</span> ' : '')
                            . '<span class="t_modifier_readonly">readonly</span> '
                            . '<span class="t_type">string</span> <span class="no-quotes t_identifier t_string" title="$status">status</span> <span class="t_operator">=</span> <span class="t_string">active</span></dd>',
                        $html
                    );
                },
            )
        );
    }

    /**
     * Test Attributes
     */
    public function testAttributes()
    {
        if (PHP_VERSION_ID < 80000) {
            // @requires not working in 4.8.36
            self::markTestSkipped('attributes classes are a php 8.0 thing');
        }
        $test = new \bdk\Test\Debug\Fixture\Php80(42);
        $this->testMethod(
            'log',
            array(
                $test,
            ),
            array(
                'entry' => static function (LogEntry $logEntry) {
                    $abs = $logEntry['args'][0];
                    $attribNamespace = 'bdk\\Test\\Debug\\Fixture\\';
                    self::assertSame(
                        array(
                            array(
                                'arguments' => array(
                                    'foo',
                                    PHP_VERSION_ID,
                                    'name' => 'bar',
                                ),
                                'name' =>  $attribNamespace . 'ExampleClassAttribute',
                            ),
                        ),
                        $abs['attributes']
                    );
                    self::assertSame(
                        array(
                            array(
                                'arguments' => array(),
                                'name' =>  $attribNamespace . 'ExampleConstAttribute',
                            ),
                        ),
                        $abs['constants']['FOO']['attributes']
                    );
                    self::assertSame(
                        array(
                            array(
                                'arguments' => array(),
                                'name' =>  $attribNamespace . 'ExampleMethodAttribute',
                            ),
                        ),
                        $abs['methods']['__construct']['attributes']
                    );
                    self::assertSame(
                        array(
                            array(
                                'arguments' =>  array(),
                                'name' =>  $attribNamespace . 'ExampleParamAttribute',
                            ),
                        ),
                        $abs['methods']['__construct']['params'][0]['attributes']
                    );
                    self::assertSame(
                        // this property (and it's attributes came via parameter promotion)
                        array(
                            array(
                                'arguments' =>  array(),
                                'name' =>  $attribNamespace . 'ExampleParamAttribute',
                            ),
                        ),
                        $abs['properties']['arg1']['attributes']
                    );
                    self::assertSame(
                        array(
                            array(
                                'arguments' =>  array(),
                                'name' =>  $attribNamespace . 'ExamplePropAttribute',
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
                'entry' => static function (LogEntry $logEntry) {
                    self::assertTrue($logEntry['args'][0]['isFinal']);
                },
                'html' => array(
                    'contains' => '<dt class="modifiers">modifiers</dt>' . "\n"
                        . '<dd class="t_modifier_final">final</dd>' . "\n",
                ),
            )
        );
    }

    public function testVariadic()
    {
        if (\version_compare(PHP_VERSION, '5.6', '<')) {
            self::markTestSkipped('variadic params are a php 5.6 thing');
        }
        $testVar = new \bdk\Test\Debug\Fixture\TestVariadic();
        $abs = $this->debug->abstracter->getAbstraction($testVar);
        self::assertSame(array(
            'attributes' => array(),
            'defaultValue' => Abstracter::UNDEFINED,
            'desc' => 'variadic param (PHP 5.6)',
            'isOptional' => true,
            'isPassedByReference' => false,
            'isPromoted' => false,
            'isVariadic' => true,
            'name' => 'moreParams',
            'type' => 'mixed',
        ), $abs['methods']['methodVariadic']['params'][1]);
    }

    public function testVariadicByReference()
    {
        if (\version_compare(PHP_VERSION, '5.6', '<')) {
            self::markTestSkipped('variadic params are a php 5.6 thing');
        }
        if (\defined('HHVM_VERSION')) {
            self::markTestSkipped('variadic params are a php thing');
        }
        $testVarByRef = new \bdk\Test\Debug\Fixture\TestVariadicByReference();
        $abs = $this->debug->abstracter->getAbstraction($testVarByRef);
        self::assertSame(array(
            'attributes' => array(),
            'defaultValue' => Abstracter::UNDEFINED,
            'desc' => 'variadic param by reference (PHP 5.6)',
            'isOptional' => true,
            'isPassedByReference' => true,
            'isPromoted' => false,
            'isVariadic' => true,
            'name' => 'moreParams',
            'type' => 'mixed',
        ), $abs['methods']['methodVariadicByReference']['params'][1]);
    }

    public function testCollectPropertyValues()
    {
        $callable = static function (Abstraction $abs) {
            $abs['collectPropertyValues'] = false;
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);
        $abs = $this->debug->abstracter->getAbstraction((object) array('foo' => 'bar'));
        $this->debug->eventManager->unsubscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);

        self::assertSame(Abstracter::UNDEFINED, $abs['properties']['foo']['value']);
    }

    public function testPropertyOverrideValues()
    {
        $callable = static function (Abstraction $abs) {
            $abs['propertyOverrideValues']['foo'] = 'new value';
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);
        $abs = $this->debug->abstracter->getAbstraction((object) array('foo' => 'bar'));
        $this->debug->eventManager->unsubscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);

        self::assertSame('new value', $abs['properties']['foo']['value']);
        self::assertSame('debug', $abs['properties']['foo']['valueFrom']);

        $callable = static function (Abstraction $abs) {
            $abs['propertyOverrideValues']['foo'] = array(
                'desc' => 'I describe foo',
                'value' => 'new value',
            );
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);
        $abs = $this->debug->abstracter->getAbstraction((object) array('foo' => 'bar'));
        $this->debug->eventManager->unsubscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);

        self::assertSame('I describe foo', $abs['properties']['foo']['desc']);
        self::assertSame('new value', $abs['properties']['foo']['value']);
        self::assertSame('debug', $abs['properties']['foo']['valueFrom']);

        // __debugInfo + override
        $test = new TestObj();
        $callable = static function (Abstraction $abs) {
            $abs['propertyOverrideValues']['propPrivate'] = 'new value';
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);
        $abs = $this->debug->abstracter->getAbstraction($test);
        $this->debug->eventManager->unsubscribe(Debug::EVENT_OBJ_ABSTRACT_START, $callable);

        self::assertSame('new value', $abs['properties']['propPrivate']['value']);
        self::assertSame('debug', $abs['properties']['propPrivate']['valueFrom']);
    }

    /**
     * test handling __debugInfo magic method
     *
     * @return void
     */
    public function testDebugInfo()
    {
        $test = new TestObj();
        $this->debug->log('test', $test);
        $abs = $this->debug->data->get('log/0/args/1');
        $props = $abs['properties'];
        self::assertArrayNotHasKey('propHidden', $props, 'propHidden shouldn\'t be debugged');
        // debugValue
        self::assertSame('This property is debug only', $props['debugValue']['value']);
        self::assertEquals(['debug'], $props['debugValue']['visibility']);
        // propPrivate
        self::assertStringEndsWith('(alternate value via __debugInfo)', $props['propPrivate']['value']);
        self::assertSame('debugInfo', $props['propPrivate']['valueFrom']);
    }

    public function testMaxDepth()
    {
        $this->testMethod(
            'log',
            array(
                'array',
                array(
                    'foo' => 'bar',
                    'tooDeep' => (object) array('beans'),
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
                            'tooDeep' => \array_diff_key($this->debug->abstracter->crateWithVals(
                                (object) array('beans'),
                                array(
                                    'debugMethod' => 'log',
                                    'isMaxDepth' => true,
                                )
                            )->jsonSerialize(), \array_flip(array('properties'))),
                            'ding' => 'dong',
                        ),
                    ),
                    'meta' => array(),
                ),
                'html' => '<li class="m_log"><span class="no-quotes t_string">array</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                    <ul class="array-inner list-unstyled">
                    <li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">bar</span></li>
                    <li><span class="t_key">tooDeep</span><span class="t_operator">=&gt;</span><div class="t_object"><span class="classname">stdClass</span>
                        <span class="t_maxDepth">*MAX DEPTH*</span></div></li>
                    <li><span class="t_key">ding</span><span class="t_operator">=&gt;</span><span class="t_string">dong</span></li>
                    </ul><span class="t_punct">)</span></span></li>',
                'script' => 'console.log("array",{"foo":"bar","tooDeep":"(object) stdClass *MAX DEPTH*","ding":"dong"});',
                'streamAnsi' => \str_replace('\e', "\e", 'array \e[38;5;245m=\e[0m \e[38;5;45marray\e[38;5;245m(\e[0m' . "\n"
                    . '\e[38;5;245m[\e[38;5;83mfoo\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0mbar\e[38;5;250m"\e[0m' . "\n"
                    . '\e[38;5;245m[\e[38;5;83mtooDeep\e[38;5;245m]\e[38;5;224m => \e[0m\e[1mstdClass\e[22m \e[38;5;196m*MAX DEPTH*\e[0m' . "\n"
                    . '\e[38;5;245m[\e[38;5;83mding\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0mdong\e[38;5;250m"\e[0m' . "\n"
                    . '\e[38;5;245m)\e[0m'),
                'text' => 'array = array(
                    [foo] => "bar"
                    [tooDeep] => stdClass *MAX DEPTH*
                    [ding] => "dong"
                    )',
            )
        );
    }

    /**
     * v 1.0 = fatal error
     *
     * @return void
     */
    public function testRecursiveObjectProp1()
    {
        $test = new TestObj();
        $test->propPublic = array();
        $test->propPublic[] = &$test->propPublic;
        $this->debug->log('test', $test);
        $abstraction = $this->debug->data->get('log/0/args/1');
        self::assertEquals(
            Abstracter::RECURSION,
            $abstraction['properties']['propPublic']['value'][0],
            'Did not find expected recursion'
        );
        $output = $this->debug->output();
        $select = '.m_log
            > .t_object > .object-inner
            > .property
            > .t_array .array-inner > li
            > .t_array
            > .t_recursion';
        self::assertSelectCount($select, 1, $output);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRecursiveObjectProp2()
    {
        $test = new TestObj();
        $test->propPublic = &$test;
        $this->debug->log('test', $test);
        $abstraction = $this->debug->data->get('log/0/args/1');
        self::assertEquals(
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
        $test = new TestObj();
        $test->propPublic = array(&$test);
        $this->debug->log('test', $test);
        $abstraction = $this->debug->data->get('log/0/args/1');
        self::assertEquals(
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
        $testOa = new TestObj();
        $testOb = new TestObj();
        $testOa->propPublic = 'this is object a';
        $testOb->propPublic = 'this is object b';
        $testOa->ob = $testOb;
        $testOb->oa = $testOa;
        $this->debug->log('test_oa', $testOa);
        $abstraction = $this->debug->data->get('log/0/args/1');
        self::assertEquals(
            true,
            $abstraction['properties']['ob']['value']['properties']['oa']['value']['isRecursion'],
            'Did not find expected recursion'
        );
        $this->debug->output();
    }

    public function testScope()
    {
        $obj = new \bdk\Test\Debug\Fixture\ObjectScope();
        $obj->callsDebug();
        $logEntry = $this->debug->data->get('log/__end__');
        $abs = $logEntry['args'][0];
        self::assertSame($abs['className'], $abs['scopeClass']);
    }
}

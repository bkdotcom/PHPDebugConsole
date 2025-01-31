<?php

namespace bdk\Test\Debug;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;
use bdk\Test\Debug\Helper;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Dump\AbstractValue
 * @covers \bdk\Debug\Dump\Base\Value
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\Object\AbstractSection
 * @covers \bdk\Debug\Dump\Html\Value
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\Text\Value
 * @covers \bdk\Debug\Dump\TextAnsi\Value
 * @covers \bdk\Debug\Abstraction\Abstracter
 * @covers \bdk\Debug\Abstraction\Abstraction
 * @covers \bdk\Debug\Abstraction\AbstractArray
 *
 * phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class CharReplacementAndSanitizationTest extends DebugTestFramework
{
    public static function providerTestMethod()
    {
        $tests = array(
            'confusableIdentifiers obj' => array(
                'log',
                array(
                    new \bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers(),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $actual = Helper::deObjectifyData($logEntry['args'][0]->getValues(), true, false, true);
                        $expect = require __DIR__ . '/data/ConfusableIdentifiers.php';
                        // \bdk\Debug::varDump('expect', $expect);
                        // \bdk\Debug::varDump('actual', $actual);
                        self::assertSame($expect, $actual);
                    },
                    'chromeLogger' => array(
                        array(
                            array(
                                '___class_name' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
                                '(public) array' => array(
                                    'int' => 42,
                                    'password' => 'secret',
                                    'poop' => '💩',
                                    'string' => 'str\u{0131}ngy' . "\n" . 'string',
                                    'ctrl chars and whatnot' => '\u{feff}bom' . "\r\n\t" . '\x07 \x1f \x7f \x00 \u{00a0}<i>(nbsp)</i> \u{2009}(thsp), & \u{200b}(zwsp)',
                                    'n\u{03bf}n\x80utf8' => 'test',
                                ),
                                '(public) \u{0581}\u{1d0f}\u{0251}t' => 'moun\u{1d42d}ain',
                            ),
                        ),
                        null,
                        '',
                    ),
                    'html' => static function ($html) {
                        $expect = require __DIR__ . '/data/ConfusableIdentifiers_html.php';
                        // echo 'expect: ' . $expect . "\n\n";
                        // echo 'actual: ' . $html . "\n\n";
                        self::assertStringMatchesFormatNormalized($expect, $html);
                    },
                    'text' => 'bdk\Test\Debug\Fixture\Con\u{1d627}usableIdenti\u{1d627}iers
                        Properties:
                          (public) array = array(
                            [int] => 42
                            [password] => "secret"
                            [poop] => "💩"
                            [string] => "str\u{0131}ngy
                        string"
                            [ctrl chars and whatnot] => "\u{feff}bom[\r]
                                \x07 \x1f \x7f \x00 \u{00a0}<i>(nbsp)</i> \u{2009}(thsp), & \u{200b}(zwsp)"
                            [n\u{03bf}n\x80utf8] => "test"
                          )
                          (public) \u{0581}\u{1d0f}\u{0251}t = "moun\u{1d42d}ain"
                        Methods:
                          public: 6
                          magic: 1
                    ',
                    'script' => \preg_replace('/\\\\([^rnt])/', '\\\\\\\\$1', 'console.log({"___class_name":"bdk\\Test\\Debug\\Fixture\\Con𝘧usableIdenti𝘧iers","(public) array":{"int":42,"password":"secret","poop":"💩","string":"str\\u{0131}ngy\nstring","ctrl chars and whatnot":"\\u{feff}bom\r\n\t\\x07 \\x1f \\x7f \\x00 \\u{00a0}<i>(nbsp)</i> \\u{2009}(thsp), & \\u{200b}(zwsp)","n\\u{03bf}n\\x80utf8":"test"},"(public) \\u{0581}\\u{1d0f}\\u{0251}t":"moun\\u{1d42d}ain"});'),
                    'streamAnsi' => \str_replace('\e', "\e", '
                        \e[38;5;250mbdk\Test\Debug\Fixture\\\e[0m\e[1mCon\e[34;48;5;14m𝘧\e[0;1musableIdenti\e[34;48;5;14m𝘧\e[0;1miers\e[22m
                        \e[4mProperties:\e[24m
                            \e[38;5;250m(public)\e[0m \e[38;5;83marray\e[0m \e[38;5;224m=\e[0m \e[38;5;45marray\e[38;5;245m(\e[0m
                                \e[38;5;245m[\e[38;5;83mint\e[38;5;245m]\e[38;5;224m => \e[0m\e[96m42\e[0m
                                \e[38;5;245m[\e[38;5;83mpassword\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0msecret\e[38;5;250m"\e[0m
                                \e[38;5;245m[\e[38;5;83mpoop\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0m💩\e[38;5;250m"\e[0m
                                \e[38;5;245m[\e[38;5;83mstring\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0mstr\e[34;48;5;14mı\e[0mngy
                                string\e[38;5;250m"\e[0m
                                \e[38;5;245m[\e[38;5;83mctrl chars and whatnot\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0m\e[34;48;5;14m\u{feff}\e[0mbom[\r]
                                \e[34;48;5;14m\x07\e[0m \e[34;48;5;14m\x1f\e[0m \e[34;48;5;14m\x7f\e[0m \e[34;48;5;14m\x00\e[0m \e[34;48;5;14m\u{00a0}\e[0m<i>(nbsp)</i> \e[34;48;5;14m\u{2009}\e[0m(thsp), & \e[34;48;5;14m\u{200b}\e[0m(zwsp)\e[38;5;250m"\e[0m
                                \e[38;5;245m[\e[38;5;83mn\e[34;48;5;14mο\e[38;5;83;49mn\e[30;48;5;250m80\e[38;5;83;49mutf8\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250m"\e[0mtest\e[38;5;250m"\e[0m
                                \e[38;5;245m)\e[0m
                            \e[38;5;250m(public)\e[0m \e[38;5;83m\e[34;48;5;14mց\e[38;5;83;49m\e[34;48;5;14mᴏ\e[38;5;83;49m\e[34;48;5;14mɑ\e[38;5;83;49mt\e[0m \e[38;5;224m=\e[0m \e[38;5;250m"\e[0mmoun\e[34;48;5;14m𝐭\e[0main\e[38;5;250m"\e[0m
                        \e[4mMethods:\e[24m
                            public\e[38;5;245m: \e[96m6\e[0m
                            magic\e[38;5;245m: \e[96m1\e[0m
                    '),
                    'wamp' => static function (array $messages, LogEntry $logEntry) {
                        $messages = \array_map(static function ($message) {
                            $methodArgsMeta = $message['args'];
                            return \array_slice($methodArgsMeta, 0, 2);
                        }, \array_slice($messages, 1));
                        $definition = $logEntry->getSubject()->data->get('classDefinitions.bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers')->jsonSerialize();
                        $definition = Helper::deObjectifyData($definition);
                        $definition = \array_diff_key($definition, \array_flip([
                            // the act of dumping and checking for these options has side effect of adding values
                            'addQuotes',
                            'attribs',
                            'charHighlight',
                            'charReplace',
                            'detectFiles',
                            'errorCat',
                            'glue',
                            'icon',
                            'interfacesCollapse',
                            'level',
                            'options',
                            'postDump',
                            'sanitize',
                            'sanitizeFirst',
                            'scopeClass',
                            'tagName',
                            // 'typeMore',
                            'uncollapse',
                            'visualWhiteSpace',
                            'what',
                        ]));
                        $definition['properties']['array']['value']['value']['__debug_key_order__'] = [
                            'int',
                            'password',
                            'poop',
                            'string',
                            'ctrl chars and whatnot',
                            'af8af85a7694926703b9690c2eb6d1fc',
                        ];
                        $expect = array(
                            array(
                                'meta',
                                array(
                                    array(
                                        'classDefinitions' => array(
                                            'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers' => $definition,
                                        ),
                                    ),
                                ),
                            ), // meta log entry
                            array(
                                'log',
                                array(
                                    array(
                                        'cfgFlags' => 29360127,
                                        'debugMethod' => 'log',
                                        'interfacesCollapse' => array(),
                                        'isLazy' => false,
                                        'isMaxDepth' => false,
                                        'isRecursion' => false,
                                        'properties' => array(
                                            'array' => array(
                                                'value' => array(
                                                    'debug' => Abstracter::ABSTRACTION,
                                                    'keys' => array(
                                                        'af8af85a7694926703b9690c2eb6d1fc' => array(
                                                            'brief' => false,
                                                            'chunks' => array(
                                                                array('utf8', 'nοn'),
                                                                array('other', '80'),
                                                                array('utf8', 'utf8'),
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
                                                        'int' => 42,
                                                        'password' => 'secret',
                                                        'poop' => '💩',
                                                        'string' => "strıngy\nstring",
                                                        'ctrl chars and whatnot' => "\xef\xbb\xbfbom\r\n\t\x07 \x1F \x7F \x00 \xc2\xa0<i>(nbsp)</i> \xE2\x80\x89(thsp), & \xE2\x80\x8B(zwsp)",
                                                        'af8af85a7694926703b9690c2eb6d1fc' => 'test',
                                                        '__debug_key_order__' => array(
                                                            'int',
                                                            'password',
                                                            'poop',
                                                            'string',
                                                            'ctrl chars and whatnot',
                                                            'af8af85a7694926703b9690c2eb6d1fc',
                                                        ),
                                                    ),
                                                ),
                                            ),
                                        ), // properties
                                        'scopeClass' => "bdk\Test\Debug\CharReplacementAndSanitizationTest",
                                        'methods' => array(
                                            '__toString' => array(
                                                'returnValue' => 'Thiꮪ <b>is</b> a string',
                                            ),
                                        ),
                                        'debug' => Abstracter::ABSTRACTION,
                                        'inheritsFrom' => 'bdk\Test\Debug\Fixture\Con𝘧usableIdenti𝘧iers',
                                        'isLazy' => false,
                                        'type' => Type::TYPE_OBJECT,
                                        // 'typeMore' => null,
                                    ), // abstraction
                                ), // args
                            ), // logentry
                        );
                        // \bdk\Debug::varDump('expect', $expect);
                        // \bdk\Debug::varDump('actual', $messages);
                        self::assertEquals($expect, $messages);
                    }, // wamp test closure
                ),
            ), // confusableIdentifiers obj
        );
        return $tests;
    }
}

<?php

namespace bdk\Test\Debug\Type;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Abstraction\Abstracter
 * @covers \bdk\Debug\Abstraction\AbstractString
 * @covers \bdk\Debug\Dump\BaseValue
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\HtmlString
 * @covers \bdk\Debug\Dump\Html\Value
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\TextAnsi
 * @covers \bdk\Debug\Dump\TextAnsiValue
 * @covers \bdk\Debug\Dump\TextValue
 */
class BasicTest extends DebugTestFramework
{
    public static function providerTestMethod()
    {
        $ts = \time();
        $datetime = \gmdate(self::DATETIME_FORMAT, $ts);
        $test = new \bdk\Test\Debug\Fixture\TestObj();

        $fhOpen = \fopen(__FILE__, 'r');
        $resourceOpenEntryExpect = array(
            'method' => 'log',
            'args' => array(
                array(
                    'debug' => Abstracter::ABSTRACTION,
                    'type' => Abstracter::TYPE_RESOURCE,
                    'value' => \print_r($fhOpen, true) . ': stream',
                ),
            ),
            'meta' => array(),
        );

        $fhClosed = \fopen(__FILE__, 'r');
        $resourceClosedEntryExpect = array(
            'method' => 'log',
            'args' => array(
                array(
                    'debug' => Abstracter::ABSTRACTION,
                    'type' => Abstracter::TYPE_RESOURCE,
                    'value' => \print_r($fhClosed, true) . ': stream',
                ),
            ),
            'meta' => array(),
        );


        return array(
            'bool.true' => array(
                'log',
                array(true),
                array(
                    'chromeLogger' => '[[true],null,""]',
                    'html' => '<li class="m_log"><span class="t_bool" data-type-more="true">true</span></li>',
                    'script' => 'console.log(true);',
                    'streamAnsi' => "\e[32mtrue\e[0m",
                    'text' => 'true',
                    'wamp' => array(
                        'log',
                        array(true),
                    ),
                )
            ),
            'bool.false' => array(
                'log',
                array(false),
                array(
                    'chromeLogger' => '[[false],null,""]',
                    'html' => '<li class="m_log"><span class="t_bool" data-type-more="false">false</span></li>',
                    'script' => 'console.log(false);',
                    'streamAnsi' => "\e[91mfalse\e[0m",
                    'text' => 'false',
                    'wamp' => array(
                        'log',
                        array(false),
                    ),
                )
            ),

            'callable' => array(
                'log',
                array(array($test,'testBaseStatic')),
                array(
                    'chromeLogger' => '[["callable: bdk\\\Test\\\Debug\\\Fixture\\\TestObj::testBaseStatic"],null,""]',
                    'html' => '<li class="m_log"><span class="t_callable"><span class="t_type">callable</span> <span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestObj</span><span class="t_operator">::</span><span class="t_identifier">testBaseStatic</span></span></li>',
                    'script' => 'console.log("callable: bdk\\\Test\\\Debug\\\Fixture\\\TestObj::testBaseStatic");',
                    'streamAnsi' => "callable: \e[38;5;250mbdk\Test\Debug\Fixture\\\e[0m\e[1mTestObj\e[22m\e[38;5;130m::\e[0m\e[1mtestBaseStatic\e[22m",
                    'text' => 'callable: bdk\Test\Debug\Fixture\TestObj::testBaseStatic',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Abstracter::TYPE_CALLABLE,
                                'value' => array(
                                    'bdk\Test\Debug\Fixture\TestObj',
                                    'testBaseStatic',
                                ),
                            ),
                        ),
                    ),
                ),
            ),

            'classname' => array(
                'log',
                array(
                    Debug::_getInstance()->abstracter->crateWithVals(
                        'SomeNamespace\Classname',
                        array('typeMore' => Abstracter::TYPE_STRING_CLASSNAME)
                    ),
                ),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'brief' => false,
                                'strlen' => null,
                                'type' => Abstracter::TYPE_STRING,
                                'typeMore' => Abstracter::TYPE_STRING_CLASSNAME,
                                'value' => 'SomeNamespace\Classname',
                            )
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="classname no-quotes t_string" data-type-more="classname"><span class="namespace">SomeNamespace\</span>Classname</span></li>',
                    'text' => 'SomeNamespace\Classname',
                ),
            ),

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
                                        'visibility' => 'debug',
                                    ), $values);
                                    break;
                                case 'debug.line':
                                    self::assertSame(array(
                                        'value' => $line,
                                        'valueFrom' => 'debug',
                                        'visibility' => 'debug',
                                    ), $values);
                                    break;
                            }
                        });
                    },
                    'html' => '<li class="m_log"><div class="t_object" data-accessible="public"><span class="classname">Closure</span>
                        <dl class="object-inner">
                        <dt class="t_modifier_final">final</dt>
                        <dt class="properties">properties</dt>
                        <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">string</span> <span class="t_identifier">file</span> <span class="t_operator">=</span> <span class="t_string">' . __FILE__ . '</span></dd>
                        <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">int</span> <span class="t_identifier">line</span> <span class="t_operator">=</span> <span class="t_int">%d</span></dd>
                        <dt class="methods">methods</dt>
                        <dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier">__invoke</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$foo</span></span><span class="t_punct">,</span> <span class="parameter"><span class="t_parameter-name">$bar</span></span><span class="t_punct">)</span></dd>
                        %a
                        <dd class="method private"><span class="t_modifier_private">private</span> <span class="t_identifier">__construct</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>
                        </dl>
                        </div></li>',
                ),
            ),

            'float' => array(
                'log',
                array(10.10),
                array(
                    'chromeLogger' => '[[10.1],null,""]',
                    'html' => '<li class="m_log"><span class="t_float">10.1</span></li>',
                    'script' => 'console.log(10.1);',
                    'streamAnsi' => "\e[96m10.1\e[0m",
                    'text' => '10.1',
                    'wamp' => array(
                        'log',
                        array(10.1),
                    ),
                ),
            ),
            'float.inf' => array(
                'log',
                array(INF),
                array(
                    'chromeLogger' => '[["INF"],null,""]',
                    'html' => '<li class="m_log"><span class="t_float" data-type-more="inf">INF</span></li>',
                    'script' => 'console.log(Infinity);',
                    'streamAnsi' =>  "\e[96mINF\e[0m",
                    'text' => 'INF',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Abstracter::TYPE_FLOAT,
                                'typeMore' => Abstracter::TYPE_FLOAT_INF,
                                'value' => Abstracter::TYPE_FLOAT_INF,
                            ),
                        ),
                    ),
                ),
            ),
            'float.NaN' => array(
                'log',
                array(NAN),
                array(
                    'chromeLogger' => '[["NaN"],null,""]',
                    'html' => '<li class="m_log"><span class="t_float" data-type-more="nan">NaN</span></li>',
                    'script' => 'console.log(NaN);',
                    'streamAnsi' => "\e[96mNaN\e[0m",
                    'text' => 'NaN',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Abstracter::TYPE_FLOAT,
                                'typeMore' => Abstracter::TYPE_FLOAT_NAN,
                                'value' => Abstracter::TYPE_FLOAT_NAN,
                            ),
                        ),
                    ),
                ),
            ),

            'int' => array(
                'log',
                array(10),
                array(
                    'chromeLogger' => '[[10],null,""]',
                    'html' => '<li class="m_log"><span class="t_int">10</span></li>',
                    'script' => 'console.log(10);',
                    'streamAnsi' => "\e[96m10\e[0m",
                    'text' => '10',
                    'wamp' => array(
                        'log',
                        array(10),
                    ),
                ),
            ),

            'null' => array(
                'log',
                array(null),
                array(
                    'chromeLogger' => '[[null],null,""]',
                    'html' => '<li class="m_log"><span class="t_null">null</span></li>',
                    'script' => 'console.log(null);',
                    'streamAnsi' => "\e[38;5;250mnull\e[0m",
                    'text' => 'null',
                    'wamp' => array(
                        'log',
                        array(null),
                    ),
                ),
            ),

            'resource.closed`' => array(
                'log',
                array($fhClosed),
                array(
                    'entry' => $resourceClosedEntryExpect,
                    'chromeLogger' => '[["Resource id #' . (int) $fhClosed . ': stream"],null,""]',
                    'html' => '<li class="m_log"><span class="t_resource">Resource id #' . (int) $fhClosed . ': stream</span></li>',
                    'script' => 'console.log("Resource id #' . (int) $fhClosed . ': stream");',
                    'text' => 'Resource id #' . (int) $fhClosed . ': stream',
                    'wamp' => \json_decode(\json_encode($resourceClosedEntryExpect), true),
                ),
            ),

            'resource.open' => array(
                'log',
                array($fhOpen),
                array(
                    'custom' => static function () use ($fhOpen) {
                        \fclose($fhOpen);
                    },
                    'entry' => $resourceOpenEntryExpect,
                    'chromeLogger' => '[["Resource id #' . (int) $fhOpen . ': stream"],null,""]',
                    'html' => '<li class="m_log"><span class="t_resource">Resource id #' . (int) $fhOpen . ': stream</span></li>',
                    'script' => 'console.log("Resource id #' . (int) $fhOpen . ': stream");',
                    'text' => 'Resource id #' . (int) $fhOpen . ': stream',
                    'wamp' => \json_decode(\json_encode($resourceOpenEntryExpect), true),
                ),
            ),

            'string.numeric' => array(
                'log',
                array('123.45'),
                array(
                    'chromeLogger' => '[["123.45"],null,""]',
                    'html' => '<li class="m_log"><span class="t_string" data-type-more="numeric">123.45</span></li>',
                    'script' => 'console.log("123.45");',
                    'streamAnsi' => "\e[38;5;250m\"\e[96m123.45\e[38;5;250m\"\e[0m",
                    'text' => '"123.45"',
                    'wamp' => array(
                        'log',
                        array('123.45'),
                    ),
                ),
            ),

            'timestamp.float' => array(
                'log',
                array($ts + 0.1),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Abstracter::TYPE_FLOAT,
                                'typeMore' => Abstracter::TYPE_TIMESTAMP,
                                'value' => $ts + 0.1,
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'chromeLogger' => '[["' . ($ts + 0.1) . ' (' . $datetime . ')"],null,""]',
                    'html' => '<li class="m_log"><span class="timestamp value-container" title="' . $datetime . '"><span class="t_float" data-type-more="timestamp">' . ($ts + 0.1) . '</span></span></li>',
                    'script' => 'console.log("' . ($ts + 0.1) . ' (' . $datetime . ')");',
                    'streamAnsi' => "📅 \e[96m" . ($ts + 0.1) . "\e[0m \e[38;5;250m(" . $datetime . ")\e[0m",
                    'text' => '📅 ' . ($ts + 0.1) . ' (' . $datetime . ')',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Abstracter::TYPE_FLOAT,
                                'typeMore' => Abstracter::TYPE_TIMESTAMP,
                                'value' => $ts + 0.1,
                            ),
                        ),
                    ),
                ),
            ),
            'timestamp.int' => array(
                'log',
                array($ts),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Abstracter::TYPE_INT,
                                'typeMore' => Abstracter::TYPE_TIMESTAMP,
                                'value' => $ts,
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'chromeLogger' => '[["' . $ts . ' (' . $datetime . ')"],null,""]',
                    'html' => '<li class="m_log"><span class="timestamp value-container" title="' . $datetime . '"><span class="t_int" data-type-more="timestamp">' . $ts . '</span></span></li>',
                    'script' => 'console.log("' . $ts . ' (' . $datetime . ')");',
                    'streamAnsi' => "📅 \e[96m" . $ts . "\e[0m \e[38;5;250m(" . $datetime . ")\e[0m",
                    'text' => '📅 ' . $ts . ' (' . $datetime . ')',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Abstracter::TYPE_INT,
                                'typeMore' => Abstracter::TYPE_TIMESTAMP,
                                'value' => $ts,
                            ),
                        ),
                    ),
                ),
            ),
            'timestamp.int.forced' => array(
                'log',
                array(
                    Debug::getInstance()->abstracter->crateWithVals(\strtotime('1985-10-26 09:00:00 PDT'), array(
                        'typeMore' => Abstracter::TYPE_TIMESTAMP,
                    )),
                ),
                array(
                    'chromeLogger' => '[["499190400 (1985-10-26 16:00:00 GMT)"],null,""]',
                    'html' => '<li class="m_log"><span class="timestamp value-container" title="1985-10-26 16:00:00 GMT"><span class="t_int" data-type-more="timestamp">499190400</span></span></li>',
                    'script' => 'console.log("499190400 (1985-10-26 16:00:00 GMT)");',
                    'streamAnsi' => "📅 \e[96m499190400\e[0m \e[38;5;250m(1985-10-26 16:00:00 GMT)\e[0m",
                    'text' => '📅 499190400 (1985-10-26 16:00:00 GMT)',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Abstracter::TYPE_INT,
                                'typeMore' => Abstracter::TYPE_TIMESTAMP,
                                'value' => 499190400,
                            ),
                        ),
                    ),
                ),
            ),
            'timestamp.string' => array(
                'log',
                array((string) $ts),
                array(
                    'chromeLogger' => '[["' . $ts . ' (' . $datetime . ')"],null,""]',
                    'html' => '<li class="m_log"><span class="timestamp value-container" title="' . $datetime . '"><span class="t_string" data-type-more="timestamp">' . $ts . '</span></span></li>',
                    'script' => 'console.log("' . $ts . ' (' . $datetime . ')");',
                    'streamAnsi' => "📅 \e[38;5;250m\"\e[96m" . $ts . "\e[38;5;250m\"\e[0m \e[38;5;250m(" . $datetime . ")\e[0m",
                    'text' => '📅 "' . $ts . '" (' . $datetime . ')',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'brief' => false,
                                'debug' => Abstracter::ABSTRACTION,
                                'strlen' => null,
                                'type' => Abstracter::TYPE_STRING,
                                'typeMore' => Abstracter::TYPE_TIMESTAMP,
                                'value' => (string) $ts,
                            )
                        ),
                    ),
                ),
            ),
            'timestamp.string.forced' => array(
                'log',
                array(
                    Debug::getInstance()->abstracter->crateWithVals((string) \strtotime('1985-10-26 09:00:00 PDT'), array(
                        'typeMore' => Abstracter::TYPE_TIMESTAMP,
                    )),
                ),
                array(
                    'chromeLogger' => '[["499190400 (1985-10-26 16:00:00 GMT)"],null,""]',
                    'html' => '<li class="m_log"><span class="timestamp value-container" title="1985-10-26 16:00:00 GMT"><span class="t_string" data-type-more="timestamp">499190400</span></span></li>',
                    'script' => 'console.log("499190400 (1985-10-26 16:00:00 GMT)");',
                    'streamAnsi' => "📅 \e[38;5;250m\"\e[96m499190400\e[38;5;250m\"\e[0m \e[38;5;250m(1985-10-26 16:00:00 GMT)\e[0m",
                    'text' => '📅 "499190400" (1985-10-26 16:00:00 GMT)',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'brief' => false,
                                'debug' => Abstracter::ABSTRACTION,
                                'strlen' => null,
                                'type' => Abstracter::TYPE_STRING,
                                'typeMore' => Abstracter::TYPE_TIMESTAMP,
                                'value' => '499190400',
                            ),
                        ),
                    ),
                ),
            ),
            'timestamp.string.crated' => array(
                'log',
                array(
                    Debug::getInstance()->abstracter->crateWithVals((string) $ts, array(
                        'attribs' => array(
                            'class' => 'testaroo', // also test that converted to array
                        )
                    ))
                ),
                array(
                    'chromeLogger' => '[["' . $ts . ' (' . $datetime . ')"],null,""]',
                    'html' => '<li class="m_log"><span class="timestamp value-container" title="' . $datetime . '"><span class="t_string testaroo" data-type-more="timestamp">' . $ts . '</span></span></li>',
                    'script' => 'console.log("' . $ts . ' (' . $datetime . ')");',
                    'streamAnsi' => "📅 \e[38;5;250m\"\e[96m" . $ts . "\e[38;5;250m\"\e[0m \e[38;5;250m(" . $datetime . ")\e[0m",
                    'text' => '📅 "' . $ts . '" (' . $datetime . ')',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'attribs' => array(
                                    'class' => array(
                                        'testaroo',
                                    ),
                                ),
                                'brief' => false,
                                'debug' => Abstracter::ABSTRACTION,
                                'strlen' => null,
                                'type' => Abstracter::TYPE_STRING,
                                'typeMore' => Abstracter::TYPE_TIMESTAMP,
                                'value' => (string) $ts,
                            ),
                        ),
                    ),
                ),
            ),

            'notInspected' => array(
                'log',
                array(Abstracter::NOT_INSPECTED),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            Abstracter::NOT_INSPECTED,
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="t_notInspected">NOT INSPECTED</span></li>',
                    'text' => 'NOT INSPECTED',
                ),
            ),
            'recursion' => array(
                'log',
                array(Abstracter::RECURSION),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            Abstracter::RECURSION,
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="t_array"><span class="t_keyword">array</span> <span class="t_recursion">*RECURSION*</span></span></li>',
                    'text' => 'array *RECURSION*',
                ),
            ),
            'undefined' => array(
                'log',
                array(Abstracter::UNDEFINED),
                array(
                    'chromeLogger' => '[[null],null,""]',
                    'html' => '<li class="m_log"><span class="t_undefined"></span></li>',
                    'script' => 'console.log(undefined);',
                    'streamAnsi' => "\e[2mundefined\e[22m",
                    'text' => 'undefined',
                    'wamp' => array(
                        'log',
                        array(Abstracter::UNDEFINED),
                    ),
                ),
            ),
            'unknown' => array(
                'log',
                array(
                    Debug::getInstance()->abstracter->crateWithVals('mysteryVal', array(
                        'type' => Abstracter::TYPE_UNKNOWN,
                    )),
                ),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'brief' => false, // crateWithVals... initially treated as string
                                'debug' => Abstracter::ABSTRACTION,
                                'strlen' => null,
                                'type' => Abstracter::TYPE_UNKNOWN,
                                'typeMore' => null,
                                'value' => 'mysteryVal',
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="t_unknown">unknown type</span></li>',
                ),
            ),
        );
    }

    /**
     * Test that scalar reference vals get dereferenced
     * Sine passed by-value to log... nothing special being done
     *
     * @return void
     */
    public function testDereferenceBasic()
    {
        $src = 'success';
        $ref = &$src;
        $this->debug->log('ref', $ref);
        // $src = 'fail';
        $output = $this->debug->output();
        self::assertStringContainsString('success', $output);
    }
}

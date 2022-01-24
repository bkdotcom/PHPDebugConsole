<?php

namespace bdk\Test\Debug\Type;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Test\Debug\DebugTestFramework;
use DateTime;

/**
 * PHPUnit tests for Debug class
 */
class BasicTest extends DebugTestFramework
{
    public function providerTestMethod()
    {
        $ts = \time();
        $datetime = \gmdate(self::DATETIME_FORMAT, $ts);
        $test = new \bdk\Test\Debug\Fixture\Test();
        return array(
            'boolTrue' => array(
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
            'boolFalse' => array(
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
            'inf' => array(
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
            'NaN' => array(
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
            'intTimestamp' => array(
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
            'intTimestampForced' => array(
                'log',
                array(
                    \bdk\Debug::getInstance()->abstracter->crateWithVals(strtotime('1985-10-26 09:00:00 PDT'), array(
                        'typeMore' => Abstracter::TYPE_TIMESTAMP,
                    )),
                ),
                array(
                    // ·
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
            'stringNumeric' => array(
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
            'stringTimestamp' => array(
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
            'stringTimestampForced' => array(
                'log',
                array(
                    \bdk\Debug::getInstance()->abstracter->crateWithVals((string) strtotime('1985-10-26 09:00:00 PDT'), array(
                        'typeMore' => Abstracter::TYPE_TIMESTAMP,
                    )),
                ),
                array(
                    // ·
                    'chromeLogger' => '[["499190400 (1985-10-26 16:00:00 GMT)"],null,""]',
                    'html' => '<li class="m_log"><span class="timestamp value-container" title="1985-10-26 16:00:00 GMT"><span class="t_string" data-type-more="timestamp">499190400</span></span></li>',
                    'script' => 'console.log("499190400 (1985-10-26 16:00:00 GMT)");',
                    'streamAnsi' => "📅 \e[38;5;250m\"\e[96m499190400\e[38;5;250m\"\e[0m \e[38;5;250m(1985-10-26 16:00:00 GMT)\e[0m",
                    'text' => '📅 "499190400" (1985-10-26 16:00:00 GMT)',
                    'wamp' => array(
                        'log',
                        array(
                            array(
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
            'stringTimestampCrated' => array(
                'log',
                array(
                    \bdk\Debug::getInstance()->abstracter->crateWithVals((string) $ts, array(
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
            'callable' => array(
                'log',
                array(array($test,'testBaseStatic')),
                array(
                    'chromeLogger' => '[["callable: bdk\\\Test\\\Debug\\\Fixture\\\Test::testBaseStatic"],null,""]',
                    'html' => '<li class="m_log"><span class="t_callable"><span class="t_type">callable</span> <span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>Test</span><span class="t_operator">::</span><span class="t_identifier">testBaseStatic</span></span></li>',
                    'script' => 'console.log("callable: bdk\\\Test\\\Debug\\\Fixture\\\Test::testBaseStatic");',
                    'streamAnsi' => "callable: \e[38;5;250mbdk\Test\Debug\Fixture\\\e[0m\e[1mTest\e[22m\e[38;5;130m::\e[0m\e[1mtestBaseStatic\e[22m",
                    'text' => 'callable: bdk\Test\Debug\Fixture\Test::testBaseStatic',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Abstracter::TYPE_CALLABLE,
                                'value' => array(
                                    'bdk\Test\Debug\Fixture\Test',
                                    'testBaseStatic',
                                ),
                            ),
                        ),
                    ),
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
        $src = 'fail';
        $output = $this->debug->output();
        $this->assertStringContainsString('success', $output);
    }
}

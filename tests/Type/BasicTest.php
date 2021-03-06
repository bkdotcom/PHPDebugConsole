<?php

namespace bdk\DebugTests\Type;

use bdk\Debug\Abstraction\Abstracter;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class BasicTest extends DebugTestFramework
{

    public function providerTestMethod()
    {
        $ts = \time();
        $datetime = \date('Y-m-d H:i:s', $ts);
        $test = new \bdk\DebugTests\Fixture\Test();
        return array(
            // #0 : boolean
            array(
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
            // #1
            array(
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
            // #2 : null
            array(
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
            // #3 : INF
            array(
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
            // #4 : NAN
            array(
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
            // #5 : number
            array(
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
            // #6 : float
            array(
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
            // #7 : (int) timestamp
            array(
                'log',
                array($ts),
                array(
                    'chromeLogger' => '[["' . $ts . ' (' . $datetime . ')"],null,""]',
                    'html' => '<li class="m_log"><span class="timestamp value-container" data-type="int" title="' . $datetime . '"><span class="t_int">' . $ts . '</span></span></li>',
                    'script' => 'console.log("' . $ts . ' (' . $datetime . ')");',
                    'streamAnsi' => "📅 \e[96m" . $ts . "\e[0m \e[38;5;250m(" . $datetime . ")\e[0m",
                    'text' => '📅 ' . $ts . ' (' . $datetime . ')',
                    'wamp' => array(
                        'log',
                        array($ts),
                    ),
                ),
            ),
            // #8 : (string) timestamp
            array(
                'log',
                array((string) $ts),
                array(
                    'chromeLogger' => '[["' . $ts . ' (' . $datetime . ')"],null,""]',
                    'html' => '<li class="m_log"><span class="timestamp value-container" data-type="string" title="' . $datetime . '"><span class="t_string" data-type-more="numeric">' . $ts . '</span></span></li>',
                    'script' => 'console.log("' . $ts . ' (' . $datetime . ')");',
                    'streamAnsi' => "📅 \e[38;5;250m\"\e[96m" . $ts . "\e[38;5;250m\"\e[0m \e[38;5;250m(" . $datetime . ")\e[0m",
                    'text' => '📅 "' . $ts . '" (' . $datetime . ')',
                    'wamp' => array(
                        'log',
                        array((string) $ts),
                    ),
                ),
            ),
            // #9 : (string) timestamp - crateWithVals
            array(
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
                    'html' => '<li class="m_log"><span class="timestamp value-container" data-type="string" title="' . $datetime . '"><span class="t_string testaroo" data-type-more="numeric">' . $ts . '</span></span></li>',
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
                                'typeMore' => Abstracter::TYPE_STRING_NUMERIC,
                                'value' => (string) $ts,
                            ),
                        ),
                    ),
                ),
            ),
            // #10 : undefined
            array(
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
            // #11 : callable
            array(
                'log',
                array(array($test,'testBaseStatic')),
                array(
                    'chromeLogger' => '[["callable: bdk\\\DebugTests\\\Fixture\\\Test::testBaseStatic"],null,""]',
                    'html' => '<li class="m_log"><span class="t_callable"><span class="t_type">callable</span> <span class="classname"><span class="namespace">bdk\DebugTests\Fixture\</span>Test</span><span class="t_operator">::</span><span class="t_identifier">testBaseStatic</span></span></li>',
                    'script' => 'console.log("callable: bdk\\\DebugTests\\\Fixture\\\Test::testBaseStatic");',
                    'streamAnsi' => "callable: \e[38;5;250mbdk\DebugTests\Fixture\\\e[0m\e[1mTest\e[22m\e[38;5;130m::\e[0m\e[1mtestBaseStatic\e[22m",
                    'text' => 'callable: bdk\DebugTests\Fixture\Test::testBaseStatic',
                    'wamp' => array(
                        'log',
                        array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Abstracter::TYPE_CALLABLE,
                                'value' => array(
                                    'bdk\DebugTests\Fixture\Test',
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

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
        // val, html, text, script
        return array(
            // boolean
            array(
                'log',
                array(true),
                array(
                    'chromeLogger' => '[[true],null,""]',
                    'html' => '<li class="m_log"><span class="t_bool true">true</span></li>',
                    'script' => 'console.log(true);',
                    'streamAnsi' => "\e[32mtrue\e[0m",
                    'text' => 'true',
                    'wamp' => array(
                        'log',
                        array(true),
                    ),
                )
            ),
            array(
                'log',
                array(false),
                array(
                    'chromeLogger' => '[[false],null,""]',
                    'html' => '<li class="m_log"><span class="false t_bool">false</span></li>',
                    'script' => 'console.log(false);',
                    'streamAnsi' => "\e[91mfalse\e[0m",
                    'text' => 'false',
                    'wamp' => array(
                        'log',
                        array(false),
                    ),
                )
            ),
            // null
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
            // number
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
            array(
                'log',
                array($ts),
                array(
                    'chromeLogger' => '[["' . $ts . ' (' . $datetime . ')"],null,""]',
                    'html' => '<li class="m_log"><span class="t_int timestamp" title="' . $datetime . '">' . $ts . '</span></li>',
                    'script' => 'console.log("' . $ts . ' (' . $datetime . ')");',
                    'streamAnsi' => "📅 \e[96m" . $ts . "\e[0m \e[38;5;250m(" . $datetime . ")\e[0m",
                    'text' => '📅 ' . $ts . ' (' . $datetime . ')',
                    'wamp' => array(
                        'log',
                        array($ts),
                    ),
                ),
            ),
            array(
                'log',
                array((string) $ts),
                array(
                    'chromeLogger' => '[["' . $ts . ' (' . $datetime . ')"],null,""]',
                    'html' => '<li class="m_log"><span class="no-quotes numeric t_string timestamp" title="' . $datetime . '">' . $ts . '</span></li>',
                    'script' => 'console.log("' . $ts . ' (' . $datetime . ')");',
                    'streamAnsi' => "📅 \e[38;5;250m\"\e[96m" . $ts . "\e[38;5;250m\"\e[0m \e[38;5;250m(" . $datetime . ")\e[0m",
                    'text' => '📅 "' . $ts . '" (' . $datetime . ')',
                    'wamp' => array(
                        'log',
                        array((string) $ts),
                    ),
                ),
            ),
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
                                'value' => array(
                                    'bdk\DebugTests\Fixture\Test',
                                    'testBaseStatic',
                                ),
                                'type' => Abstracter::TYPE_CALLABLE,
                                'debug' => Abstracter::ABSTRACTION,
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
        $this->assertContains('success', $output);
    }
}

<?php

use bdk\Debug\Abstraction\Abstracter;

/**
 * PHPUnit tests for Debug class
 */
class TypeBasicTest extends DebugTestFramework
{

    public function providerTestMethod()
    {
        $ts = time();
        $datetime = date('Y-m-d H:i:s', $ts);
        $test = new \bdk\DebugTest\Test();
        // val, html, text, script
        return array(
            // boolean
            array(
                'log',
                array(true),
                array(
                    'chromeLogger' => '[[true],null,""]',
                    'html' => '<li class="m_log"><span class="t_bool true">true</span></li>',
                    'text' => 'true',
                    'script' => 'console.log(true);',
                    'streamAnsi' => "\e[32mtrue\e[0m",
                )
            ),
            array(
                'log',
                array(false),
                array(
                    'chromeLogger' => '[[false],null,""]',
                    'html' => '<li class="m_log"><span class="false t_bool">false</span></li>',
                    'text' => 'false',
                    'script' => 'console.log(false);',
                    'streamAnsi' => "\e[91mfalse\e[0m",
                )
            ),
            // null
            array(
                'log',
                array(null),
                array(
                    'chromeLogger' => '[[null],null,""]',
                    'html' => '<li class="m_log"><span class="t_null">null</span></li>',
                    'text' => 'null',
                    'script' => 'console.log(null);',
                    'streamAnsi' => "\e[38;5;250mnull\e[0m",
                ),
            ),
            // number
            array(
                'log',
                array(10),
                array(
                    'chromeLogger' => '[[10],null,""]',
                    'html' => '<li class="m_log"><span class="t_int">10</span></li>',
                    'text' => '10',
                    'script' => 'console.log(10);',
                    'streamAnsi' => "\e[96m10\e[0m",
                ),
            ),
            array(
                'log',
                array(10.10),
                array(
                    'chromeLogger' => '[[10.1],null,""]',
                    'html' => '<li class="m_log"><span class="t_float">10.1</span></li>',
                    'text' => '10.1',
                    'script' => 'console.log(10.1);',
                    'streamAnsi' => "\e[96m10.1\e[0m",
                ),
            ),
            array(
                'log',
                array($ts),
                array(
                    'chromeLogger' => '[["'.$ts.' ('.$datetime.')"],null,""]',
                    'html' => '<li class="m_log"><span class="t_int timestamp" title="'.$datetime.'">'.$ts.'</span></li>',
                    'text' => '📅 '.$ts.' ('.$datetime.')',
                    'script' => 'console.log("'.$ts.' ('.$datetime.')");',
                    'streamAnsi' => "📅 \e[96m".$ts."\e[0m \e[38;5;250m(".$datetime.")\e[0m",
                ),
            ),
            array(
                'log',
                array((string) $ts),
                array(
                    'chromeLogger' => '[["'.$ts.' ('.$datetime.')"],null,""]',
                    'html' => '<li class="m_log"><span class="no-quotes numeric t_string timestamp" title="'.$datetime.'">'.$ts.'</span></li>',
                    'text' => '📅 "'.$ts.'" ('.$datetime.')',
                    'script' => 'console.log("'.$ts.' ('.$datetime.')");',
                    'streamAnsi' => "📅 \e[38;5;250m\"\e[96m".$ts."\e[38;5;250m\"\e[0m \e[38;5;250m(".$datetime.")\e[0m",
                ),
            ),
            array(
                'log',
                array(Abstracter::UNDEFINED),
                array(
                    'chromeLogger' => '[[null],null,""]',
                    'html' => '<li class="m_log"><span class="t_undefined"></span></li>',
                    'text' => 'undefined',
                    'script' => 'console.log(undefined);',
                    'streamAnsi' => "\e[2mundefined\e[22m",
                ),
            ),
            array(
                'log',
                array(array($test,'testBaseStatic')),
                array(
                    'chromeLogger' => '[["callable: bdk\\\DebugTest\\\Test::testBaseStatic"],null,""]',
                    'html' => '<li class="m_log"><span class="t_callable"><span class="t_type">callable</span> <span class="classname"><span class="namespace">bdk\DebugTest\</span>Test</span><span class="t_operator">::</span><span class="t_identifier">testBaseStatic</span></span></li>',
                    'text' => 'callable: bdk\DebugTest\Test::testBaseStatic',
                    'script' => 'console.log("callable: bdk\\\DebugTest\\\Test::testBaseStatic");',
                    'streamAnsi' => "callable: \e[38;5;250mbdk\DebugTest\\\e[0m\e[1mTest\e[22m\e[38;5;130m::\e[0m\e[1mtestBaseStatic\e[22m",
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

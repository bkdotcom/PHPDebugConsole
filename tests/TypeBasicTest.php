<?php

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
                    'html' => '<div class="m_log"><span class="t_bool true">true</span></div>',
                    'text' => 'true',
                    'script' => 'console.log(true);',
                )
            ),
            array(
                'log',
                array(false),
                array(
                    'chromeLogger' => '[[false],null,""]',
                    'html' => '<div class="m_log"><span class="false t_bool">false</span></div>',
                    'text' => 'false',
                    'script' => 'console.log(false);',
                )
            ),
            // null
            array(
                'log',
                array(null),
                array(
                    'chromeLogger' => '[[null],null,""]',
                    'html' => '<div class="m_log"><span class="t_null">null</span></div>',
                    'text' => 'null',
                    'script' => 'console.log(null);',
                ),
            ),
            // number
            array(
                'log',
                array(10),
                array(
                    'chromeLogger' => '[[10],null,""]',
                    'html' => '<div class="m_log"><span class="t_int">10</span></div>',
                    'text' => '10',
                    'script' => 'console.log(10);',
                ),
            ),
            array(
                'log',
                array(10.10),
                array(
                    'chromeLogger' => '[[10.1],null,""]',
                    'html' => '<div class="m_log"><span class="t_float">10.1</span></div>',
                    'text' => '10.1',
                    'script' => 'console.log(10.1);',
                ),
            ),
            array(
                'log',
                array($ts),
                array(
                    'chromeLogger' => '[["'.$ts.' ('.$datetime.')"],null,""]',
                    'html' => '<div class="m_log"><span class="t_int timestamp" title="'.$datetime.'">'.$ts.'</span></div>',
                    'text' => '📅 '.$ts.' ('.$datetime.')',
                    'script' => 'console.log("'.$ts.' ('.$datetime.')");',
                ),
            ),
            array(
                'log',
                array(\bdk\Debug\Abstracter::UNDEFINED),
                array(
                    'chromeLogger' => '[[null],null,""]',
                    'html' => '<div class="m_log"><span class="t_undefined"></span></div>',
                    'text' => 'undefined',
                    'script' => 'console.log(undefined);',
                ),
            ),
            array(
                'log',
                array(array($test,'testBaseStatic')),
                array(
                    'chromeLogger' => '[["callable: bdk\\\DebugTest\\\Test::testBaseStatic"],null,""]',
                    'html' => '<div class="m_log"><span class="t_callable"><span class="t_type">callable</span> <span class="t_classname"><span class="namespace">bdk\DebugTest\</span>Test</span><span class="t_operator">::</span><span class="method-name">testBaseStatic</span></span></div>',
                    'text' => 'callable: bdk\DebugTest\Test::testBaseStatic',
                    'script' => 'console.log("callable: bdk\\\DebugTest\\\Test::testBaseStatic");',
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

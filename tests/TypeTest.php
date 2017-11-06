<?php

/**
 * PHPUnit tests for Debug class
 */
class TypeBasicTest extends DebugTestFramework
{

    public function dumpProvider()
    {
        $ts = time();
        $test = new \bdk\DebugTest\Test();
        // val, html, text, script
        return array(
            // boolean
            array(true, '<span class="t_bool true">true</span>', 'true', true),
            array(false, '<span class="t_bool false">false</span>', 'false', false),
            // null
            array(null, '<span class="t_null">null</span>', 'null', null),
            // number
            array(10, '<span class="t_int">10</span>', 10, 10),
            array(10.10, '<span class="t_float">10.1</span>', 10.10, 10.10),
            array(
                $ts,
                '<span class="t_int timestamp" title="'.date('Y-m-d H:i:s', $ts).'">'.$ts.'</span>',
                '📅 '.$ts.' ('.date('Y-m-d H:i:s').')',
                $ts.' ('.date('Y-m-d H:i:s').')',
            ),
            array(\bdk\Debug\Abstracter::UNDEFINED,
                '<span class="t_undefined"></span>',
                'undefined',
                \bdk\Debug\Abstracter::UNDEFINED,
            ),
            array(
                array($test,'testBaseStatic'),
                '<span class="t_callable"><span class="t_type">callable</span> <span class="t_classname"><span class="namespace">bdk\DebugTest\</span>Test</span><span class="t_operator">::</span><span class="method-name">testBaseStatic</span></span>',
                'callable: bdk\DebugTest\Test::testBaseStatic',
                'callable: bdk\DebugTest\Test::testBaseStatic',
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

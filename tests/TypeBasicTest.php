<?php

/**
 * PHPUnit tests for Debug class
 */
class TypeBasicTest extends DebugTestFramework
{

    public function dumpProvider()
    {
        $ts = time();
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
                $ts.' ('.date('Y-m-d H:i:s').')'
            ),
            array(\bdk\Debug\Abstracter::UNDEFINED,
                '<span class="t_undefined"></span>',
                'undefined',
                \bdk\Debug\Abstracter::UNDEFINED
            )

        );
    }

    /**
     * Test
     *
     * @dataProvider dumpProvider
     *
     * @return void
     */
    /*
    public function testDump($val, $html, $text, $script)
    {
        $dump = $this->debug->output->outputHtml->dump($val);
        $this->assertSame($html, $dump);
        $dump = $this->debug->output->outputText->dump($val);
        $this->assertSame($text, $dump);
        $dump = $this->debug->output->outputScript->dump($val);
        $this->assertSame($script, $dump);
    }
    */

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

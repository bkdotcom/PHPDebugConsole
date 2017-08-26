<?php

/**
 * PHPUnit tests for Debug class
 */
class DebugTestFramework extends PHPUnit_Framework_DOMTestCase
{

    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->debug = new \bdk\Debug(array(
            'collect' => true,
            'output' => true,
            'outputCss' => false,
            'outputScript' => false,
            'outputAs' => 'html',
            'logEnvInfo' => false,
        ));
    }

    /**
     * tearDown is executed after each test
     *
     * @return void
     */
    public function tearDown()
    {
        $this->debug->setCfg('output', false);
    }

    /**
     * Util to output to console / help in creation of tests
     *
     * @param string $label label
     * @param mixed  $val   value
     *
     * @return void
     */
    public function stdout($label, $val)
    {
        fwrite(STDOUT, $label.' = '.print_r($val, true) . "\n");
    }

    /**
     * Override me
     *
     * @return array
     */
    public function dumpProvider()
    {
        return array(
            // val, html, text, script
            array(null, '<span class="t_null">null</span>', 'null', null),
        );
    }

    /**
     * Test
     *
     * @dataProvider dumpProvider
     *
     * @return void
     */
    public function testDump($val, $html, $text, $script)
    {
        $dumps = array(
            'outputHtml' => $html,
            'outputText' => $text,
            'outputScript' => $script,
        );
        foreach ($dumps as $outputAs => $dumpExpect) {
            $dump = $this->debug->output->{$outputAs}->dump($val);
            if (is_callable($dumpExpect)) {
                $dumpExpect($dump);
            } elseif (is_array($dumpExpect) && isset($dumpExpect['contains'])) {
                $this->assertContains($dumpExpect['contains'], $dump, $outputAs.' doesn\'t contain');
            } else {
                $this->assertSame($dumpExpect, $dump, $outputAs.' not same');
            }
        }
    }
}

<?php

use bdk\PubSub\Event;

/**
 * PHPUnit tests for Debug class
 */
class DebugTestFramework extends PHPUnit_Framework_DOMTestCase
{

    public static $allowError = false;

    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp()
    {
        self::$allowError = false;
        $this->debug = \bdk\Debug::getInstance(array(
            'collect' => true,
            'emailLog' => false,
            'emailTo' => null,
            'logEnvInfo' => false,
            'output' => true,
            'outputCss' => false,
            'outputScript' => false,
            'outputAs' => 'html',
            'onError' => function (Event $event) {
                if (self::$allowError) {
                    $event['logError'] = false;
                    return;
                }
                throw new \PHPUnit\Framework\Exception($event['message'], 500);
            }
        ));
        $resetValues = array(
            'alerts'        => array(), // array of alerts.  alerts will be shown at top of output when possible
            'counts'        => array(), // count method
            'entryCountInitial' => 0,   // store number of log entries created during init
            'groupDepth'    => 0,
            'groupDepthSummary' => 0,
            'log'           => array(),
            'logSummary'    => array(),
            'outputSent'    => false,
            'timers' => array(      // timer method
                'labels' => array(
                    'debugInit' => array(
                        0,
                        isset($_SERVER['REQUEST_TIME_FLOAT']) // php 5.4
                            ? $_SERVER['REQUEST_TIME_FLOAT']
                            : microtime(true)
                    ),
                ),
                'stack' => array(),
            ),
        );
        foreach ($resetValues as $k => $v) {
            $this->debug->setData($k, $v);
        }
        $this->debug->errorHandler->setData('errors', array());
        $this->debug->errorHandler->setData('errorCaller', array());
        $this->debug->errorHandler->setData('lastError', null);
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

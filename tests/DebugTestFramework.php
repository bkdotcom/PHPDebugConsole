<?php

use bdk\PubSub\Event;
use bdk\CssXpath\DOMTestCase;

/**
 * PHPUnit tests for Debug class
 */
class DebugTestFramework extends DOMTestCase
{

    public static $allowError = false;

    /**
     * for given $var, check if it's abstraction type is of $type
     *
     * @param array  $var  abstracted $var
     * @param string $type array, object, or resource
     *
     * @return boolean
     */
    protected function checkAbstractionType($var, $type)
    {
        $return = false;
        if ($type == 'object') {
            $keys = array('collectMethods','viaDebugInfo','isExcluded','isRecursion',
                    'extends','implements','constants','properties','methods','scopeClass','stringified');
            $keysMissing = array_diff($keys, array_keys($var));
            $return = $var['debug'] === \bdk\Debug\Abstracter::ABSTRACTION
                && $var['type'] === 'object'
                && $var['className'] === 'stdClass'
                && count($keysMissing) == 0;
        } elseif ($type == 'resource') {
            $return = $var['debug'] === \bdk\Debug\Abstracter::ABSTRACTION
                && $var['type'] === 'resource'
                && isset($var['value']);
        }
        return $return;
    }

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
                    $event['continueToNormal'] = false;
                    return;
                }
                throw new \PHPUnit\Framework\Exception($event['message'], 500);
            }
        ));
        $resetValues = array(
            'alerts'        => array(), // array of alerts.  alerts will be shown at top of output when possible
            'counts'        => array(), // count method
            'entryCountInitial' => 0,   // store number of log entries created during init
            'groupDepth'    => array(0, 0),
            'groupSummaryStack' => array(),
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
    public function providerTestMethod()
    {
        return array(
            // val, html, text, script
            array(
                'log',
                array(null),
                array(
                    'html' => '<div class="m_log"><span class="t_null">null</span></div>',
                    'text' => 'null',
                    'script' => 'console.log(null);',
                ),
            ),
        );
    }

    /**
     * Test Method's output
     *
     * @param string|null $method debug method to call or null/false to just test against last log entry
     * @param array       $args   method arguments
     * @param array|false $tests  array of 'outputAs' => 'string
     *                          ie array('html'=>'expected html')
     *                          pass false to test that nothing was logged
     *
     * @dataProvider providerTestMethod
     *
     * @return void
     */
    public function testMethod($method, $args = array(), $tests = array())
    {
        if ($tests === false) {
            /*
                Assert that nothing gets logged
            */
            $path = $method == 'alert'
                ? 'alerts/count'
                : 'log/count';
            $logCountBefore = $this->debug->getData($path);
            \call_user_func_array(array($this->debug, $method), $args);
            $logCountAfter = $this->debug->getData($path);
            $this->assertSame($logCountBefore, $logCountAfter, 'failed asserting nothing logged');
            return;
        }
        $dataPath = 'log/end';
        if (is_array($method)) {
            if (isset($method['dataPath'])) {
                $dataPath = $method['dataPath'];
            }
        } elseif ($method) {
            \call_user_func_array(array($this->debug, $method), $args);
        }
        $logEntry = $this->debug->getData($dataPath);
        if ($method == 'alert') {
            $logEntry = $this->debug->getData('alerts/end');
            $logEntry = array('alert', array($logEntry[0]), $logEntry[1]);
        }
        foreach ($tests as $outputAs => $outputExpect) {
            if ($outputAs == 'entry') {
                $this->assertSame($outputExpect, $logEntry);
                continue;
            } elseif ($outputAs == 'custom') {
                \call_user_func($outputExpect, $logEntry);
                continue;
            } elseif ($outputAs == 'firephp') {
                $outputObj = $this->debug->output->{$outputAs};
                $outputObj->unitTestMode = true;
                $outputObj->processLogEntry($logEntry[0], $logEntry[1], $logEntry[2]);
                $output = \implode("\n", $outputObj->lastHeadersSent);
                // @todo assert that header integer increments
                $outputExpect = preg_replace('/^(X-Wf-1-1-1-)\S+\b/m', '$1%d', $outputExpect);
            } else {
                $outputObj = $this->debug->output->{$outputAs};
                $output = $outputObj->processLogEntry($logEntry[0], $logEntry[1], $logEntry[2]);
            }
            if (\is_callable($outputExpect)) {
                $outputExpect($output);
            } elseif (\is_array($outputExpect) && isset($outputExpect['contains'])) {
                $this->assertContains($outputExpect['contains'], $output, $outputAs.' doesn\'t contain');
            } else {
                $output = \preg_replace("#^\s+#m", '', $output);
                $outputExpect = \preg_replace('#^\s+#m', '', $outputExpect);
                // @see https://github.com/sebastianbergmann/phpunit/issues/3040
                $output = \str_replace("\r", '[\\r]', $output);
                $outputExpect = \str_replace("\r", '[\\r]', $outputExpect);
                $this->assertStringMatchesFormat($outputExpect, $output, $outputAs.' not same');
            }
        }
    }

    /**
     * Test output
     *
     * @param array $tests array of 'outputAs' => 'string
     *                         ie array('html'=>'expected html')
     *
     * @return void
     */
    public function outputTest($tests = array())
    {
        $backupData = array(
            'alerts' => $this->debug->getData('alerts'),
            'log' => $this->debug->getData('log'),
            'logSummary' => $this->debug->getData('logSummary'),
            'requestId' => $this->debug->getData('requestId'),
            'runtime' => $this->debug->getData('runtime'),
        );
        $backupOutputAs = $this->debug->getCfg('outputAs');
        foreach ($tests as $outputAs => $expectContains) {
            $this->debug->setCfg('outputAs', $outputAs);
            $output = $this->debug->output();
            $output = \preg_replace("#^\s+#m", '', $output);
            $expectContains = \preg_replace('#^\s+#m', '', $expectContains);
            $this->assertContains($expectContains, $output);
            foreach ($backupData as $k => $v) {
                $this->debug->setData($k, $v);
            }
        }
        $this->debug->setCfg('outputAs', $backupOutputAs);
    }
}

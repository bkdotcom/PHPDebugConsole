<?php

use bdk\PubSub\Event;
use bdk\CssXpath\DOMTestCase;

/**
 * PHPUnit tests for Debug class
 */
class DebugTestFramework extends DOMTestCase
{

    public static $allowError = false;
    private $reflectionMethods = array();
    private $reflectionProperties = array();

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
        $this->debug->setData($resetValues);
        $this->debug->errorHandler->setData('errors', array());
        $this->debug->errorHandler->setData('errorCaller', array());
        $this->debug->errorHandler->setData('lastError', null);
        if (!isset($this->file)) {
            /*
            this dummy test won't do any assertions, but will set
                $this->file
                $this->line
            */
            $this->testMethod(
                'getCfg',
                array('collect'),
                array(
                    'custom' => function () {
                    },
                )
            );
        }
    }

    /**
     * tearDown is executed after each test
     *
     * @return void
     */
    public function tearDown()
    {
        $this->debug->setCfg('output', false);
        $subscribers = $this->debug->eventManager->getSubscribers('debug.output');
        foreach ($subscribers as $subscriber) {
            $unsub = false;
            if ($subscriber instanceof \Closure) {
                $unsub = true;
            } elseif (is_array($subscriber) && strpos(get_class($subscriber[0]), 'bdk\\Debug') === false) {
                $unsub = true;
            }
            if ($unsub) {
                $this->debug->eventManager->unsubscribe('debug.output', $subscriber);
            }
        }
        $subscribers = $this->debug->eventManager->getSubscribers('debug.outputLogEntry');
        foreach ($subscribers as $subscriber) {
            $this->debug->eventManager->unsubscribe('debug.outputLogEntry', $subscriber);
        }
        if (!isset($this->reflectionProperties['textDepth'])) {
            $depthRef = new \ReflectionProperty($this->debug->output->text, 'depth');
            $depthRef->setAccessible(true);
            $this->reflectionProperties['textDepth'] = $depthRef;
        }
        $this->reflectionProperties['textDepth']->setValue($this->debug->output->text, 0);
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
        $countPath = $method == 'alert'
            ? 'alerts/__count__'
            : 'log/__count__';
        $dataPath = $method == 'alert'
            ? 'alerts/__end__'
            : 'log/__end__';
        $logCountBefore = $this->debug->getData($countPath);
        if (is_array($method)) {
            if (isset($method['dataPath'])) {
                $dataPath = $method['dataPath'];
            }
        } elseif ($method) {
            $return = \call_user_func_array(array($this->debug, $method), $args);
            $this->file = __FILE__;
            $this->line = __LINE__ - 2;
        }
        $logEntry = $this->debug->getData($dataPath);
        if (!$tests) {
            $tests = array(
                'notLogged' => true,
            );
        }
        foreach ($tests as $test => $outputExpect) {
            if ($test == 'entry') {
                if (\is_callable($outputExpect)) {
                    \call_user_func($outputExpect, $logEntry);
                } elseif (is_string($outputExpect)) {
                    $this->assertStringMatchesFormat($outputExpect, json_encode($logEntry), 'log entry does not match format');
                } else {
                    $this->assertSame($outputExpect, $logEntry);
                }
                continue;
            } elseif ($test == 'custom') {
                \call_user_func($outputExpect, $logEntry);
                continue;
            } elseif ($test == 'notLogged') {
                $this->assertSame($logCountBefore, $this->debug->getData($countPath), 'failed asserting nothing logged');
                continue;
            } elseif ($test == 'return') {
                if (is_string($outputExpect)) {
                    $this->assertStringMatchesFormat($outputExpect, $return, 'return value does not match format');
                } else {
                    $this->assertSame($outputExpect, $return, 'return value not same');
                }
                continue;
            }
            $outputObj = $this->debug->output->{$test};
            if ($test == 'firephp') {
                $outputObj->unitTestMode = true;
            }
            if (!isset($this->reflectionMethods[$test])) {
                $refMethod = new \ReflectionMethod($outputObj, 'processLogEntryWEvent');
                $refMethod->setAccessible(true);
                $this->reflectionMethods[$test] = $refMethod;
            }
            $output = $this->reflectionMethods[$test]->invoke($outputObj, $logEntry[0], $logEntry[1], $logEntry[2]);
            if ($test == 'chromeLogger') {
                if (!isset($this->reflectionProperties['chromeLogger'])) {
                    $refProperty = new \ReflectionProperty($outputObj, 'json');
                    $refProperty->setAccessible(true);
                    $this->reflectionProperties['chromeLogger'] = $refProperty;
                }
                $output = end($this->reflectionProperties['chromeLogger']->getValue($outputObj)['rows']);
                // output is an array
                if (is_string($outputExpect)) {
                    $output = json_encode($output);
                }
            } elseif ($test == 'firephp') {
                $output = \implode("\n", $outputObj->lastHeadersSent);
                // @todo assert that header integer increments
                if (is_string($outputExpect)) {
                    $outputExpect = preg_replace('/^(X-Wf-1-1-1-)\S+\b/m', '$1%d', $outputExpect);
                }
            }
            if (\is_callable($outputExpect)) {
                $outputExpect($output);
            } elseif (\is_array($outputExpect)) {
                if (isset($outputExpect['contains'])) {
                    $this->assertContains($outputExpect['contains'], $output, $test.' doesn\'t contain');
                } else {
                    $this->assertSame($outputExpect, $output, $test.' not same');
                }
            } else {
                $output = \preg_replace("#^\s+#m", '', $output);
                $outputExpect = \preg_replace('#^\s+#m', '', $outputExpect);
                // @see https://github.com/sebastianbergmann/phpunit/issues/3040
                $output = \str_replace("\r", '[\\r]', $output);
                $outputExpect = \str_replace("\r", '[\\r]', $outputExpect);
                $this->assertStringMatchesFormat(trim($outputExpect), trim($output), $test.' not same');
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
        foreach ($tests as $test => $expectContains) {
            $this->debug->setCfg('outputAs', $test);
            $output = $this->debug->output();
            $output = \preg_replace("#^\s+#m", '', $output);
            $expectContains = \preg_replace('#^\s+#m', '', $expectContains);
            if ($expectContains) {
                $this->assertContains($expectContains, $output);
            }
            foreach ($backupData as $k => $v) {
                $this->debug->setData($k, $v);
            }
        }
        $this->debug->setCfg('outputAs', $backupOutputAs);
    }
}

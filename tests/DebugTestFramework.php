<?php

use bdk\Debug\LogEntry;
use bdk\Debug\Abstraction\Abstraction;
use bdk\PubSub\Event;
use bdk\CssXpath\DOMTestCase;

/**
 * PHPUnit tests for Debug class
 */
class DebugTestFramework extends DOMTestCase
{

    public static $allowError = false;

    protected function &getSharedVar($key)
    {
        static $values = array(
            'reflectionMethods' => array(),
            'reflectionProperties' => array(),
        );
        if (!isset($values[$key])) {
            $values[$key] = null;
        }
        return $values[$key];
    }

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
        if (!$var instanceof Abstraction) {
            return false;
        }
        if ($type == 'object') {
            $keys = array(
                'className',
                'constants',
                'definition',
                'extends',
                'flags',
                'implements',
                'isExcluded',
                'isRecursion',
                'methods',
                'phpDoc',
                'properties',
                'scopeClass',
                'stringified',
                'traverseValues',
                'viaDebugInfo',
            );
            $keysMissing = \array_diff($keys, \array_keys($var->getValues()));
            $return = $var['type'] === 'object'
                && $var['className'] === 'stdClass'
                && \count($keysMissing) == 0;
        } elseif ($type == 'resource') {
            $return = $var['type'] === 'resource' && isset($var['value']);
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
        /*
        $count = &$this->getSharedVar('count');
        $count = $count === null
            ? 1
            : $count + 1;
        $GLOBALS['debugTest'] = $count == 10;
        if ($GLOBALS['debugTest']) {
            $this->stderr(' ----------------- setUp -----------------');
        }
        */
        self::$allowError = false;
        $this->debug = \bdk\Debug::getInstance(array(
            'collect' => true,
            'emailLog' => false,
            'emailTo' => null,
            'logEnvInfo' => false,
            'logRuntime' => true,
            'onError' => function (Event $event) {
                if (self::$allowError) {
                    $event['continueToNormal'] = false;
                    return;
                }
                throw new \PHPUnit\Framework\Exception($event['message'], 500);
            },
            'output' => true,
            'outputCss' => false,
            'outputHeaders' => false,
            'outputScript' => false,
            'route' => 'html',
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
                            : \microtime(true)
                    ),
                ),
                'stack' => array(),
            ),
        );
        $this->debug->setData($resetValues);
        $this->debug->errorHandler->setData('errors', array());
        $this->debug->errorHandler->setData('errorCaller', array());
        $this->debug->errorHandler->setData('lastErrors', array());
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
        // fwrite(STDERR, "tearDown\n");
        $subscribers = $this->debug->eventManager->getSubscribers('debug.output');
        foreach ($subscribers as $subscriber) {
            $unsub = false;
            if ($subscriber instanceof \Closure) {
                $unsub = true;
            } elseif (\is_array($subscriber) && \strpos(\get_class($subscriber[0]), 'bdk\\Debug') === false) {
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
        $refProperties = &$this->getSharedVar('reflectionProperties');
        if (!isset($refProperties['channels'])) {
            $channelProp = new \ReflectionProperty($this->debug, 'channels');
            $channelProp->setAccessible(true);
            $refProperties['channels'] = $channelProp;
        }
        if (!isset($refProperties['textDepth'])) {
            $depthRef = new \ReflectionProperty($this->debug->dumpText, 'depth');
            $depthRef->setAccessible(true);
            $refProperties['textDepth'] = $depthRef;
        }
        if (!isset($refProperties['registeredPlugins'])) {
            $registeredPluginsRef = new \ReflectionProperty($this->debug, 'registeredPlugins');
            $registeredPluginsRef->setAccessible(true);
            $refProperties['registeredPlugins'] = $registeredPluginsRef;
        }
        $refProperties['channels']->setValue($this->debug, array());
        $refProperties['textDepth']->setValue($this->debug->dumpText, 0);
        $registeredPlugins = $refProperties['registeredPlugins']->getValue($this->debug);
        $registeredPlugins->removeAll($registeredPlugins);  // (ie SplObjectStorage->removeAll())
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
    }

    /**
     * Util to output to console / help in creation of tests
     *
     * @return void
     */
    public function stderr()
    {
        $args = \array_map(function ($val) {
            return $val === null
                ? 'null'
                : \print_r($val, true);
        }, \func_get_args());
        $glue = \func_num_args() > 2
            ? ', '
            : ' = ';
        \fwrite(STDERR, \implode($glue, $args) . "\n");
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
                    'html' => '<li class="m_log"><span class="t_null">null</span></li>',
                    'text' => 'null',
                    'script' => 'console.log(null);',
                ),
            ),
        );
    }

    /**
     * Test Method's log-entry, return value, output, etc
     *
     * @param string|null $method debug method to call or null/false to just test against last log entry
     * @param array       $args   method arguments
     * @param array|false $tests  array of 'route' => 'string
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
        if (\is_array($method)) {
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
                $logEntryTemp = $logEntry;
                if (\is_callable($outputExpect)) {
                    \call_user_func($outputExpect, $logEntryTemp);
                } elseif (\is_string($outputExpect)) {
                    $logEntryTemp = $this->logEntryToArray($logEntryTemp);
                    $this->assertStringMatchesFormat($outputExpect, \json_encode($logEntryTemp), 'log entry does not match format');
                } else {
                    $logEntryTemp = $this->logEntryToArray($logEntryTemp);
                    if (isset($outputExpect[2]['file']) && $outputExpect[2]['file'] === '*') {
                        unset($outputExpect[2]['file']);
                        unset($logEntryTemp[2]['file']);
                    }
                    $this->assertEquals($outputExpect, $logEntryTemp);
                }
                continue;
            } elseif ($test == 'custom') {
                \call_user_func($outputExpect, $logEntry);
                continue;
            } elseif ($test == 'notLogged') {
                $this->assertSame($logCountBefore, $this->debug->getData($countPath), 'failed asserting nothing logged');
                continue;
            } elseif ($test == 'return') {
                if (\is_string($outputExpect)) {
                    $this->assertStringMatchesFormat($outputExpect, (string) $return, 'return value does not match format');
                } else {
                    $this->assertSame($outputExpect, $return, 'return value not same');
                }
                continue;
            }
            if ($test === 'streamAnsi') {
                // $routeObj = new \bdk\Debug\Route\Stream($this->debug);
                $routeObj = $this->debug->routeStream;
                $routeObj->setCfg('stream', 'php://temp');
            } else {
                $prop = 'route'.\ucfirst($test);
                $routeObj = $this->debug->{$prop};
            }
            if (\in_array($test, array('chromeLogger','firephp'))) {
                // remove data - sans the logEntry we're interested in
                $dataBackup = array(
                    'alerts' => $this->debug->getData('alerts'),
                    'log' => $this->debug->getData('log'),
                    // 'logSummary' => $this->debug->getData('logSummary'),
                );
                $this->debug->setData('alerts', array());
                $this->debug->setData('log', array($logEntry));
                /*
                    We'll call processLogEntries directly
                */
                $event = new \bdk\PubSub\Event(
                    $this->debug,
                    array(
                        'headers' => array(),
                        'return' => '',
                    )
                );
                $routeObj->processLogEntries($event, 'debug.output', $this->debug->eventManager);
                $this->debug->setData($dataBackup);
                $headers = $event['headers'];
                if ($test == 'chromeLogger') {
                    /*
                        Decode the chromelogger header and get rows data
                    */
                    $rows = \json_decode(\base64_decode($headers[0][1]), true)['rows'];
                    // entry is nested inside a group
                    $output = $rows[\count($rows)-2];
                    if (\is_string($outputExpect)) {
                        $output = \json_encode($output);
                    }
                } else {
                    if (\is_string($outputExpect)) {
                        $outputExpect = \preg_replace('/^(X-Wf-1-1-1-)\S+\b/m', '$1%d', $outputExpect);
                    }
                    /*
                        Filter just the log entry headers
                    */
                    $headersNew = array();
                    foreach ($headers as $header) {
                        if (\strpos($header[0], 'X-Wf-1-1-1') === 0) {
                            $headersNew[] = $header[0].': '.$header[1];
                        }
                    }
                    // entry is nested inside a group
                    $output = $headersNew[\count($headersNew)-2];
                }
            } else {
                $refMethods = &$this->getSharedVar('reflectionMethods');
                if (!isset($refMethods[$test])) {
                    $refMethod = new \ReflectionMethod($routeObj, 'processLogEntryViaEvent');
                    $refMethod->setAccessible(true);
                    $refMethods[$test] = $refMethod;
                }
                $output = $refMethods[$test]->invoke($routeObj, $logEntry);
            }
            if (\is_callable($outputExpect)) {
                $outputExpect($output);
            } elseif (\is_array($outputExpect)) {
                if (isset($outputExpect['contains'])) {
                    $message = "\e[1m".$test." doesn't contain\e[0m";
                    if ($test === 'streamAnsi') {
                        $message .= "\nactual: ".\str_replace("\e", '\e', $output);
                    }
                    $this->assertContains($outputExpect['contains'], $output, $message);
                } else {
                    $this->assertSame($outputExpect, $output, "\e[1m".$test." not same\e[0m");
                }
            } else {
                $output = \preg_replace("#^\s+#m", '', $output);
                $outputExpect = \preg_replace('#^\s+#m', '', $outputExpect);
                // @see https://github.com/sebastianbergmann/phpunit/issues/3040
                $output = \str_replace("\r", '[\\r]', $output);
                $outputExpect = \str_replace("\r", '[\\r]', $outputExpect);
                $message = "\e[1m".$test." not same\e[0m";
                if ($test === 'streamAnsi') {
                    $message .= "\nactual: ".\str_replace("\e", '\e', $output);
                }
                $this->assertStringMatchesFormat(\trim($outputExpect), \trim($output), $message);
            }
        }
    }

    /**
     * Test output
     *
     * @param array $tests array of 'route' => 'string
     *                         ie array('html'=>'expected html')
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    public function outputTest($tests = array(), $debug = null)
    {
        if (!$debug) {
            $debug = $this->debug;
        }
        $backupRoute = $debug->getCfg('route');
        $regexLtrim = '#^\s+#m';
        foreach ($tests as $test => $expectContains) {
            $debug->setCfg('route', $test);
            $output = $debug->output();
            // $this->stderr($test, $output);
            $output = \preg_replace($regexLtrim, '', $output);
            $expectContains = \preg_replace($regexLtrim, '', $expectContains);
            if ($expectContains) {
                $this->assertStringMatchesFormat('%A'.$expectContains.'%A', $output);
            }
        }
        $debug->setCfg('route', $backupRoute);
    }

    protected function logEntryToArray(LogEntry $logEntry)
    {
        $return = \array_values($logEntry->export());
        \ksort($return[2]);
        return $return;
    }
}

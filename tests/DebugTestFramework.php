<?php

namespace bdk\DebugTests;

use bdk\CssXpath\DOMTestCase;
use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;
use bdk\Debug\Psr7lite\ServerRequest;
use bdk\DebugTests\PolyFill\AssertionTrait;
use bdk\PubSub\Event;

/**
 * PHPUnit tests for Debug class
 */
class DebugTestFramework extends DOMTestCase
{
    use AssertionTrait;

    const DATETIME_FORMAT = 'Y-m-d H:i:s T';

    public static $allowError = false;
    public static $obLevels = 0;

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
     * @return bool
     */
    protected function checkAbstractionType($var, $type)
    {
        $return = false;
        if (!$var instanceof Abstraction) {
            return false;
        }
        if ($type === 'object') {
            $keys = array(
                'cfgFlags',
                'className',
                'constants',
                'definition',
                'extends',
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
        } elseif ($type === 'resource') {
            $return = $var['type'] === 'resource' && isset($var['value']);
        }
        return $return;
    }

    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp(): void
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
        self::$obLevels = \ob_get_level();
        self::$allowError = false;
        $this->debug = Debug::getInstance(array(
            'collect' => true,
            'emailLog' => false,
            'emailTo' => null,
            'logEnvInfo' => false,
            'logRequestInfo' => false,
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
            'serviceProvider' => array(
                'request' => new ServerRequest(
                    'GET',
                    null,
                    array(
                        'REQUEST_METHOD' => 'GET', // presence of REQUEST_METHOD = not cli
                        'REQUEST_TIME_FLOAT' => $_SERVER['REQUEST_TIME_FLOAT'],
                        'SERVER_ADMIN' => 'ttesterman@test.com',
                    )
                ),
            ),
        ));
        $resetValues = array(
            'alerts'        => array(), // array of alerts.  alerts will be shown at top of output when possible
            'counts'        => array(), // count method
            'entryCountInitial' => 0,   // store number of log entries created during init
            'log'           => array(),
            'logSummary'    => array(),
            'outputSent'    => false,
        );
        $this->debug->setData($resetValues);
        $this->debug->stopWatch->reset();
        $this->debug->errorHandler->setData('errors', array());
        $this->debug->errorHandler->setData('errorCaller', array());
        $this->debug->errorHandler->setData('lastErrors', array());
        /*
        if (self::$haveWampPlugin === false) {
            $wamp = $this->debug->getRoute('wamp', true) === false
                ? new \bdk\Debug\Route\Wamp($this->debug, new \bdk\DebugTests\MockWampPublisher())
                : $this->debug->getRoute('wamp');
            $this->debug->addPlugin($wamp);
            self::$haveWampPlugin = true;
        }
        */

        $refProperties = &$this->getSharedVar('reflectionProperties');
        if (!isset($refProperties['channels'])) {
            $prop = new \ReflectionProperty($this->debug, 'channels');
            $prop->setAccessible(true);
            $refProperties['channels'] = $prop;
        }
        if (!isset($refProperties['groupPriorityStack'])) {
            $prop = new \ReflectionProperty('bdk\\Debug\\Method\\Group', 'groupPriorityStack');
            $prop->setAccessible(true);
            $refProperties['groupPriorityStack'] = $prop;
        }
        if (!isset($refProperties['groupStacks'])) {
            $prop = new \ReflectionProperty('bdk\\Debug\\Method\\Group', 'groupStacks');
            $prop->setAccessible(true);
            $refProperties['groupStacks'] = $prop;
        }
        if (!isset($refProperties['textDepth'])) {
            $prop = new \ReflectionProperty($this->debug->getDump('text'), 'depth');
            $prop->setAccessible(true);
            $refProperties['textDepth'] = $prop;
        }
        if (!isset($refProperties['registeredPlugins'])) {
            $prop = new \ReflectionProperty($this->debug, 'registeredPlugins');
            $prop->setAccessible(true);
            $refProperties['registeredPlugins'] = $prop;
        }

        $refProperties['channels']->setValue($this->debug, array());
        $refProperties['textDepth']->setValue($this->debug->getDump('text'), 0);
        $registeredPlugins = $refProperties['registeredPlugins']->getValue($this->debug);
        $registeredPlugins->removeAll($registeredPlugins);  // (ie SplObjectStorage->removeAll())

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
    public function tearDown(): void
    {
        $this->debug->setCfg('output', false);
        $subscribers = $this->debug->eventManager->getSubscribers(Debug::EVENT_OUTPUT);
        foreach ($subscribers as $subscriber) {
            $unsub = false;
            if ($subscriber instanceof \Closure) {
                $unsub = true;
            } elseif (\is_array($subscriber) && \strpos(\get_class($subscriber[0]), 'bdk\\Debug') === false) {
                $unsub = true;
            }
            if ($unsub) {
                $this->debug->eventManager->unsubscribe(Debug::EVENT_OUTPUT, $subscriber);
            }
        }
        $subscribers = $this->debug->eventManager->getSubscribers(Debug::EVENT_OUTPUT_LOG_ENTRY);
        foreach ($subscribers as $subscriber) {
            $this->debug->eventManager->unsubscribe(Debug::EVENT_OUTPUT_LOG_ENTRY, $subscriber);
        }
        /*
        while (\ob_get_level() > self::$obLevels) {
            \ob_end_clean();
        }
        */
    }

    /**
     * Util to output to console / help in creation of tests
     *
     * @return void
     */
    public function stderr()
    {
        $args = \array_map(function ($val) {
            $new = $val === null
                ? 'null'
                /*
                : (isset($this->debug)
                    ? $this->debug->getDump('text')->dump($val)
                    : \str_replace('\n', "\n", \json_encode($val, JSON_PRETTY_PRINT))
                );
                */
                : Debug::getInstance()->getDump('text')->dump($val);
            if (\json_last_error() !== JSON_ERROR_NONE) {
                $new = \var_export($val, true);
            }
            return $new;
        }, \func_get_args());
        $glue = \func_num_args() > 2
            ? ', '
            : ' = ';
        \fwrite(STDERR, \implode($glue, $args) . "\n");
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
        foreach ($tests as $test => $expect) {
            $debug->setCfg('route', $test);
            $output = $debug->output();
            $output = \preg_replace($regexLtrim, '', $output);
            if (\is_string($expect)) {
                $expectContains = \preg_replace($regexLtrim, '', $expect);
                if ($expectContains) {
                    $this->assertStringMatchesFormat('%A' . $expectContains . '%A', $output);
                }
            }
        }
        $debug->setCfg('route', $backupRoute);
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
    public function testMethod($method = null, $args = array(), $tests = array())
    {
        $countPath = $method === 'alert'
            ? 'alerts/__count__'
            : 'log/__count__';
        $dataPath = $method === 'alert'
            ? 'alerts/__end__'
            : 'log/__end__';
        $values = array(
            'logCountAfter' => 0,
            'logCountBefore' => $this->debug->getData($countPath),
            'return' => null,
        );
        if (\is_array($method)) {
            if (isset($method['dataPath'])) {
                $dataPath = $method['dataPath'];
            }
        } elseif ($method) {
            $this->debug->getRoute('wamp')->wamp->messages = array();
            $values['return'] = \call_user_func_array(array($this->debug, $method), $args);
            $this->file = __FILE__;
            $this->line = __LINE__ - 2;
        }
        $values['logCountAfter'] = $this->debug->getData($countPath);
        $logEntry = $this->debug->getData($dataPath);
        if ($logEntry) {
            $meta = $logEntry['meta'];
            \ksort($meta);
            $logEntry['meta'] = $meta;
        }
        if (!$tests) {
            $tests = array(
                'notLogged' => true,
            );
        }
        /*
        $this->stderr(array(
            'method' => $method,
            'args' => $args,
            'count' => count($tests),
            'dataPath' => $dataPath,
            'logEntry' => $logEntry,
        ));
        */
        foreach ($tests as $test => $expect) {
            // $this->stderr('test', $test);
            $logEntryTemp = $logEntry
                ? new LogEntry($logEntry->getSubject(), $logEntry['method'], $logEntry['args'], $logEntry['meta'])
                : new LogEntry($this->debug, 'null');
            $continue = $this->tstMethodPreTest($test, $expect, $logEntryTemp, $values);
            if ($continue === false) {
                // continue testing = false
                continue;
            }
            $routeObj = $this->tstMethodRouteObj($test);
            $output = $this->tstMethodOutput($test, $routeObj, $logEntryTemp, $expect);
            $this->tstMethodTest($test, $logEntryTemp, $expect, $output);
        }
    }

    private function tstMethodPreTest($test, $expect, LogEntry $logEntry, $vals = array())
    {
        switch ($test) {
            case 'entry':
                if (\is_callable($expect)) {
                    \call_user_func($expect, $logEntry);
                } elseif (\is_string($expect)) {
                    $logEntryArray = $this->logEntryToArray($logEntry);
                    $this->assertStringMatchesFormat($expect, \json_encode($logEntryArray), 'log entry does not match format');
                } else {
                    $logEntryArray = $this->logEntryToArray($logEntry);
                    if (isset($expect['meta']['file']) && $expect['meta']['file'] === '*') {
                        unset($expect['meta']['file']);
                        unset($logEntryArray['meta']['file']);
                    }
                    $this->assertEquals($expect, $logEntryArray);
                }
                return false;
            case 'custom':
                \call_user_func($expect, $logEntry);
                return false;
            case 'notLogged':
                $this->assertSame($vals['logCountBefore'], $vals['logCountAfter'], 'failed asserting nothing logged');
                return false;
            case 'return':
                if (\is_string($expect)) {
                    $this->assertStringMatchesFormat($expect, (string) $vals['return'], 'return value does not match format');
                    return false;
                }
                $this->assertSame($expect, $vals['return'], 'return value not same');
                return false;
        }
        return true;
    }

    /**
     * Get/Initialize route
     *
     * @param string $test route
     *
     * @return \bdk\Debug\Route\RouteInterface
     */
    private function tstMethodRouteObj($test)
    {
        switch ($test) {
            case 'streamAnsi':
                $routeObj = $this->debug->getRoute('stream');
                $routeObj->setCfg('stream', 'php://temp');
                return $routeObj;
            case 'wamp':
                return null;  // we'll rely on wamp's Debug::EVENT_LOG subscription
            default:
                return $this->debug->getRoute($test);
        }
    }

    /**
     * Get output from route
     *
     * @param string                               $test     chromeLogger|firephp|wampother
     * @param \bdk\Debug\Route\RouteInterface|null $routeObj Route instance
     * @param LogEntry                             $logEntry LogEntry
     * @param mixed                                $expect   expected output
     *
     * @return array|string
     */
    private function tstMethodOutput($test, $routeObj, LogEntry $logEntry, $expect)
    {
        $asString = \is_string($expect);
        if (\in_array($test, array('chromeLogger','firephp','wamp'))) {
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
            $event = new Event(
                $this->debug,
                array(
                    'headers' => array(),
                    'return' => '',
                )
            );
            if ($routeObj) {
                $routeObj->processLogEntries($event, Debug::EVENT_OUTPUT, $this->debug->eventManager);
            }
            $this->debug->setData($dataBackup);
            $headers = $event['headers'];
            switch ($test) {
                case 'chromeLogger':
                    /*
                        Decode the chromelogger header and get rows data
                    */
                    $rows = \json_decode(\base64_decode($headers[0][1]), true)['rows'];
                    // entry is nested inside a group
                    $output = $rows[\count($rows) - 2];
                    if ($asString) {
                        $output = \json_encode($output);
                    }
                    break;
                case 'firephp':
                    /*
                    if ($asString) {
                        $outputExpect = \preg_replace('/^(X-Wf-1-1-1-)\S+\b/m', '$1%d', $outputExpect);
                    }
                    */
                    /*
                        Filter just the log entry headers
                    */
                    $headersNew = array();
                    foreach ($headers as $header) {
                        if (\strpos($header[0], 'X-Wf-1-1-1') === 0) {
                            $headersNew[] = $header[0] . ': ' . $header[1];
                        }
                    }
                    // entry is nested inside a group
                    $output = $headersNew[\count($headersNew) - 2];
                    break;
                case 'wamp':
                    // $output = end($routeObj->wamp->messages);
                    $routeObj = $this->debug->getRoute('wamp');
                    // var_dump('get output:', $routeObj->wamp);
                    $messageIndex = \is_array($expect) && isset($expect['messageIndex'])
                        ? $expect['messageIndex']
                        : \count($routeObj->wamp->messages) - 1;
                    $output = isset($routeObj->wamp->messages[$messageIndex])
                        ? $routeObj->wamp->messages[$messageIndex]
                        : false;
                    if ($output) {
                        $output['args'][1] = $this->crate($output['args'][1]); // sort abstraction values
                        \ksort($output['args'][2]); // sort meta
                        $output = \json_encode($output);
                        if (!$asString) {
                            $output = \json_decode($output, true);
                        }
                    }
                    break;
            }
            return $output;
        }
        $refMethods = &$this->getSharedVar('reflectionMethods');
        if (!isset($refMethods[$test])) {
            $refMethod = new \ReflectionMethod($routeObj, 'processLogEntryViaEvent');
            $refMethod->setAccessible(true);
            $refMethods[$test] = $refMethod;
        }
        return $refMethods[$test]->invoke($routeObj, $logEntry);
    }

    /**
     * Test output from route
     *
     * @param string       $test         chromeLogger|firephp|wamp
     * @param LogEntry     $logEntry     LogEntry instance
     * @param string|array $outputExpect [description]
     * @param string|array $output       [description]
     *
     * @return void
     */
    private function tstMethodTest($test, LogEntry $logEntry, $outputExpect, $output)
    {
        if (\is_callable($outputExpect)) {
            $outputExpect($output, $logEntry);
            return;
        }
        if (\is_array($outputExpect)) {
            if ($test === 'wamp') {
                if ($outputExpect) {
                    unset($outputExpect['messageIndex']);
                    $outputExpect = $this->debug->arrayUtil->isList($outputExpect)
                        ? array('args' => $outputExpect)
                        : array('args' => \array_values($outputExpect));
                }
                $outputExpect = \array_replace_recursive(array(
                    'topic' => $this->debug->getRoute('wamp')->topic,
                    'args' => array(
                        null,       // method
                        array(),    // args
                        array(),    // meta
                    ),
                    'options' => array(),
                ), $outputExpect);
                $outputExpect['args'][2] = \array_merge(array(
                    'format' => 'raw',
                    'requestId' => $this->debug->getData('requestId'),
                ), $outputExpect['args'][2]);
                \ksort($outputExpect['args'][2]);
            }
            if (isset($outputExpect['contains'])) {
                $message = "\e[1m" . $test . " doesn't contain\e[0m";
                if ($test === 'streamAnsi') {
                    $message .= "\nactual: " . \str_replace("\e", '\e', $output);
                }
                if (\is_string($output)) {
                    $this->assertStringContainsString($outputExpect['contains'], $output, $message);
                    return;
                }
                $this->assertContains($outputExpect['contains'], $output, $message);
            } else {
                $message = "\e[1m" . $test . " not same\e[0m";
                $this->assertSame($outputExpect, $output, $message);
            }
            return;
        } elseif ($outputExpect === false) {
            if ($test === 'wamp') {
                $this->assertFalse($output);
                return;
            }
        }
        if ($test === 'firephp') {
            $outputExpect = \preg_replace('/^(X-Wf-1-1-1-)\S+\b/m', '$1%d', $outputExpect);
        }
        $output = \preg_replace('#^\s+#m', '', $output);
        $outputExpect = \preg_replace('#^\s+#m', '', $outputExpect);
        // @see https://github.com/sebastianbergmann/phpunit/issues/3040
        $output = \str_replace("\r", '[\\r]', $output);
        $outputExpect = \str_replace("\r", '[\\r]', $outputExpect);
        $message = "\e[1m" . $test . " not same\e[0m";
        if ($test === 'streamAnsi') {
            $message .= "\nexpect: " . \str_replace("\e", '\e', $outputExpect) . "\n";
            $message .= "\nactual: " . \str_replace("\e", '\e', $output);
        }
        $this->assertStringMatchesFormat(\trim($outputExpect), \trim($output), $message);
    }

    protected function deObjectifyData($data)
    {
        foreach (array('alerts','log') as $what) {
            if (!isset($data[$what])) {
                continue;
            }
            foreach ($data[$what] as $i => $v) {
                $data[$what][$i] = $this->logEntryToArray($v);
            }
        }
        if (isset($data['logSummary'])) {
            foreach ($data['logSummary'] as $i => $group) {
                foreach ($group as $i2 => $v) {
                    $data['logSummary'][$i][$i2] = $this->logEntryToArray($v);
                }
            }
        }
        return $data;
    }

    protected function getPrivateProp($obj, $prop)
    {
        $objRef = new \ReflectionObject($obj);
        $propRef = $objRef->getProperty($prop);
        $propRef->setAccessible(true);
        return $propRef->getValue($obj);
    }

    /**
     * convert log entry to array
     *
     * @param LogEntry $logEntry LogEntry instance
     * @param bool     $withKeys Whether to return key => value or just list
     *
     * @return array|null
     */
    protected function logEntryToArray($logEntry, $withKeys = true)
    {
        if (!$logEntry || !($logEntry instanceof LogEntry)) {
            return null;
        }
        $return = $logEntry->export();
        // convert any abstractions to array via json_encode
        // $return['args'] = \json_decode(\json_encode($return['args']), true);
        $return['args'] = $this->crate($return['args']);
        \ksort($return['meta']);
        if (!$withKeys) {
            return \array_values($return);
        }
        return $return;
    }

    /**
     * Arrayify abstractions
     * sort abtract values and meta values for consistency
     *
     * @param mixed $val args or value
     *
     * @return mixed
     */
    protected function crate($val)
    {
        if (\is_array($val)) {
            if (\in_array(Abstracter::ABSTRACTION, $val, true)) {
                // already arrayified abstraction... probably via wamp
                // go ahead and sort
                \ksort($val);
                if ($val['type'] === 'object') {
                    $val['methods'] = $this->crate($val['methods']);
                }
                return $val;
            }
            foreach ($val as $k => $v) {
                $val[$k] = $this->crate($v);
            }
            return $val;
        }
        if ($val instanceof Abstraction) {
            $val = $val->jsonSerialize();
            \ksort($val);
            return $val;
        }
        return $val;
    }
}

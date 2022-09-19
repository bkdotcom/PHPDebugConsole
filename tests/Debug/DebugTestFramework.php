<?php

namespace bdk\Test\Debug;

use bdk\CssXpath\DOMTestCase;
use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;
use bdk\ErrorHandler\Error;
use bdk\HttpMessage\ServerRequest;
use bdk\PubSub\Event;
use bdk\Test\PolyFill\AssertionTrait;

/**
 * PHPUnit tests for Debug class
 */
class DebugTestFramework extends DOMTestCase
{
    use AssertionTrait;

    const DATETIME_FORMAT = 'Y-m-d H:i:s T';

    public static $allowError = false;
    public static $obLevels = 0;

    public $debug;
    public $emailInfo = array();

    protected $helper;
    protected $file;
    protected $line;

    /**
     * Constructor
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        $this->helper = new \bdk\Test\Debug\Helper();
        parent::__construct($name, $data, $dataName);
    }

    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        self::$obLevels = \ob_get_level();
        self::$allowError = false;
        $this->resetDebug();

        /*
        if (self::$haveWampPlugin === false) {
            $wamp = $this->debug->getRoute('wamp', true) === false
                ? new \bdk\Debug\Route\Wamp($this->debug, new \bdk\Test\Debug\MockWampPublisher())
                : $this->debug->getRoute('wamp');
            $this->debug->addPlugin($wamp);
            self::$haveWampPlugin = true;
        }
        */

        $refProperties = &$this->getSharedVar('reflectionProperties');
        if (!isset($refProperties['inShutdown'])) {
            $refProp = new \ReflectionProperty('bdk\\Debug\\Method\\Group', 'inShutdown');
            $refProp->setAccessible(true);
            $refProperties['inShutdown'] = $refProp;
        }
        if (!isset($refProperties['groupStack'])) {
            $refProp = new \ReflectionProperty('bdk\\Debug\\Method\\Group', 'groupStack');
            $refProp->setAccessible(true);
            $refProperties['groupStack'] = $refProp->getValue($this->debug->methodGroup);
        }
        if (!isset($refProperties['groupPriorityStack'])) {
            $refProp = new \ReflectionProperty('bdk\\Debug\\Method\\GroupStack', 'priorityStack');
            $refProp->setAccessible(true);
            $refProperties['groupPriorityStack'] = $refProp;
        }
        if (!isset($refProperties['groupStacks'])) {
            $refProp = new \ReflectionProperty('bdk\\Debug\\Method\\GroupStack', 'groupStacks');
            $refProp->setAccessible(true);
            $refProperties['groupStacks'] = $refProp;
        }
        if (!isset($refProperties['textDepth'])) {
            $refProp = new \ReflectionProperty($this->debug->getDump('text'), 'depth');
            $refProp->setAccessible(true);
            $refProperties['textDepth'] = $refProp;
        }

        $refProperties['inShutdown']->setValue($this->debug->methodGroup, false);
        $refProperties['textDepth']->setValue($this->debug->getDump('text'), 0);

        /*
        $subscribers = $this->debug->eventManager->getSubscribers(Debug::EVENT_CUSTOM_METHOD);
        foreach ($subscribers as $subscriber) {
            // $subscriberObj = $subscriber[0];
            // if ($subscriberObj instanceof  \bdk\Debug\Plugin\Manager) {
            //     $registeredPlugins = $this->helper->getPrivateProp($subscriberObj, 'registeredPlugins');
            //     // clear registeredPlugins... but we don't unsubscribe?!
            //     $registeredPlugins->removeAll($registeredPlugins);  // (ie SplObjectStorage->removeAll())
            // }
            // if ($subscriberObj instanceof  \bdk\Debug\Plugin\Channel) {
            //     $channelsRef = new \ReflectionProperty($subscriberObj, 'channels');
            //     $channelsRef->setAccessible(true);
            //     $channelsRef->setValue($subscriberObj, array());
            // }
        }
        */

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
    }

    public static function tearDownAfterClass(): void
    {
        $GLOBALS['collectedHeaders'] = array();
        $GLOBALS['headersSent'] = array();
    }

    public function emailMock($to, $subject, $body, $addHeadersStr)
    {
        $this->emailInfo = array(
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'addHeadersStr' => $addHeadersStr,
        );
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
        $wampMessages = $this->debug->getRoute('wamp')->wamp->messages;
        $backupRoute = $debug->getCfg('route');
        $regexLtrim = '#^\s+#m';
        foreach ($tests as $test => $expect) {
            if ($test === 'wamp') {
                $output = $wampMessages;
            } else {
                $debug->setCfg('route', $test);
                $output = $debug->output();
            }
            if (\is_string($output)) {
                $output = \preg_replace($regexLtrim, '', $output);
            }
            if (\is_string($expect)) {
                $expectContains = \preg_replace($regexLtrim, '', $expect);
                if ($expectContains) {
                    $this->assertStringMatchesFormat('%A' . $expectContains . '%A', $output);
                }
            } elseif (\is_callable($expect)) {
                \call_user_func($expect, $output);
            } else {
                $this->assertSame($expect, $output);
            }
        }
        // note that this setting the route removes previous route plugins  (ie wamp)
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
            array(),
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
            'logCountBefore' => $this->debug->data->get($countPath),
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
        $values['logCountAfter'] = $this->debug->data->get($countPath);
        $logEntry = $this->debug->data->get($dataPath);
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
        $this->helper->stderr(array(
            'method' => $method,
            'args' => $args,
            'count' => count($tests),
            'dataPath' => $dataPath,
            'logEntry' => $logEntry,
        ));
        */
        if (isset($tests['chromeLogger']) && !isset($tests['serverLog'])) {
            $tests['serverLog'] = $tests['chromeLogger'];
        }
        foreach ($tests as $test => $expect) {
            // $this->helper->stderr('test', $test);
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
     * @param Abstraction $abs  Abstraction instance
     * @param string      $type array, object, or resource
     *
     * @return void
     */
    protected function assertAbstractionType(Abstraction $abs, $type = null)
    {
        $isAbsType = false;
        if (empty($abs['type'])) {
            $this->assertTrue($isAbsType);
            return;
        }
        $type = $type ?: $abs['type'];
        if ($type === 'object') {
            $keys = array(
                'cfgFlags',
                'className',
                'constants',
                'definition',
                'extends',
                'implements',
                'isAnonymous',
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
            $keysMissing = \array_diff($keys, \array_keys($abs->getValues()));
            $isAbsType = $abs['type'] === 'object'
                // && $var['className'] === 'stdClass'
                && \count($keysMissing) == 0;
        } elseif ($type === 'resource') {
            $isAbsType = $abs['type'] === 'resource' && isset($abs['value']);
        }
        $this->assertTrue($isAbsType);
    }

    protected function assertLogEntries($expect, $actual)
    {
        if (is_string($expect)) {
            // assume json
            $expect = \json_decode($expect, true);
            if ($expect === null) {
                throw new \Exception(\json_last_error(). ': ' . \json_last_error_msg());
            }
        }

        $actual = $this->helper->deObjectifyData($actual);
        $this->assertCount(
            \count($expect),
            $actual,
            'count actual (' . \count($actual) . ') does not match count expected (' . \count($expect) . ') : ' . \preg_replace('/=>\s*\n\s*array /', '=> array', \var_export($actual, true))
        );
        foreach ($expect as $i => $expectArr) {
            $actualArr = $actual[$i];
            $expectStr = \preg_replace('/=>\s*\n\s*array /', '=> array', \var_export($expectArr, true));
            $actualStr = \preg_replace('/=>\s*\n\s*array /', '=> array', \var_export($actualArr, true));
            try {
                $this->assertStringMatchesFormat($expectStr, $actualStr, 'LogEntry ' . $i . ' does not match');
            } catch (\Exception $e) {
                // echo 'actual = ' . $actualStr . "\n";
                throw $e;
            }
        }
    }

    protected function getLogEntries($count = null, $where = 'log')
    {
        $logEntries = $this->debug->data->get($where);
        if (\in_array($where, array('log','alerts'), true) || \preg_match('#^logSummary[\./]\d+$#', $where)) {
            if ($count) {
                $logEntries = \array_slice($logEntries, 0 - $count);
            }
        }
        return $this->helper->deObjectifyData($logEntries);
    }

    protected function resetDebug()
    {
        $this->debug = Debug::getInstance(array(
            'collect' => true,
            'emailFunc' => array($this, 'emailMock'),
            'emailLog' => false,
            'emailTo' => null,
            'logEnvInfo' => false,
            'logRequestInfo' => false,
            'logResponse' => false,
            'logRuntime' => true,
            'onError' => function (Error $error) {
                if (self::$allowError) {
                    $error['continueToNormal'] = false;
                    return;
                }
                // throw new \PHPUnit\Framework\Exception($error['message'] . ' @ ' . $error['file'] . ':' . $error['line'], 500);
                $error['throw'] = true;
            },
            'output' => true,
            'outputCss' => false,
            'outputHeaders' => false,
            'outputScript' => false,
            'route' => 'html',
            'serviceProvider' => array(
                'serverRequest' => new ServerRequest(
                    'GET',
                    null,
                    array(
                        'DOCUMENT_ROOT' => TEST_DIR . '/../tmp',
                        'REQUEST_METHOD' => 'GET',
                        'REQUEST_TIME_FLOAT' => $_SERVER['REQUEST_TIME_FLOAT'],
                        'SERVER_ADMIN' => 'testAdmin@test.com',
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
        $this->debug->data->set($resetValues);
        $this->debug->stopWatch->reset();
        $this->debug->errorHandler->setData('errors', array());
        $this->debug->errorHandler->setData('errorCaller', array());
        $this->debug->errorHandler->setData('lastErrors', array());

        // make sure we still have wamp plugin registered
        $wamp = $this->debug->getRoute('wamp');
        $wamp->wamp->messages = array();
        $this->debug->addPlugin($wamp);
    }

    private function tstMethodPreTest($test, $expect, LogEntry $logEntry, $vals = array())
    {
        switch ($test) {
            case 'entry':
                if (\is_callable($expect)) {
                    \call_user_func($expect, $logEntry);
                } elseif (\is_string($expect)) {
                    $logEntryArray = $this->helper->logEntryToArray($logEntry);
                    $this->assertStringMatchesFormat($expect, \json_encode($logEntryArray), 'log entry does not match format');
                } else {
                    $logEntryArray = $this->helper->logEntryToArray($logEntry);
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
     * @return \bdk\Debug\Route\RouteInterface|null
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
     * @param LogEntry                             $logEntry LogEntry instance
     * @param mixed                                $expect   expected output
     *
     * @return array|string
     */
    private function tstMethodOutput($test, $routeObj, LogEntry $logEntry, $expect)
    {
        $asString = \is_string($expect);
        if (\in_array($test, array('chromeLogger','firephp','serverLog','wamp'))) {
            // remove data - sans the logEntry we're interested in
            $dataBackup = array(
                'alerts' => $this->debug->data->get('alerts'),
                'log' => $this->debug->data->get('log'),
                // 'logSummary' => $this->debug->data->get('logSummary'),
            );
            $this->debug->data->set('alerts', array());
            $this->debug->data->set('log', array($logEntry));
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
            $this->debug->data->set($dataBackup);
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
                case 'serverLog':
                    $uri = $headers[0][1];
                    $filepath = TEST_DIR . '/../tmp' . $uri;
                    $json = \file_get_contents($filepath);
                    $rows = \json_decode($json, true)['rows'];
                    $output = $rows[\count($rows) - 2];
                    if ($asString) {
                        $output = \json_encode($output);
                    }
                    break;
                case 'wamp':
                    $routeObj = $this->debug->getRoute('wamp');
                    // var_dump('get output:', $routeObj->wamp);
                    $messageIndex = \is_array($expect) && isset($expect['messageIndex'])
                        ? $expect['messageIndex']
                        : \count($routeObj->wamp->messages) - 1;
                    $output = isset($routeObj->wamp->messages[$messageIndex])
                        ? $routeObj->wamp->messages[$messageIndex]
                        : false;
                    if ($output) {
                        $output['args'][1] = $this->helper->crate($output['args'][1]); // sort abstraction values
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
     * @param string|array $outputExpect expected output
     * @param string|array $output       actual output
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
                    'requestId' => $this->debug->data->get('requestId'),
                ), $outputExpect['args'][2]);
                \ksort($outputExpect['args'][2]);
            }
            if (isset($outputExpect['contains'])) {
                $message = "\e[1m" . $test . " doesn't contain\e[0m";
                if ($test === 'streamAnsi') {
                    $message .= "\nactual: " . \str_replace(array("\e","\n"), array('\e','\n'), $output);
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
            $message .= "\nexpect: " . \str_replace(array("\e", "\n"), array('\e', '\n'), $outputExpect) . "\n";
            $message .= "\nactual: " . \str_replace(array("\e", "\n"), array('\e', '\n'), $output);
        }
        $this->assertStringMatchesFormat(\trim($outputExpect), \trim($output), $message);
    }
}

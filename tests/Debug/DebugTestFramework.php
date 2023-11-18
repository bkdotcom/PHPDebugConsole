<?php

namespace bdk\Test\Debug;

use bdk\CssXpath\DOMTestCase;
use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\Reflection;
use bdk\ErrorHandler\Error;
use bdk\HttpMessage\ServerRequest;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\Helper;
use PHPUnit\Framework\ExpectationFailedException;
use RuntimeException;

/**
 * PHPUnit tests for Debug class
 */
class DebugTestFramework extends DOMTestCase
{
    use AssertionTrait;

    const DATETIME_FORMAT = 'Y-m-d H:i:s T';

    public static $allowError = false;
    public static $obLevels = 0;

    public static $debug;
    public $emailInfo = array();

    protected static $helper;
    protected static $outputMemoryUsage = false;
    protected $file;
    protected $line;

    /**
     * Constructor
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        self::$helper = new Helper();
        parent::__construct($name, $data, $dataName);
    }

    public function __get($name)
    {
        if ($name === 'helper') {
            return self::$helper;
        }
        if ($name === 'debug') {
            return self::$debug;
        }
        throw new RuntimeException('Access to unavailable property ' . __CLASS__ . '::' . $name);
    }

    /**
     * setUp is executed before each test
     *
     * @coversNothing
     *
     * @return void
     */
    public function setUp(): void
    {
        self::$allowError = false;
        self::$obLevels = \ob_get_level();
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

        $refProperties = self::getSharedVar('reflectionProperties');

        $refProperties['inShutdown']->setValue($this->debug->getPlugin('groupCleanup'), false);
        $refProperties['subscriberStack']->setValue($this->debug->eventManager, array());
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
        // self::memoryUsage();

        $this->debug->setCfg('output', false);
        $subscribers = $this->debug->eventManager->getSubscribers(Debug::EVENT_OUTPUT);
        foreach ($subscribers as $subscriberInfo) {
            $unsub = false;
            $callable = $subscriberInfo['callable'];
            if ($callable instanceof \Closure) {
                $unsub = true;
            } elseif (\is_array($callable) && \strpos(\get_class($callable[0]), 'bdk\\Debug') === false) {
                $unsub = true;
            }
            if ($unsub) {
                $this->debug->eventManager->unsubscribe(Debug::EVENT_OUTPUT, $callable);
            }
        }
        $subscribers = $this->debug->eventManager->getSubscribers(Debug::EVENT_OUTPUT_LOG_ENTRY);
        foreach ($subscribers as $subscriberInfo) {
            $callable = $subscriberInfo['callable'];
            $this->debug->eventManager->unsubscribe(Debug::EVENT_OUTPUT_LOG_ENTRY, $callable);
        }
    }

    public static function tearDownAfterClass(): void
    {
        $GLOBALS['collectedHeaders'] = array();
        $GLOBALS['headersSent'] = array();
        $GLOBALS['sessionMock']['status'] = PHP_SESSION_NONE;
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
                try {
                    if ($expectContains) {
                        self::assertStringMatchesFormat('%A' . $expectContains . '%A', $output);
                    }
                } catch (\Exception $e) {
                    // \bdk\Debug::varDump('expect', $expectContains);
                    // \bdk\Debug::varDump('actual', $output);
                    throw $e;
                }
            } elseif (\is_callable($expect)) {
                \call_user_func($expect, $output);
            } else {
                self::assertSame($expect, $output);
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
    public static function providerTestMethod()
    {
        return array(
            array(),
        );
    }

    private static function memoryUsage()
    {
        static $memoryUsage = 0;
        $memoryUsageNew = \memory_get_usage(true);
        $memoryDelta = $memoryUsageNew - $memoryUsage;
        if ($memoryUsage > 0 && $memoryDelta > 256000 && self::$outputMemoryUsage) {
            \fwrite(STDERR, \sprintf(
                'memory usage: %s (increase of %s) (%s)',
                \number_format($memoryUsageNew),
                \number_format($memoryDelta),
                \get_called_class()
            ) . "\n");
        }
        $memoryUsage = $memoryUsageNew;
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
            'output' => null,
        );
        if (\is_array($method)) {
            if (isset($method['dataPath'])) {
                $dataPath = $method['dataPath'];
            }
        } elseif ($method) {
            $this->debug->getRoute('wamp')->wamp->messages = array();
            \ob_start();
            $values['return'] = \call_user_func_array(array($this->debug, $method), $args);
            $values['output'] = \ob_get_clean();
            $this->file = __FILE__;
            $this->line = __LINE__ - 3;
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
            $logEntryTemp = $logEntry
                ? new LogEntry($logEntry->getSubject(), $logEntry['method'], $logEntry['args'], $logEntry['meta'])
                : new LogEntry($this->debug, 'null');
            $output = $test === 'output'
                ? $values['output']
                : null;
            try {
                $continue = $this->tstMethodPreTest($test, $expect, $logEntryTemp, $values);
                if ($continue === false) {
                    // continue testing = false
                    continue;
                }
                $routeObj = $this->tstMethodRouteObj($test);
                $output = $this->tstMethodOutput($test, $routeObj, $logEntryTemp, $expect);
                $this->tstMethodTest($test, $logEntryTemp, $expect, $output);
            } catch (ExpectationFailedException $e) {
                $trace = $e->getTrace();
                $file = null;
                $line = null;
                for ($i = 0, $count = \count($trace); $i < $count; $i++) {
                    $frame = $trace[$i];
                    if (\strpos($frame['class'], __NAMESPACE__) === 0) {
                        $file = $trace[$i - 1]['file'];
                        $line = $trace[$i - 1]['line'];
                        break;
                    }
                }
                $message = $test . ' has failed'
                    . ' - ' . $file . ':' . $line;
                if ($test === 'entry') {
                    \bdk\Debug::varDump(array(
                        'expect' => $expect,
                        'actual' => $this->helper->logEntryToArray($logEntryTemp),
                    ));
                } elseif (\is_string($expect) && \is_string($output)) {
                    echo $test . ':' . "\n";
                    echo 'expect: ' . \str_replace("\e", '\e', $expect) . "\n\n"
                        . 'actual: ' . \str_replace("\e", '\e', $output) . "\n\n";
                    if (\strpos($output, "\e") !== false) {
                        echo 'actual ansi: ' . $output . "\n\n";
                    }
                }
                throw new \PHPUnit\Framework\AssertionFailedError($message);
            }
        }
    }

    protected static function &getSharedVar($key)
    {
        static $values = array(
            'groupStack' => null,
            'reflectionMethods' => array(),
            'reflectionProperties' => array(),
        );
        if (empty($values['groupStack'])) {
            $values['groupStack'] = Reflection::propGet(\bdk\Debug::getPlugin('methodGroup'), 'groupStack');
        }
        if (empty($values['reflectionProperties'])) {
            $values['reflectionProperties'] = array(
                'groupPriorityStack' => new \ReflectionProperty('bdk\\Debug\\Plugin\\Method\\GroupStack', 'priorityStack'),
                'groupStacks' => new \ReflectionProperty('bdk\\Debug\\Plugin\\Method\\GroupStack', 'groupStacks'),
                'inShutdown' => new \ReflectionProperty('bdk\\Debug\\Plugin\\Method\\GroupCleanup', 'inShutdown'),
                'subscriberStack' => new \ReflectionProperty('bdk\\PubSub\\Manager', 'subscriberStack'),
                'textDepth' => new \ReflectionProperty('bdk\\Debug\\Dump\\Text', 'depth'),
            );
            \array_walk($values['reflectionProperties'], static function ($refProp) {
                $refProp->setAccessible(true);
            });
        }
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
    protected static function assertAbstractionType(Abstraction $abs, $type = null)
    {
        $isAbsType = false;
        if (empty($abs['type'])) {
            self::assertTrue($isAbsType);
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
        self::assertTrue($isAbsType);
    }

    protected static function assertLogEntries($expect, $actual)
    {
        if (\is_string($expect)) {
            // assume json
            $expect = \json_decode($expect, true);
            if ($expect === null) {
                throw new \Exception(\json_last_error(). ': ' . \json_last_error_msg());
            }
        }

        $actual = Helper::deObjectifyData($actual);
        self::assertCount(
            \count($expect),
            $actual,
            'count actual (' . \count($actual) . ') does not match count expected (' . \count($expect) . ') : ' . \preg_replace('/=>\s*\n\s*array /', '=> array', \var_export($actual, true))
        );
        foreach ($expect as $i => $expectArr) {
            $actualArr = $actual[$i];
            $expectStr = \preg_replace('/=>\s*\n\s*array /', '=> array', \var_export($expectArr, true));
            $actualStr = \preg_replace('/=>\s*\n\s*array /', '=> array', \var_export($actualArr, true));
            try {
                self::assertStringMatchesFormat($expectStr, $actualStr, 'LogEntry ' . $i . ' does not match');
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

    /**
     * @coversNothing
     */
    protected function resetDebug()
    {
        \bdk\Test\Debug\Mock\Php::$memoryLimit = null;
        self::$debug = Debug::getInstance(array(
            'collect' => true,
            'emailFunc' => array($this, 'emailMock'),
            'emailLog' => false,
            'emailTo' => null,
            'logEnvInfo' => false,
            'logFiles' => array(
                'filesExclude' => array(
                    'closure://function',
                    '/vendor/',
                ),
            ),
            'logRequestInfo' => false,
            'logResponse' => false,
            'logRuntime' => true,
            'onError' => static function (Error $error) {
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
                'response' => null,
                'serverRequest' => new ServerRequest(
                    'GET',
                    'http://test.example.com/noun/id/verb',
                    array(
                        'DOCUMENT_ROOT' => TEST_DIR . '/../tmp',
                        'REQUEST_METHOD' => 'GET',
                        'REQUEST_TIME_FLOAT' => \microtime(true),
                        'SERVER_ADMIN' => 'testAdmin@test.com',
                    )
                ),
            ),
            'sessionName' => null,
        ));
        $resetValues = array(
            'alerts'        => array(), // array of alerts.  alerts will be shown at top of output when possible
            'classDefinitions' => array(),
            'counts'        => array(), // count method
            'entryCountInitial' => 0,   // store number of log entries created during init
            'log'           => array(),
            'logSummary'    => array(),
            'outputSent'    => false,
            'runtime'       => array(),
        );
        $routeChromeLogger = $this->debug->routeChromeLogger;
        if ($routeChromeLogger) {
            $this->debug->pluginManager->removePlugin($routeChromeLogger);
        }
        $this->debug->data->set($resetValues);
        $this->debug->stopWatch->reset();
        $this->debug->errorHandler->setData('errors', array());
        $this->debug->errorHandler->setData('errorCaller', array());
        $this->debug->errorHandler->setData('lastErrors', array());
        $channels = Reflection::propGet($this->debug->getPlugin('channel'), 'channels');
        $channels = array(
            'general' => \array_intersect_key($channels['general'], \array_flip(array('Request / Response'))),
        );
        Reflection::propSet($this->debug->getPlugin('channel'), 'channels', $channels);
        Reflection::propSet($this->debug->getPlugin('methodReqRes'), 'serverParams', array());
        Reflection::propSet($this->debug->abstracter->abstractObject->definition, 'default', null);

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
                    self::assertStringMatchesFormat($expect, \json_encode($logEntryArray), 'log entry does not match format');
                } else {
                    $logEntryArray = $this->helper->logEntryToArray($logEntry);
                    if (isset($expect['meta']['file']) && $expect['meta']['file'] === '*') {
                        unset($expect['meta']['file']);
                        unset($logEntryArray['meta']['file']);
                    }
                    self::assertEquals($expect, $logEntryArray);
                }
                return false;
            case 'custom':
                \call_user_func($expect, $logEntry);
                return false;
            case 'notLogged':
                self::assertSame($vals['logCountBefore'], $vals['logCountAfter'], 'failed asserting nothing logged');
                return false;
            case 'output':
                self::assertStringMatchesFormatNormalized($expect, (string) $vals['output'], 'output not match format');
                return false;
            case 'return':
                if (\is_string($expect)) {
                    self::assertStringMatchesFormat($expect, (string) $vals['return'], 'return value does not match format');
                    return false;
                }
                self::assertSame($expect, $vals['return'], 'return value not same');
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
        if (\in_array($test, array('chromeLogger','firephp','serverLog','wamp'), true)) {
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
                $subscriptions = $routeObj->getSubscriptions();
                $subscriptions = (array) $subscriptions[Debug::EVENT_OUTPUT];
                foreach ($subscriptions as $methodName) {
                    $routeObj->{$methodName}($event, Debug::EVENT_OUTPUT, $this->debug->eventManager);
                }
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
        $refMethods = &self::getSharedVar('reflectionMethods');
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
                \is_string($output)
                    ? self::assertStringContainsString($outputExpect['contains'], $output, $message)
                    : self::assertContains($outputExpect['contains'], $output, $message);
                return;
            }
            $message = "\e[1m" . $test . " not same\e[0m";
            self::assertSame($outputExpect, $output, $message);
            return;
        }
        if ($outputExpect === false) {
            if ($test === 'wamp') {
                self::assertFalse($output);
                return;
            }
        }
        if ($test === 'firephp') {
            $outputExpect = \preg_replace('/^(X-Wf-1-1-1-)\S+\b/m', '$1%d', $outputExpect);
        }

        $message = "\e[1m" . $test . " not same\e[0m";
        self::assertStringMatchesFormatNormalized($outputExpect, $output, $message);
    }

    /**
     * Assert string matches format
     * Leading per-line whitespace is removed
     *
     * @param string $expect  expect format
     * @param string $actual  actual
     * @param string $message message to output when assert failes
     *
     * @return void
     */
    protected static function assertStringMatchesFormatNormalized($expect, $actual, $message = null)
    {
        $actual = \preg_replace('#^\s+#m', '', $actual);
        $expect = \preg_replace('#^\s+#m', '', $expect);
        // @see https://github.com/sebastianbergmann/phpunit/issues/3040
        $actual = \str_replace("\r", '[\\r]', $actual);
        $expect = \str_replace("\r", '[\\r]', $expect);

        $actual = \trim($actual);
        $expect = \trim($expect);

        $args = array($expect, $actual);
        if ($message) {
            $args[] = $message;
        }

        \call_user_func_array(array('PHPUnit\Framework\TestCase', 'assertStringMatchesFormat'), $args);
    }
}

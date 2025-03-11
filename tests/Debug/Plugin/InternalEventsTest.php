<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\ErrorHandler\Error;
use bdk\HttpMessage\ServerRequestExtended as ServerRequest;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Dump\AbstractValue
 * @covers \bdk\Debug\Plugin\InternalEvents
 * @covers \bdk\Debug\Plugin\Route
 * @covers \bdk\Debug\Route\Email
 * @covers \bdk\Debug\Route\Stream
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class InternalEventsTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    public function testDumpCustom()
    {
        $val = new Abstraction('someCustomValueType', array('foo' => '<b>bar&baz</b>'));
        $expect = '<span class="t_someCustomValueType"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
<ul class="array-inner list-unstyled">
\t<li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">&lt;b&gt;bar&amp;baz&lt;/b&gt;</span></li>
\t<li><span class="t_key">type</span><span class="t_operator">=&gt;</span><span class="t_string">someCustomValueType</span></li>
\t<li><span class="t_key">value</span><span class="t_operator">=&gt;</span><span class="t_null">null</span></li>
</ul><span class="t_punct">)</span></span></span>';
        $expect = \str_replace('\\t', "\t", $expect);
        $dumped = $this->debug->getDump('html')->valDumper->dump($val);
        self::assertSame($expect, $dumped);

        $callable = static function (Event $event) {
            $event['return'] = '<span>woo</span>';
            self::assertIsObject($event['valDumper']);
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_DUMP_CUSTOM, $callable);
        $dumped = $this->debug->getDump('html')->valDumper->dump($val);
        self::assertSame('<span class="t_someCustomValueType"><span>woo</span></span>', $dumped);
        $this->debug->eventManager->unsubscribe(Debug::EVENT_DUMP_CUSTOM, $callable);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testEmailLog()
    {
        parent::$allowError = true;

        $this->debug->setCfg(array(
            'emailLog' => 'always',
            'emailTo' => 'test@email.com', // need an email address to email to!
            'output' => false,  // email only sent if not outputting
        ));

        $internalEvents = $this->debug->getPlugin('internalEvents');

        //
        // Test that not emailed if nothing logged
        //
        $internalEvents->onShutdownLow();
        self::assertEmpty(self::$emailInfo);

        //
        // Test that emailed if something logged
        //
        $this->debug->log('this is a test');
        $this->debug->log(new \DateTime());
        $internalEvents->onShutdownLow();
        self::assertNotEmpty(self::$emailInfo);
        self::assertSame($this->debug->getCfg('emailTo'), self::$emailInfo['to']);
        self::assertSame('Debug Log', self::$emailInfo['subject']);
        self::assertContainsSerializedLog(self::$emailInfo['body']);
        self::$emailInfo = array();

        $this->debug->setCfg('emailLog', 'onError');

        //
        // Test that not emailed if no error
        //
        $internalEvents->onShutdownLow();
        self::assertEmpty(self::$emailInfo);

        //
        // Test that not emailed for notice
        //
        $undefinedVar;  // notice
        $internalEvents->onShutdownLow();
        self::assertEmpty(self::$emailInfo);

        //
        // Test that emailed if there's an error
        //
        // 1 / 0; // warning
        $this->debug->errorHandler->handleError(E_WARNING, 'you have been warned', __FILE__, __LINE__);
        $internalEvents->onShutdownLow();
        self::assertNotEmpty(self::$emailInfo);
        self::assertSame('Debug Log: Error', self::$emailInfo['subject']);
        self::assertContainsSerializedLog(self::$emailInfo['body']);
        self::$emailInfo = array();

        //
        // Test that not emailed if disabled
        //
        $this->debug->setCfg('emailLog', false);
        $internalEvents->onShutdownLow();
        self::assertEmpty(self::$emailInfo);
    }

    /*
    public function testOnError()
    {
        $error = new Error($this->debug->errorHandler, E_WARNING, 'test error', __FILE__, __LINE__);
        $error['throw'] = true;
        $errorValues = $error->getValues();
        $logCount = $this->debug->data->get('log/__count__');
        $this->debug->pluginInternalEvents->onError($error);
        self::assertSame($errorValues, $error->getValues());
        self::assertSame($logCount, $this->debug->data->get('log/__count__'));
    }
    */

    public function testErrorAsException()
    {
        /*
        $callable = function (\bdk\ErrorHandler\Error $error) {
            $error['throw'] = true;
        };
        $this->debug->errorHandler->eventManager->subscribe(\bdk\ErrorHandler::EVENT_ERROR, $callable, 1);
        */
        $e = null;
        try {
            $line = __LINE__ + 1;
            \trigger_error('some error');
        } catch (\Exception $e) {
        }
        // $this->debug->errorHandler->eventManager->unsubscribe(\bdk\ErrorHandler::EVENT_ERROR, $callable);
        self::assertSame(0, $this->debug->data->get('log/__count__'));
        self::assertInstanceOf('Exception', $e);
        self::assertSame(
            array(
                'message' => 'some error',
                'file' => __FILE__,
                'line' => $line,
            ),
            array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            )
        );
    }

    public function testErrorForceErrorOutput()
    {
        $error = new Error($this->debug->errorHandler, array(
            'type' => E_ERROR,
            'message' => 'fatality',
            'file' => __FILE__,
            'line' => __LINE__,
        ));
        $line = __LINE__ - 2;
        // cli
        // stream
        $this->debug->setCfg(array(
            'serviceProvider' => array(
                'serverRequest' => new ServerRequest('GET', '', array(
                    'argv' => array('foo','bar'),
                )),
            ),
            'route' => 'stream',
            // 'stream' => 'php://temp',
            'collect' => false,
        ));
        $this->debug->getRoute('stream')->setCfg('stream', 'php://temp');
        $this->debug->getPlugin('internalEvents')->onError($error);
        $logEntry = $this->helper->logEntryToArray($this->debug->data->get('log/__end__'));
        $logEntry['meta']['errorHash'] = '';
        $logEntry['meta']['trace'] = array();
        self::assertSame(array(
            'method' => 'error',
            'args' => array(
                'Fatal Error:',
                'fatality',
                __FILE__ . ' (line ' . $line . ')',
            ),
            'meta' => array(
                'channel' => 'general.phpError',
                // 'context' => null,
                'detectFiles' => true,
                'errorCat' => 'fatal',
                'errorHash' => '',
                'errorType' => E_ERROR,
                // 'evalLine' => null,
                'file' => __FILE__,
                'isSuppressed' => false,
                'line' => $line,
                'sanitize' => true,
                'trace' => array(),
                'uncollapse' => true,
            ),
        ), $logEntry);
    }

    public function testErrorCollectNoOutputYes()
    {
        $this->debug->setCfg(array(
            'collect' => false,
            'output' => true,
        ));
        $error = new Error($this->debug->errorHandler, array(
            'type' => E_NOTICE,
            'message' => 'Hi error',
            'file' => __FILE__,
            'line' => __LINE__,
        ));
        $this->debug->getPlugin('internalEvents')->onError($error);
        self::assertSame(0, $this->debug->data->get('log/__count__'));
        self::assertFalse($error['email']);
        self::assertFalse($error['inConsole']);
    }

    public function testErrorCollectNoOutputNo()
    {
        $this->debug->setCfg(array(
            'collect' => false,
            'output' => false,
        ));
        $error = new Error($this->debug->errorHandler, array(
            'type' => E_NOTICE,
            'message' => 'Hi error',
            'file' => __FILE__,
            'line' => __LINE__,
        ));
        $this->debug->getPlugin('internalEvents')->onError($error);
        self::assertSame(0, $this->debug->data->get('log/__count__'));
        self::assertArrayNotHasKey('error', $error);
        self::assertFalse($error['inConsole']);
    }

    public function testErrorSuppressed()
    {
        $file = __FILE__;
        $line = __LINE__;
        $error = new Error($this->debug->errorHandler, array(
            'file' => $file,
            'isSuppressed' => true,
            'line' => $line,
            'message' => 'stern warning',
            'type' => E_USER_WARNING,
        ));
        $this->debug->getPlugin('internalEvents')->onError($error);
        $logEntry = $this->debug->data->get('log/__end__');
        $logEntryValues = $logEntry->getValues();
        unset($logEntryValues['meta']['errorHash']);
        self::assertSame(array(
            'appendLog' => true,
            'args' => array(
                'User Warning:',
                'stern warning',
                $file . ' (line ' . $line . ')',
            ),
            'meta' => array(
                'detectFiles' => true,
                'file' => $file,
                'line' => $line,
                'uncollapse' => true,
                'errorCat' => 'warning',
                'errorType' => E_USER_WARNING,
                'icon' => "fa fa-at fa-lg",
                'isSuppressed' => true,
                'sanitize' => true,
            ),
            'method' => 'warn',
            'numArgs' => 3,
            'return' => null,
        ), $logEntryValues);
    }

    public function testShutdown()
    {
        if (false) {
            // test find exit
            exit;
        }
        $exitLine = __LINE__ - 2;

        $this->debug->setCfg(array(
            'output' => true,
            'exitCheck' => true,
            'outputHeaders' => true,
            'onOutput' => static function (Event $event) {
                $event['headers'][] = array('x-debug-text', 'success');
            },
        ));
        $closure = function () {
            $this->debug->log('in shutdown');
        };

        \ob_start();
        $this->debug->eventManager->subscribe(EventManager::EVENT_PHP_SHUTDOWN, $closure);
        $this->debug->eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
        $this->debug->eventManager->unsubscribe(EventManager::EVENT_PHP_SHUTDOWN, $closure);
        $this->debug->setCfg('onOutput', null);
        $output = \ob_get_clean();

        $logEntriesExpect = array(
            array(
                'method' => 'info',
                'args' => array(
                    'php.shutdown',
                ),
                'meta' => array(
                    'attribs' => array(
                        'class' => [
                            'php-shutdown',
                        ],
                    ),
                    'icon' => 'fa fa-power-off',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'in shutdown',
                ),
                'meta' => array(),
            ),
        );

        if (\bdk\Backtrace\Xdebug::isXdebugFuncStackAvail()) {
            \array_unshift($logEntriesExpect, array(
                'method' => 'warn',
                'args' => array(
                    'Potentially shutdown via exit:',
                    __FILE__ . ' (line ' . $exitLine . ')',
                ),
                'meta' => array(
                    'detectFiles' => true,
                    // 'evalLine' => null,
                    'file' => __FILE__,
                    'line' => $exitLine,
                    'uncollapse' => true,
                ),
            ));
            self::assertStringContainsString('Potentially shutdown via exit', $output);
        }

        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertSame($logEntriesExpect, $logEntries);
        self::assertContains(
            'x-debug-text: success',
            \bdk\Debug\headers_list()
        );
    }

    public function assertContainsSerializedLog($string)
    {
        $unserialized = \bdk\Debug\Utility\SerializeLog::unserialize($string);
        $rootInstance = $this->debug->rootInstance;
        $channelKeyRoot = $rootInstance->getCfg('channelKey', Debug::CONFIG_DEBUG);
        $channelNameRoot = $rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG);
        $expect = array(
            'alerts' => $this->helper->deObjectifyData($this->debug->data->get('alerts'), false),
            'classDefinitions' => $this->helper->deObjectifyData($this->debug->data->get('classDefinitions'), false),
            'log' => $this->helper->deObjectifyData($this->debug->data->get('log'), false),
            'logSummary' => $this->helper->deObjectifyData($this->debug->data->get('logSummary'), false),
            'requestId' => $this->debug->data->get('requestId'),
            'runtime' => $this->debug->data->get('runtime'),
            'config' => array(
                'channelIcon' => $rootInstance->getCfg('channelIcon', Debug::CONFIG_DEBUG),
                'channelKey' => $channelKeyRoot,
                'channelName' => $channelNameRoot,
                'channels' => \array_map(static function (Debug $channel) use ($channelKeyRoot) {
                    $channelKey = $channel->getCfg('channelKey', Debug::CONFIG_DEBUG);
                    return array(
                        'channelIcon' => $channel->getCfg('channelIcon', Debug::CONFIG_DEBUG),
                        'channelShow' => $channel->getCfg('channelShow', Debug::CONFIG_DEBUG),
                        'channelSort' => $channel->getCfg('channelSort', Debug::CONFIG_DEBUG),
                        'nested' => \strpos($channelKey, $channelKeyRoot . '.') === 0,
                    );
                }, $rootInstance->getChannels(true, true)),
                'logRuntime' => $this->debug->getCfg('logRuntime'),
            ),
            'version' => Debug::VERSION,
        );
        foreach ($expect['log'] as $i => $logEntryArray) {
            if (empty($logEntryArray[2])) {
                unset($expect['log'][$i][2]);
            }
        }
        /*
        foreach ($expect['logSummary'][0] as $i => $logEntryArray) {
            if (empty($logEntryArray[2])) {
                unset($expect['logSummary'][0][$i][2]);
            }
        }
        */
        self::assertEquals(
            $expect,
            $this->helper->deObjectifyData($unserialized)
        );
    }
}

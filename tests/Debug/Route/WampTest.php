<?php

namespace bdk\Test\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Route\Wamp;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Manager as EventManager;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Wamp route
 *
 * @covers \bdk\Debug\Route\Wamp
 * @covers \bdk\Debug\Route\WampHelper
 * @covers \bdk\Debug\Route\WampCrate
 */
class WampTest extends DebugTestFramework
{
    protected static $wamp;
    protected static $publisher;

    public function testConstruct()
    {
        self::$publisher = new \bdk\Test\Debug\Mock\WampPublisher();
        self::$wamp = new Wamp($this->debug, self::$publisher);
        self::assertInstanceOf('\\bdk\\Debug\\Route\\Wamp', self::$wamp);
    }

    public function testAppendsHeaders()
    {
        self::assertFalse($this->debug->getRoute('wamp')->appendsHeaders());
    }

    public function testErrorWithTrace()
    {
        $frames = array(
            array(
                'file' => '/path/to/file.php',
                'line' => 42,
                'function' => 'Foo::bar',
            ),
        );
        $this->testMethod(
            'error',
            array('Fatal Error', 'trace', __FILE__, $this->debug->meta('trace', $frames)),
            array(
                'wamp' => array(
                    'error',
                    array('Fatal Error', 'trace'),
                    array(
                        'caption' => 'trace',
                        'detectFiles' => true,
                        // 'evalLine' => null,
                        'file' => $this->file,
                        'foundFiles' => array(
                            __FILE__,
                        ),
                        'inclArgs' => false,
                        'inclInternal' => false,
                        'limit' => 0,
                        'line' => $this->line,
                        'sortable' => false,
                        'tableInfo' => array(
                            'class' => null,
                            'columns' => array(
                                array('key' => 'file'),
                                array('key' => 'line'),
                                array('key' => 'function'),
                            ),
                            'haveObjRow' => false,
                            'indexLabel' => null,
                            'rows' => array(),
                            'summary' => '',
                        ),
                        'trace' => $frames,
                        'uncollapse' => true,
                    ),
                ),
            )
        );
    }

    public function testCrateAbstraction()
    {
        $base64 = 'j/v9wNrF5i1abMXFW/4vVw==';
        $binary = \base64_decode($base64);
        $this->testMethod(
            'log',
            array(
                $this->debug->abstracter->crateWithVals(array("\x00" . 'foo' => 'bar')),
                $binary,
                \json_encode(array('foo' => 'bar',42,true,false,null)),
                '',
                __FILE__,
                $this->debug->meta('detectFiles'),
            ),
            array(
                'wamp' => array(
                    'log',
                    array(
                        array(
                            'debug' => Abstracter::ABSTRACTION,
                            'type' => Type::TYPE_ARRAY,
                            'value' => array('_b64_:AGZvbw==' => 'bar'),
                        ),
                        array(
                            'brief' => false,
                            'debug' => Abstracter::ABSTRACTION,
                            'percentBinary' => 62.5,
                            'strlen' => 16,
                            'strlenValue' => 16,
                            'type' => Type::TYPE_STRING,
                            'typeMore' => Type::TYPE_STRING_BINARY,
                            'value' => '8f fb fd c0 da c5 e6 2d 5a 6c c5 c5 5b fe 2f 57',
                        ),
                        array(
                            'attribs' => array('class' => array('highlight','language-json', 'no-quotes')),
                            'brief' => false,
                            'contentType' => 'application/json',
                            'debug' => Abstracter::ABSTRACTION,
                            'prettified' => true,
                            'prettifiedTag' => true,
                            // 'strlen' => 79,
                            // 'strlenValue' => 79,
                            'type' => Type::TYPE_STRING,
                            'typeMore' => Type::TYPE_STRING_JSON,
                            'value' => \json_encode(array('foo' => 'bar',42,true,false,null), JSON_PRETTY_PRINT),
                            'valueDecoded' => array(
                                'foo' => 'bar',42,true,false,null,
                                '__debug_key_order__' => array('foo',0,1,2,3),
                            ),
                        ),
                        '',
                        __FILE__,
                    ),
                    array(
                        'detectFiles' => true,
                        'foundFiles' => array(
                            __FILE__,
                        ),
                    ),
                ),
            )
        );
    }

    public function testGetSubscriptions()
    {
        self::assertTrue(self::$wamp->isConnected());
        $subs = self::$wamp->getSubscriptions();
        self::assertArrayHasKey(Debug::EVENT_BOOTSTRAP, $subs);
        self::assertArrayHasKey(Debug::EVENT_CONFIG, $subs);
        self::assertArrayHasKey(Debug::EVENT_LOG, $subs);
        self::assertArrayHasKey(ErrorHandler::EVENT_ERROR, $subs);
        self::assertArrayHasKey(EventManager::EVENT_PHP_SHUTDOWN, $subs);

        self::$publisher->connected = false;
        self::assertFalse(self::$wamp->isConnected());
        $subs = self::$wamp->getSubscriptions();
        self::assertSame(array(), $subs);

        $alert = $this->debug->data->get('alerts/__end__');
        self::assertSame('WAMP publisher not connected to WAMP router', $alert['args'][0]);
    }

    public function testInit()
    {
        self::$wamp->onBootstrap();
        $msg = self::$publisher->messages[0];
        self::assertSame('bdk.debug', $msg['topic']);
        self::assertSame('meta', $msg['args'][0]);
        $expect = array(
            'processId' => \getmypid(),
            'HTTP_HOST' => null,
            'HTTPS' => null,
            'REMOTE_ADDR' => null,
            'REQUEST_METHOD' => 'GET',
            'REQUEST_TIME' => null,
            'REQUEST_URI' => '/',
            'SERVER_ADDR' => null,
            'SERVER_NAME' => null,
        );
        self::assertSame($expect, \array_intersect_key($expect, $msg['args'][1][0]));
        self::assertSame(array(
            'format' => 'raw',
            'requestId' => $this->debug->data->get('requestId'),
            'channelNameRoot' => 'general',
            'debugVersion' => Debug::VERSION,
            'drawer' => true,
            'interface' => 'http',
            'linkFilesTemplateDefault' => null,
        ), $msg['args'][2]);
    }

    public function testOnError()
    {
        $error = new Error($this->debug->errorHandler, array(
            'type' => E_WARNING,
            'message' => 'bogus error',
            'file' => __FILE__,
            'line' => 42,
        ));
        $this->debug->getRoute('wamp')->onError($error);
        $msg = $this->debug->getRoute('wamp')->wamp->messages[0];
        // echo \json_encode($msg, JSON_PRETTY_PRINT) . "\n";
        self::assertSame('errorNotConsoled', $msg['args'][0]);
        self::assertSame(array(
            'Warning:',
            'bogus error',
            __FILE__ . ' (line 42)',
        ), $msg['args'][1]);
        self::assertSame(array(
            'class' => array('error'),
        ), $msg['args'][2]['attribs']);
    }

    public function testOnShutdown()
    {
        $this->debug->getRoute('wamp')->onShutdown();
        $msg = $this->debug->getRoute('wamp')->wamp->messages[0];
        // echo \json_encode($msg, JSON_PRETTY_PRINT) . "\n";
        self::assertSame(array(
            'endOutput',
            array(),
            array(
                'format' => 'raw',
                'requestId' => $this->debug->data->get('requestId'),
                'responseCode' => $this->debug->getResponseCode(),
            ),
        ), $msg['args']);
    }

    public function testProcessLogEntries()
    {
        $this->debug->setCfg('output', false);
        $this->debug->alert('someAlert');
        $this->debug->groupSummary();
        $this->debug->log('I\'m in the summary');
        $this->debug->groupEnd();
        $this->debug->log('log log log');
        $this->debug->getRoute('wamp')->processLogEntries();
        $messages = $this->debug->getRoute('wamp')->wamp->messages;
        // echo \json_encode($messages, JSON_PRETTY_PRINT) . "\n";
        self::assertCount(5, $messages);
    }
}

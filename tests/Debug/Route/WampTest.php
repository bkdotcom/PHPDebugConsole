<?php

namespace bdk\Test\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Route\Wamp;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Manager as EventManager;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Wamp route
 *
 * @covers \bdk\Debug\Route\Wamp
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
        $this->assertInstanceOf('\\bdk\\Debug\\Route\\Wamp', self::$wamp);
    }

    public function testAppendsHeaders()
    {
        $this->assertFalse($this->debug->getRoute('wamp')->appendsHeaders());
    }

    public function testCrateAbstraction()
    {
        $base64 = 'j/v9wNrF5i1abMXFW/4vVw==';
        $binary = \base64_decode($base64);
        $this->testMethod(
            'log',
            array(
                $this->debug->abstracter->crateWithVals(array('foo' => 'bar')),
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
                            'type' => Abstracter::TYPE_ARRAY,
                            'value' => array('foo' => 'bar'),
                        ),
                        array(
                            'debug' => Abstracter::ABSTRACTION,
                            'strlen' => 16,
                            'strlenValue' => 16,
                            'type' => Abstracter::TYPE_STRING,
                            'typeMore' => Abstracter::TYPE_STRING_BINARY,
                            'value' => '_b64_:' . $base64,
                        ),
                        array(
                            'addQuotes' => false,
                            'attribs' => array('class' => array('highlight','language-json')),
                            'contentType' => 'application/json',
                            'debug' => Abstracter::ABSTRACTION,
                            'prettified' => true,
                            'prettifiedTag' => true,
                            'strlen' => null,
                            'type' => Abstracter::TYPE_STRING,
                            'typeMore' => Abstracter::TYPE_STRING_JSON,
                            'value' => \json_encode(array('foo' => 'bar',42,true,false,null), JSON_PRETTY_PRINT),
                            'valueDecoded' => array(
                                'foo' => 'bar',42,true,false,null,
                                '__debug_key_order__' => array('foo',0,1,2,3),
                            ),
                            'visualWhiteSpace' => false,
                        ),
                        '',
                        __FILE__,
                    ),
                    array(
                        'detectFiles' => true,
                        'foundFiles' => array(
                            __FILE__,
                        ),
                    )
                ),
            )
        );
    }

    public function testGetSubscriptions()
    {
        $this->assertTrue(self::$wamp->isConnected());
        $subs = self::$wamp->getSubscriptions();
        $this->assertArrayHasKey(Debug::EVENT_BOOTSTRAP, $subs);
        $this->assertArrayHasKey(Debug::EVENT_CONFIG, $subs);
        $this->assertArrayHasKey(Debug::EVENT_LOG, $subs);
        $this->assertArrayHasKey(Debug::EVENT_PLUGIN_INIT, $subs);
        $this->assertArrayHasKey(ErrorHandler::EVENT_ERROR, $subs);
        $this->assertArrayHasKey(EventManager::EVENT_PHP_SHUTDOWN, $subs);

        self::$publisher->connected = false;
        $this->assertFalse(self::$wamp->isConnected());
        $subs = self::$wamp->getSubscriptions();
        $this->assertSame(array(), $subs);

        $alert = $this->debug->data->get('alerts/__end__');
        $this->assertSame('WAMP publisher not connected to WAMP router', $alert['args'][0]);
    }

    public function testInit()
    {
        self::$wamp->init();
        $msg = self::$publisher->messages[0];
        $this->assertSame('bdk.debug', $msg['topic']);
        $this->assertSame('meta', $msg['args'][0]);
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
        $this->assertSame($expect, \array_intersect_key($expect, $msg['args'][1][0]));
        $this->assertSame(array(
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
        $error = new Error($this->debug->errorHandler, E_WARNING, 'bogus error', __FILE__, 42);
        $this->debug->getRoute('wamp')->onError($error);
        $msg = $this->debug->getRoute('wamp')->wamp->messages[0];
        // echo \json_encode($msg, JSON_PRETTY_PRINT) . "\n";
        $this->assertSame('errorNotConsoled', $msg['args'][0]);
        $this->assertSame(array(
            'Warning:',
            'bogus error',
            __FILE__ . ' (line 42)',
        ), $msg['args'][1]);
        $this->assertSame(array(
            'class' => array('error'),
        ), $msg['args'][2]['attribs']);
    }

    public function testOnShutdown()
    {
        $this->debug->getRoute('wamp')->onShutdown();
        $msg = $this->debug->getRoute('wamp')->wamp->messages[0];
        // echo \json_encode($msg, JSON_PRETTY_PRINT) . "\n";
        $this->assertSame(array(
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
        $this->assertCount(5, $messages);
    }
}

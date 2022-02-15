<?php

namespace bdk\Test\Debug\Route;

use bdk\Debug\Route\Wamp;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Wamp route
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

    public function testGetSubscriptions()
    {
        $this->assertTrue(self::$wamp->isConnected());
        $subs = self::$wamp->getSubscriptions();
        $this->assertArrayHasKey(\bdk\Debug::EVENT_BOOTSTRAP, $subs);
        $this->assertArrayHasKey(\bdk\Debug::EVENT_CONFIG, $subs);
        $this->assertArrayHasKey(\bdk\Debug::EVENT_LOG, $subs);
        $this->assertArrayHasKey(\bdk\Debug::EVENT_PLUGIN_INIT, $subs);
        $this->assertArrayHasKey(\bdk\ErrorHandler::EVENT_ERROR, $subs);
        $this->assertArrayHasKey(\bdk\PubSub\Manager::EVENT_PHP_SHUTDOWN, $subs);

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
            'debugVersion' => \bdk\Debug::VERSION,
            'drawer' => true,
            'interface' => 'http',
            'linkFilesTemplateDefault' => null,
        ), $msg['args'][2]);
    }

    public function testOnError()
    {
        $error = new \bdk\ErrorHandler\Error($this->debug->errorHandler, E_WARNING, 'bogus error', __FILE__, 42);
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

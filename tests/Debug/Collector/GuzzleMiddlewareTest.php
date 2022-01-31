<?php

namespace bdk\Test\Debug\Collector;

// use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Collector\GuzzleMiddleware;
// use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * PHPUnit tests for GuzzleMiddleware
 */
class GuzzleMiddlewareTest extends DebugTestFramework
{
    private static $client;
    private static $middleware;

    public static function setUpBeforeClass(): void
    {
        if (\class_exists('GuzzleHttp\\Handler\\MockHandler') === false) {
            return;
        }
        $mockHandler = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], 'Hello World'),
            new Response(202, ['Content-Length' => 0]),
            new Response(200, [], 'Test'),
            new RequestException('Error Communicating with Server', new Request('GET', 'test')),
        ]);
        self::$middleware = new GuzzleMiddleware(array(
            'asyncResponseWithRequest' => true,
            'inclRequestBody' => true,
            'inclResponseBody' => true,
        ));

        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(self::$middleware, 'PHPDebugConsole');
        self::$client = new Client([
            'handler' => $handlerStack,
        ]);
    }

    /**
     * Test plain 'ol request'
     *
     * @return void
     */
    public function testSyncronous()
    {
        if (PHP_VERSION_ID < 50500) {
            $this->markTestSkipped('guzzle middleware is php 5.5+');
        }
        self::$client->request('GET', 'http://example.com/');
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.Guzzle" data-icon="fa fa-exchange">
                <div class="group-header">%sGuzzle(%sGET%shttp://example.com/%s)</span></div>
                <ul class="group-body">
                    <li class="m_log" data-channel="general.Guzzle">%srequest headers</span> = <span class="t_string">GET / HTTP/1.1%A</li>
                    <li class="m_log" data-channel="general.Guzzle">%srequest body</span> = <span class="t_null">null</span></li>
                    <li class="m_time" data-channel="general.Guzzle"><span class="no-quotes t_string">time: %f %s</span></li>
                    <li class="m_log" data-channel="general.Guzzle"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/ 1.1 200 OK<span class="ws_r"></span><span class="ws_n"></span>
                    X-Foo: Bar</span></li>
                    <li class="m_log" data-channel="general.Guzzle"><span class="no-quotes t_string">response body</span> = <span class="t_string">Hello World</span></li>
                </ul>
                </li>',
            'wamp' => function ($messages) {
                $messages = \array_map(function ($message) {
                    // unset($message['args'][2]['requestId']);
                    // unset($message['args'][2]['format']);
                    return array(
                        'method' => $message['args'][0],
                        'args' => $message['args'][1],
                        // 'meta' => $message['args'][2],
                    );
                }, $messages);
                $expect = array(
                    array(
                        'method' => 'groupCollapsed',
                        'args' => array(
                            'Guzzle',
                            'GET',
                            'http://example.com/',
                        ),
                    ),
                    array(
                        'method' => 'log',
                        'args' => array(
                            'request headers',
                            $messages[1]['args'][1],
                        ),
                    ),
                    array(
                        'method' => 'log',
                        'args' => array(
                            'request body',
                            null,
                        ),
                    ),
                    array(
                        'method' => 'time',
                        'args' => array(
                            // 'time: 2.157 ms',
                            $messages[3]['args'][0],
                        ),
                    ),
                    array(
                        'method' => 'log',
                        'args' => array(
                            'response headers',
                            $messages[4]['args'][1],
                        ),
                    ),
                    array(
                        'method' => 'log',
                        'args' => array(
                            'response body',
                            'Hello World',
                        ),
                    ),
                    array(
                        'method' => 'groupEnd',
                        'args' => array(),
                    ),
                );
                $this->assertSame($expect, $messages);
            },
        ));
    }

    /**
     * Test asyncronous request'
     *
     * @return void
     */
    public function testAsyncronous()
    {
        if (PHP_VERSION_ID < 50500) {
            $this->markTestSkipped('guzzle middleware is php 5.5+');
        }
        self::$client->requestAsync('GET', 'http://example.com/')->wait();
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.Guzzle" data-icon="fa fa-exchange" id="guzzle%s">
                <div class="group-header">%sGuzzle(%sGET%shttp://example.com/%s)</span></div>
                <ul class="group-body">
                    <li class="m_info" data-channel="general.Guzzle" data-icon="fa fa-random"><span class="no-quotes t_string">asyncronous</span></li>
                    <li class="m_log" data-channel="general.Guzzle">%srequest headers</span> = <span class="t_string">GET / HTTP/1.1%A</li>
                    <li class="m_log" data-channel="general.Guzzle">%srequest body</span> = <span class="t_null">null</span></li>
                    <li class="m_time" data-channel="general.Guzzle"><span class="no-quotes t_string">time: %f %s</span></li>
                    <li class="m_log" data-channel="general.Guzzle"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/ 1.1 202 Accepted<span class="ws_r"></span><span class="ws_n"></span>
                    Content-Length: 0</span></li>
                    <li class="m_log" data-channel="general.Guzzle"><span class="no-quotes t_string">response body</span> = <span class="t_null">null</span></li>
                </ul>
                </li>',
            'wamp' => function ($messages) {
                $messages = \array_map(function ($message) {
                    unset($message['args'][2]['requestId']);
                    unset($message['args'][2]['format']);
                    return array(
                        'method' => $message['args'][0],
                        'args' => $message['args'][1],
                        'meta' => $message['args'][2],
                    );
                }, $messages);
                $id = $messages[0]['meta']['attribs']['id'];
                $expect = array(
                    0 => array(
                        'method' => 'groupCollapsed',
                        'args' => array(
                            'Guzzle',
                            'GET',
                            'http://example.com/',
                        ),
                        'meta' => array(
                            'icon' => 'fa fa-exchange',
                            'attribs' => array(
                                'id' => $id,
                                'class' => array(),
                            ),
                            'channel' => 'general.Guzzle',
                        ),
                    ),
                    1 => array(
                        'method' => 'info',
                        'args' => array(
                            'asyncronous',
                        ),
                        'meta' => array(
                            'icon' => 'fa fa-random',
                            'channel' => 'general.Guzzle',
                        ),
                    ),
                    2 => array(
                        'method' => 'log',
                        'args' => array(
                            'request headers',
                            $messages[2]['args'][1],
                        ),
                        'meta' => array(
                            'redact' => true,
                            'channel' => 'general.Guzzle',
                        ),
                    ),
                    3 => array(
                        'method' => 'log',
                        'args' => array(
                            'request body',
                            null,
                        ),
                        'meta' => array(
                            'redact' => true,
                            'channel' => 'general.Guzzle',
                        ),
                    ),
                    4 => array(
                        'method' => 'groupEnd',
                        'args' => array(),
                        'meta' => array(
                            'channel' => 'general.Guzzle',
                        ),
                    ),
                    5 => array(
                        'method' => 'time',
                        'args' => array(
                            $messages[5]['args'][0],
                        ),
                        'meta' => array(
                            'appendGroup' => $id,
                            'channel' => 'general.Guzzle',
                        ),
                    ),
                    6 => array(
                        'method' => 'log',
                        'args' => array(
                            'response headers',
                            $messages[6]['args'][1],
                        ),
                        'meta' => array(
                            'redact' => true,
                            'appendGroup' => $id,
                            'channel' => 'general.Guzzle',
                        ),
                    ),
                    7 => array(
                        'method' => 'log',
                        'args' => array(
                            'response body',
                            null,
                        ),
                        'meta' => array(
                            'redact' => true,
                            'appendGroup'  => $id,
                            'channel' => 'general.Guzzle',
                        ),
                    ),
                );
                $this->assertSame($expect, $messages);
            }
        ));
    }

    /**
     * Test asyncronous request with result not attached to request
     *
     * @return void
     */
    public function testAsyncronousSeparate()
    {
        if (PHP_VERSION_ID < 50500) {
            $this->markTestSkipped('guzzle middleware is php 5.5+');
        }
        self::$middleware->setCfg('asyncResponseWithRequest', false);
        // $this->debug->getRoute('wamp')->wamp->messages = array();
        self::$client->requestAsync('GET', 'http://example.com/')->wait();
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.Guzzle" data-icon="fa fa-exchange" id="guzzle_%s">
                    <div class="group-header">%sGuzzle(%sGET%shttp://example.com/%s)</span></div>
                    <ul class="group-body">
                        <li class="m_info" data-channel="general.Guzzle" data-icon="fa fa-random"><span class="no-quotes t_string">asyncronous</span></li>
                        <li class="m_log" data-channel="general.Guzzle">%srequest headers</span> = <span class="t_string">GET / HTTP/1.1%A</li>
                        <li class="m_log" data-channel="general.Guzzle">%srequest body</span> = <span class="t_null">null</span></li>
                    </ul>
                </li>
                <li class="m_group" data-channel="general.Guzzle" data-icon="fa fa-exchange">
                    <div class="group-header">%sGuzzle Response(%sGET%shttp://example.com/%s)</span></div>
                    <ul class="group-body">
                        <li class="m_time" data-channel="general.Guzzle"><span class="no-quotes t_string">time: %f %s</span></li>
                        <li class="m_log" data-channel="general.Guzzle"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/ 1.1 200 OK</span></li>
                        <li class="m_log" data-channel="general.Guzzle"><span class="no-quotes t_string">response body</span> = <span class="t_string">Test</span></li>
                </ul>
                </li>',
        ));
    }

    /**
     * Test rejected request
     *
     * @return void
     */
    public function testRejected()
    {
        if (PHP_VERSION_ID < 50500) {
            $this->markTestSkipped('guzzle middleware is php 5.5+');
        }
        self::$middleware->setCfg('asyncResponseWithRequest', true);
        try {
            self::$client->request('GET', 'http://example.com/');
        } catch (\Exception $e) {
            1 + 1;
        }
        $this->outputTest(array(
            'html' => '<li class="expanded m_group" data-channel="general.Guzzle" data-icon="fa fa-exchange">
                    <div class="group-header">%sGuzzle(%sGET%shttp://example.com/%s)</span></div>
                    <ul class="group-body">
                        <li class="m_log" data-channel="general.Guzzle"><span class="no-quotes t_string">request headers</span> = <span class="t_string">GET / HTTP/1.1%A</li>
                        <li class="m_log" data-channel="general.Guzzle"><span class="no-quotes t_string">request body</span> = <span class="t_null">null</span></li>
                        <li class="m_warn" data-channel="general.Guzzle" data-detect-files="true" data-file="%s" data-line="%s"><span class="no-quotes t_string">GuzzleHttp\Exception\RequestException</span>, <span class="t_int">0</span>, <span class="t_string">Error Communicating with Server</span></li>
                        <li class="m_time" data-channel="general.Guzzle"><span class="no-quotes t_string">time: %f %s</span></li>
                    </ul>
                </li>',
        ));
    }
}

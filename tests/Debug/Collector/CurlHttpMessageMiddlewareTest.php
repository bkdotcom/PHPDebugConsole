<?php

namespace bdk\Test\Debug\Collector;

use bdk\CurlHttpMessage\Exception\RequestException;
use bdk\CurlHttpMessage\Handler\Mock as MockHandler;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Collector\CurlHttpMessageMiddleware;
use bdk\HttpMessage\Request;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\Stream;
use bdk\Test\Debug\DebugTestFramework;
use Exception;

/**
 * PHPUnit tests for CurlHttpMessageMiddleware
 *
 * @covers \bdk\Debug\Collector\AbstractAsyncMiddleware
 * @covers \bdk\Debug\Collector\CurlHttpMessageMiddleware
 */
class CurlHttpMessageMiddlewareTest extends DebugTestFramework
{
    private $url = 'http://example.com/';

    /**
     * Test plain 'ol request'
     *
     * @return void
     */
    public function testSyncronous()
    {
        $client = $this->getClient(array(
            (new Response(200, ''))->withHeader('X-Foo', 'Bar')->withBody(new Stream('Hello World')),
        ), false);
        $response = $client->request(
            'GET',
            $this->url,
            array(
                'body' => \json_encode(array('foo' => 'bar')),
            )
        );
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.CurlHttpMessage" data-icon="fa fa-exchange" id="curl_%s">
                <div class="group-header">%sCurlHttpMessage(%sGET%shttp://example.com/%s)</span></div>
                <ul class="group-body">
                    <li class="m_log" data-channel="general.CurlHttpMessage">%srequest headers</span> = <span class="t_string">GET / HTTP/1.1%A</li>
                    <li class="m_log" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">request body</span> = <span class="string-encoded tabs-container" data-type-more="json">
                        <nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">json</a><a class="active nav-link" data-target=".tab-2" data-toggle="tab" role="tab">parsed</a></nav>
                        <div class="tab-1 tab-pane" role="tabpanel"><span class="value-container" data-type="string"><span class="prettified">(prettified)</span> <span class="highlight language-json no-quotes t_string">{
                        &quot;foo&quot;: &quot;bar&quot;
                        }</span></span></div>
                        <div class="active tab-2 tab-pane" role="tabpanel"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                        <ul class="array-inner list-unstyled">
                        <li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">bar</span></li>
                        </ul><span class="t_punct">)</span></span></div>
                        </span></li>
                    <li class="m_time" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">time: %f %s</span></li>
                    <li class="m_log" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/1.1 200 OK<span class="ws_r"></span><span class="ws_n"></span>
                    X-Foo: Bar</span></li>
                    <li class="m_log" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">response body</span> = <span class="t_string">Hello World</span></li>
                </ul>
                </li>',
            'wamp' => function ($messages) {
                $messages = \array_map(static function ($message) {
                    return array(
                        'method' => $message['args'][0],
                        'args' => $message['args'][1],
                    );
                }, $messages);
                $expect = array(
                    array(
                        'method' => 'groupCollapsed',
                        'args' => array(
                            'CurlHttpMessage',
                            'GET',
                            $this->url,
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
                            array(
                                'attribs' => array(
                                    'class' => array(
                                        'highlight',
                                        'language-json',
                                        'no-quotes',
                                    ),
                                ),
                                'brief' => false,
                                'contentType' => 'application/json; charset=utf-8',
                                'debug' => Abstracter::ABSTRACTION,
                                'prettified' => true,
                                'prettifiedTag' => true,
                                // 'strlen' => 20,
                                // 'strlenValue' => 20,
                                'type' => Type::TYPE_STRING,
                                'typeMore' => Type::TYPE_STRING_JSON,
                                'value' => '{' . "\n"
                                    . '    "foo": "bar"' . "\n"
                                    . '}',
                                'valueDecoded' => array(
                                    'foo' => 'bar',
                                ),
                            ),
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
                $this->assertSame($expect, $this->helper->deObjectifyData($messages));
            },
        ));
    }

    /**
     * Test asynchronous request'
     *
     * @return void
     */
    public function testAsynchronous()
    {
        if (PHP_VERSION_ID < 50500) {
            self::markTestSkipped('requires php 5.5 (yield)');
        }
        $client = $this->getClient(array(
            (new Response(202, ''))->withHeader('Content-Length', 0),
        ), true);
        $response = $client->request('GET', $this->url)->wait();
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.CurlHttpMessage" data-icon="fa fa-exchange" id="curl_%s">
                <div class="group-header">%sCurlHttpMessage(%sGET%shttp://example.com/%s)</span></div>
                <ul class="group-body">
                    <li class="m_info" data-channel="general.CurlHttpMessage" data-icon="fa fa-random"><span class="no-quotes t_string">asynchronous</span></li>
                    <li class="m_log" data-channel="general.CurlHttpMessage">%srequest headers</span> = <span class="t_string">GET / HTTP/1.1%A</li>
                    <li class="m_time" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">time: %f %s</span></li>
                    <li class="m_log" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/1.1 202 Accepted<span class="ws_r"></span><span class="ws_n"></span>
                    Content-Length: 0</span></li>
                    <li class="m_log" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">response body</span> = <span class="t_string"></span></li>
                </ul>
                </li>',
            'wamp' => function ($messages) {
                $messages = \array_map(static function ($message) {
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
                            'CurlHttpMessage',
                            'GET',
                            $this->url,
                        ),
                        'meta' => array(
                            'icon' => 'fa fa-exchange',
                            'redact' => true,
                            'attribs' => array(
                                'id' => $id,
                                'class' => array(),
                            ),
                            'channel' => 'general.CurlHttpMessage',
                        ),
                    ),
                    1 => array(
                        'method' => 'info',
                        'args' => array(
                            'asynchronous',
                        ),
                        'meta' => array(
                            'icon' => 'fa fa-random',
                            'channel' => 'general.CurlHttpMessage',
                        ),
                    ),
                    2 => array(
                        'method' => 'log',
                        'args' => array(
                            'request headers',
                            $messages[2]['args'][1],
                        ),
                        'meta' => array(
                            'channel' => 'general.CurlHttpMessage',
                        ),
                    ),
                    /*
                    3 => array(
                        'method' => 'log',
                        'args' => array(
                            'request body',
                            null,
                        ),
                        'meta' => array(
                            'redact' => true,
                            'channel' => 'general.CurlHttpMessage',
                        ),
                    ),
                    */
                    3 => array(
                        'method' => 'groupEnd',
                        'args' => array(),
                        'meta' => array(
                            'channel' => 'general.CurlHttpMessage',
                        ),
                    ),
                    4 => array(
                        'method' => 'time',
                        'args' => array(
                            $messages[4]['args'][0],
                        ),
                        'meta' => array(
                            'appendGroup' => $id,
                            'channel' => 'general.CurlHttpMessage',
                        ),
                    ),
                    5 => array(
                        'method' => 'log',
                        'args' => array(
                            'response headers',
                            $messages[5]['args'][1],
                        ),
                        'meta' => array(
                            'appendGroup' => $id,
                            'channel' => 'general.CurlHttpMessage',
                        ),
                    ),
                    6 => array(
                        'method' => 'log',
                        'args' => array(
                            'response body',
                            '',
                        ),
                        'meta' => array(
                            'redact' => true,
                            'appendGroup'  => $id,
                            'channel' => 'general.CurlHttpMessage',
                        ),
                    ),
                );
                $this->assertSame($expect, $messages);
            },
        ));
    }

    /**
     * Test asynchronous request with result not attached to request
     *
     * @return void
     */
    public function testAsynchronousSeparate()
    {
        if (PHP_VERSION_ID < 50500) {
            self::markTestSkipped('requires php 5.5 (yield)');
        }

        $client = $this->getClient(array(
            (new Response(200, ''))->withBody(new Stream('Test')),
        ), true, array(
            'asyncResponseWithRequest' => false,
        ));
        $response = $client->request('GET', $this->url)->wait();
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->outputTest(array(
            'html' => '<li class="m_group" data-channel="general.CurlHttpMessage" data-icon="fa fa-exchange" id="curl_%s">
                    <div class="group-header">%sCurlHttpMessage(%sGET%shttp://example.com/%s)</span></div>
                    <ul class="group-body">
                        <li class="m_info" data-channel="general.CurlHttpMessage" data-icon="fa fa-random"><span class="no-quotes t_string">asynchronous</span></li>
                        <li class="m_log" data-channel="general.CurlHttpMessage">%srequest headers</span> = <span class="t_string">GET / HTTP/1.1%A</li>
                    </ul>
                </li>
                <li class="m_group" data-channel="general.CurlHttpMessage" data-icon="fa fa-exchange">
                    <div class="group-header">%sCurlHttpMessage Response(%sGET%shttp://example.com/%s)</span></div>
                    <ul class="group-body">
                        <li class="m_time" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">time: %f %s</span></li>
                        <li class="m_log" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">response headers</span> = <span class="t_string">HTTP/1.1 200 OK</span></li>
                        <li class="m_log" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">response body</span> = <span class="t_string">Test</span></li>
                    </ul>
                </li>',
        ));
    }

    /**
     * Test rejected request
     *
     * @return void
     */
    public function testSyncRejected()
    {
        $client = $this->getClient(array(
            new RequestException('Error Communicating with Server', new Request('GET', 'test')),
        ), false);
        $caught = false;
        try {
            $client->request('GET', $this->url);
        } catch (Exception $e) {
            $caught = true;
        }
        $this->assertTrue($caught);
        $this->outputTest(array(
            'html' => '<li class="expanded m_group" data-channel="general.CurlHttpMessage" data-icon="fa fa-exchange" id="curl_%s">
                    <div class="group-header">%sCurlHttpMessage(%sGET%shttp://example.com/%s)</span></div>
                    <ul class="group-body">
                        <li class="m_log" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">request headers</span> = <span class="t_string">GET / HTTP/1.1%A</li>
                        <li class="m_warn" data-channel="general.CurlHttpMessage" data-file="%s" data-line="%s"><span class="no-quotes t_string">bdk\CurlHttpMessage\Exception\RequestException</span>, <span class="t_int">0</span>, <span class="t_string">Error Communicating with Server</span></li>
                        <li class="m_time" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">time: %f %s</span></li>
                    </ul>
                </li>',
        ));
    }

    /**
     * Test rejected request
     *
     * @return void
     */
    public function testAsyncRejected()
    {
        if (PHP_VERSION_ID < 50500) {
            self::markTestSkipped('requires php 5.5 (yield)');
        }
        $client = $this->getClient(array(
            new RequestException('Error Communicating with Server', new Request('GET', 'test')),
        ), true);
        $caught = false;
        try {
            $client->request('GET', $this->url)->wait();
        } catch (Exception $e) {
            $caught = true;
        }
        $this->assertTrue($caught);
        $this->outputTest(array(
            'html' => '<li class="expanded m_group" data-channel="general.CurlHttpMessage" data-icon="fa fa-exchange" id="curl_%s">
                    <div class="group-header">%sCurlHttpMessage(%sGET%shttp://example.com/%s)</span></div>
                    <ul class="group-body">
                        <li class="m_info" data-channel="general.CurlHttpMessage" data-icon="fa fa-random"><span class="no-quotes t_string">asynchronous</span></li>
                        <li class="m_log" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">request headers</span> = <span class="t_string">GET / HTTP/1.1%A</li>
                        <li class="m_warn" data-channel="general.CurlHttpMessage" data-file="%s" data-line="%s"><span class="no-quotes t_string">bdk\CurlHttpMessage\Exception\RequestException</span>, <span class="t_int">0</span>, <span class="t_string">Error Communicating with Server</span></li>
                        <li class="m_time" data-channel="general.CurlHttpMessage"><span class="no-quotes t_string">time: %f %s</span></li>
                    </ul>
                </li>',
        ));
    }

    protected function getClient($queue = array(), $async = false, $middlewareOpts = array())
    {
        /*
        $queue = [
            new Response(200, '', ['X-Foo' => 'Bar'], 'Hello World'),
            new Response(202, '', ['Content-Length' => 0]),
            new Response(200, '', [], 'Test'),
            new RequestException('Error Communicating with Server', new Request('GET', 'test')),
        ];
        */
        $class = $async
            ? 'bdk\\CurlHttpMessage\\ClientAsync'
            : 'bdk\\CurlHttpMessage\\Client';
        $client = new $class([
            'handler' => new MockHandler($queue),
        ]);
        $middlewareOpts = \array_merge(array(
            'asyncResponseWithRequest' => true,
            'inclRequestBody' => true,
            'inclResponseBody' => true,
        ), $middlewareOpts);
        $middleware = new CurlHttpMessageMiddleware($middlewareOpts);
        $client->getStack()->unshift($middleware);
        return $client;
    }
}

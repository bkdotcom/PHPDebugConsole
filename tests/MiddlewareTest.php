<?php

use bdk\Debug;
use bdk\Debug\Middleware;
use GuzzleHttp\Psr7\ServerRequest;
use mindplay\middleman\Dispatcher;

/**
 * PHPUnit tests for Debug class
 */
class MiddlewareTest extends DebugTestFramework
{

    public function testMiddleware()
    {
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            return;
        }
        $this->debug->addPlugin($this->debug->routeChromeLogger);
        // $debugMiddleware = new Middleware($this->debug);
        $mockMiddleware = new MockMiddleware($this->debug);
        $dispatcher = new Dispatcher([
            // $debugMiddleware,
            $this->debug->middleware,
            $mockMiddleware
        ]);
        $response = $dispatcher->dispatch(new ServerRequest('GET', '/'));
        $chromeLoggerHeader = $response->getHeaderLine(\bdk\Debug\Route\ChromeLogger::HEADER_NAME);
        $chromeLoggerData = \json_decode(\base64_decode($chromeLoggerHeader), true);
        $body = $response->getBody()->getContents();
        $this->assertContains(array(
            array('running mock middleware'),
            null,
            '',
        ), $chromeLoggerData['rows']);
        $this->assertContains('<li class="m_log"><span class="no-quotes t_string">running mock middleware</span></li>', $body);
    }
}

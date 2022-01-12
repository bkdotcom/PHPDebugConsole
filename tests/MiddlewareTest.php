<?php

namespace bdk\DebugTests;

use bdk\DebugTests\Mock\Middleware as MockMiddleware;
use GuzzleHttp\Psr7\ServerRequest;
use mindplay\middleman\Dispatcher;

/**
 * PHPUnit tests for Debug class
 */
class MiddlewareTest extends DebugTestFramework
{
    /**
     * @requires PHP >= 7.0
     */
    public function testMiddleware()
    {
        /*
        if (\version_compare(PHP_VERSION, '7.0', '<')) {
            $this->markTestSkipped('MiddleWare requires PHP 7.0');
        }
        */
        $this->debug->addPlugin($this->debug->getRoute('chromeLogger'));
        // $debugMiddleware = new Middleware($this->debug);
        $mockMiddleware = new MockMiddleware($this->debug);
        $dispatcher = new Dispatcher([
            // $debugMiddleware,
            $this->debug->middleware,
            $mockMiddleware,
        ]);
        $response = \method_exists($dispatcher, 'handle')
            ? $dispatcher->handle(new ServerRequest('GET', '/'))  // middleman 4.0
            : $dispatcher->dispatch(new ServerRequest('GET', '/'));  // older
        $chromeLoggerHeader = $response->getHeaderLine(\bdk\Debug\Route\ChromeLogger::HEADER_NAME);
        $chromeLoggerData = \json_decode(\base64_decode($chromeLoggerHeader), true);
        $body = $response->getBody()->getContents();
        $this->assertContains(array(
            array('running mock middleware'),
            null,
            '',
        ), $chromeLoggerData['rows']);
        $this->assertStringContainsString('<li class="m_log"><span class="no-quotes t_string">running mock middleware</span></li>', $body);
    }
}

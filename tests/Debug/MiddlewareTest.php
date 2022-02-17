<?php

namespace bdk\Test\Debug;

use bdk\Test\Debug\Mock\Middleware as MockMiddleware;
use Exception;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Stream;
use mindplay\middleman\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PHPUnit tests for Debug middleware
 */
class MiddlewareTest extends DebugTestFramework
{
    /**
     * @requires PHP >= 7.0
     */
    public function testMiddleware()
    {
        if (\version_compare(PHP_VERSION, '7.0', '<')) {
            // @requires does not work on 4.8.36  ?
            $this->markTestSkipped('MiddleWare requires PHP 7.0');
        }
        $this->debug->addPlugin($this->debug->getRoute('chromeLogger'));
        $dispatcher = new Dispatcher([
            $this->debug->middleware,
            new MockMiddleware($this->debug),
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

    public function testMiddlewareCatch()
    {
        if (\version_compare(PHP_VERSION, '7.0', '<')) {
            // @requires does not work on 4.8.36  ?
            $this->markTestSkipped('MiddleWare requires PHP 7.0');
        }
        parent::$allowError = true;
        $this->debug->middleware->setCfg(array(
            'catchException' => true,
            'onCaughtException' => function (Exception $e, ServerRequestInterface $request) {
                $msg = 'Middleware caught exception: ' . $e->getMessage();
                $stream = \fopen('php://temp', 'r+');
                \fwrite($stream, $msg);
                \fseek($stream, 0);
                $stream = new Stream($stream, []);
                return (new Response())->withBody($stream);
            }
        ));
        $dispatcher = new Dispatcher([
            $this->debug->middleware,
            new MockMiddleware($this->debug),
        ]);
        $request = new ServerRequest('GET', '/');
        $request = $request->withAttribute('throwException', true);
        $response = \method_exists($dispatcher, 'handle')
            ? $dispatcher->handle($request)  // middleman 4.0
            : $dispatcher->dispatch($request);  // older
        $body = $response->getBody()->getContents();
        $this->assertStringContainsString('Middleware caught exception: something went wrong', $body);
        $this->assertStringContainsString('<li class="m_log"><span class="no-quotes t_string">running mock middleware</span></li>', $body);
        $this->assertStringMatchesFormat('%A<li class="error-fatal m_error" data-channel="general.phpError" data-detect-files="true"><span class="no-quotes t_string">Fatal Error: </span><span class="t_string">Uncaught exception %sException%s with message something went wrong</span>, <span class="t_string">%s/Middleware.php (line %d)</span></li>%A', $body);
    }
}

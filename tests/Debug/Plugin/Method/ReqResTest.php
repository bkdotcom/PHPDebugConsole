<?php

namespace bdk\Test\Debug\Plugin\Method;

use bdk\Debug;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\ServerRequest;
use bdk\HttpMessage\Stream;
use bdk\Test\Debug\DebugTestFramework;
use bdk\Test\PolyFill\ExpectExceptionTrait;

/**
 * Test SerializeLog
 *
 * @covers \bdk\Debug\Plugin\Method\ReqRes
 */
class ReqResTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    public function testGetSubscriptions()
    {
        self::assertSame(array(
            Debug::EVENT_CONFIG,
            Debug::EVENT_CUSTOM_METHOD,
        ), \array_keys($this->debug->getPlugin('methodReqRes')->getSubscriptions()));
    }

    public function testGetInterface()
    {
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => new ServerRequest('GET', null, array(
                // 'REQUEST_METHOD' => 'GET',
            )),
        ));
        self::assertSame('http', $this->debug->getInterface());

        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => new ServerRequest('GET', null, array(
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                // 'REQUEST_METHOD' => 'GET',
            )),
        ));
        self::assertSame('http ajax', $this->debug->getInterface());

        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => new ServerRequest('GET', null, array(
                'PATH' => '.',
                'argv' => array('phpunit'),
            )),
        ));
        self::assertSame('cli', $this->debug->getInterface());

        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => new ServerRequest('GET', null, array(
                'argv' => array('phpunit'),
            )),
        ));
        self::assertSame('cli cron', $this->debug->getInterface());
    }

    public function testGetResponseCode()
    {
        self::assertSame(\http_response_code(), $this->debug->getResponseCode());
        $this->debug->setCfg('serviceProvider', array(
            'response' => new Response(200),
        ));
        self::assertSame(200, $this->debug->getResponseCode());
    }

    public function testGetResponseHeader()
    {
        self::assertSame('', $this->debug->getResponseHeader());
        $response = new Response(200);
        $response = $response->withHeader('Content-Type', 'text/html');
        $response = $response->withHeader('Content-Length', 1234);
        $this->debug->setCfg('serviceProvider', array(
            'response' => $response,
        ));
        self::assertSame('text/html', $this->debug->getResponseHeader());
        self::assertSame('1234', $this->debug->getResponseHeader('Content-Length'));
        self::assertSame(array('text/html'), $this->debug->getResponseHeader('content-type', false));
        self::assertSame(array('1234'), $this->debug->getResponseHeader('Content-Length', false));
    }

    public function testGetResponseHeaders()
    {
        $GLOBALS['collectedHeaders'] = array(
            array('X-Emitted-Header: I was emitted.. there is no HttpMessage Response', true),
        );
        self::assertSame(array(
            'X-Emitted-Header' => array(
                'I was emitted.. there is no HttpMessage Response',
            ),
        ), $this->debug->getResponseHeaders());

        $response = new Response(200);
        $response = $response->withHeader('Content-Type', 'text/html');
        $response = $response->withHeader('Content-Length', 1234);
        $this->debug->setCfg('serviceProvider', array(
            'response' => $response,
        ));
        self::assertSame(array(
            'Content-Type' => array('text/html'),
            'Content-Length' => array('1234'),
        ), $this->debug->getResponseHeaders());
        self::assertSame('HTTP/1.0 200 OK' . "\n"
            . 'Content-Type: text/html' . "\n"
            . 'Content-Length: 1234', $this->debug->getResponseHeaders(true));
    }

    public function testGetServerParam()
    {
        self::assertSame(null, $this->debug->getServerParam('REMOTE_ADDR'));
        self::assertSame('testAdmin@test.com', $this->debug->getServerParam('SERVER_ADMIN'));
    }

    public function testIsCli()
    {
        self::assertFalse($this->debug->isCli());
    }

    public function testRequestId()
    {
        self::assertStringMatchesFormat('%x', $this->debug->requestId());
    }

    public function testWriteToPsr7Response()
    {
        $this->debug->setCfg('outputHeaders', true);
        $html = '<!DOCTYPE html><html><head><title>WebCo WebPage</title></head><body>Clever Response</body></html>';
        $response = new Response();
        $response = $response->withBody(new Stream($html));
        $response = $this->debug->writeToResponse($response);
        $responseBodyContents = $response->getBody()->getContents();
        self::assertSame(array(), $response->getHeaders());
        self::assertStringMatchesFormat($html . '<div class="debug" %a</div>', $responseBodyContents);

        $this->debug->setCfg('route', 'chromeLogger');
        $response = new Response();
        $response = $response->withBody(new Stream($html));
        $response = $this->debug->writeToResponse($response);
        $responseBodyContents = $response->getBody()->getContents();
        self::assertSame(array(
            'X-ChromeLogger-Data',
        ), \array_keys($response->getHeaders()));
        self::assertSame($html, $responseBodyContents);
    }

    public function testWriteToHttpFoundationResponse()
    {
        $this->debug->setCfg('outputHeaders', true);
        $html = '<!DOCTYPE html><html><head><title>WebCo WebPage</title></head><body>Clever Response</body></html>';
        $response = new \Symfony\Component\HttpFoundation\Response($html);
        $response = $this->debug->writeToResponse($response);
        self::assertSame(array(
            'cache-control',
            'date',
        ), \array_keys($response->headers->all()));
        $htmlExpectFormat = \str_replace('</body>', '<div class="debug" %a</div>' . "\n" . '</body>', $html);
        self::assertStringMatchesFormat($htmlExpectFormat, $response->getContent());

        $this->debug->setCfg('route', 'chromeLogger');
        $response = new \Symfony\Component\HttpFoundation\Response($html);
        $response = $this->debug->writeToResponse($response);
        self::assertSame(array(
            'cache-control',
            'date',
            'x-chromelogger-data',
        ), \array_keys($response->headers->all()));
        self::assertSame($html, $response->getContent());
    }

    public function testWriteToResponseInvalid()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('writeToResponse expects ResponseInterface or HttpFoundationResponse, but null provided');
        $this->debug->writeToResponse(null);
    }
}

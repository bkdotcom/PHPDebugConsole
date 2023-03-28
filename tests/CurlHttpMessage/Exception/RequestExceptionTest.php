<?php

namespace bdk\Test\CurlHttpMessage\Exception;

/*
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
*/

use bdk\CurlHttpMessage\Exception\RequestException;
use bdk\Test\CurlHttpMessage\TestCase;
use Exception;

/**
 * @covers \bdk\CurlHttpMessage\Exception\RequestException
 */
class RequestExceptionTest extends TestCase
{
    public function testHasRequestAndResponse()
    {
        $req = $this->factory->request('GET', '/');
        $res = $this->factory->response(200);
        $e = new RequestException('foo', $req, $res);
        // self::assertInstanceOf(RequestExceptionInterface::class, $e);
        // self::assertNotInstanceOf(NetworkExceptionInterface::class, $e);
        self::assertSame('foo', $e->getMessage());
        self::assertSame($req, $e->getRequest());
        self::assertSame($res, $e->getResponse());
        // self::assertTrue($e->hasResponse());
    }

    /*
    public function testCreatesGenerateException()
    {
        $e = RequestException::create($this->factory->request('GET', '/'));
        self::assertSame('Error completing request', $e->getMessage());
        self::assertInstanceOf(RequestException::class, $e);
    }
    */

    /*
    public function testCreatesClientErrorResponseException()
    {
        $e = RequestException::create($this->factory->request('GET', '/'), $this->factory->response(400));
        self::assertStringContainsString(
            'GET /',
            $e->getMessage()
        );
        self::assertStringContainsString(
            '400 Bad Request',
            $e->getMessage()
        );
        self::assertInstanceOf(ClientException::class, $e);
    }
    */

    /*
    public function testCreatesServerErrorResponseException()
    {
        $e = RequestException::create($this->factory->request('GET', '/'), $this->factory->response(500));
        self::assertStringContainsString(
            'GET /',
            $e->getMessage()
        );
        self::assertStringContainsString(
            '500 Internal Server Error',
            $e->getMessage()
        );
        self::assertInstanceOf(ServerException::class, $e);
    }
    */

    /*
    public function testCreatesGenericErrorResponseException()
    {
        $e = RequestException::create($this->factory->request('GET', '/'), $this->factory->response(300));
        self::assertStringContainsString(
            'GET /',
            $e->getMessage()
        );
        self::assertStringContainsString(
            '300 ',
            $e->getMessage()
        );
        self::assertInstanceOf(RequestException::class, $e);
    }
    */

    /*
    public function testThrowsInvalidArgumentExceptionOnOutOfBoundsResponseCode()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status code must be an integer value between 1xx and 5xx.');

        throw RequestException::create($this->factory->request('GET', '/'), $this->factory->response(600));
    }
    */

    /*
    public function dataPrintableResponses()
    {
        return [
            ['You broke the test!'],
            ['<h1>zlomený zkouška</h1>'],
            ['{"tester": "Philépe Gonzalez"}'],
            ["<xml>\n\t<text>Your friendly test</text>\n</xml>"],
            ['document.body.write("here comes a test");'],
            ["body:before {\n\tcontent: 'test style';\n}"],
        ];
    }
    */

    /**
     * @dataProvider dataPrintableResponses
     */
    /*
    public function testCreatesExceptionWithPrintableBodySummary($content)
    {
        $response = $this->factory->response(
            500,
            [],
            $content
        );
        $e = RequestException::create($this->factory->request('GET', '/'), $response);
        self::assertStringContainsString(
            $content,
            $e->getMessage()
        );
        self::assertInstanceOf(RequestException::class, $e);
    }
    */

    /*
    public function testCreatesExceptionWithTruncatedSummary()
    {
        $content = \str_repeat('+', 121);
        $response = $this->factory->response(500, [], $content);
        $e = RequestException::create($this->factory->request('GET', '/'), $response);
        $expected = \str_repeat('+', 120) . ' (truncated...)';
        self::assertStringContainsString($expected, $e->getMessage());
    }
    */

    /*
    public function testExceptionMessageIgnoresEmptyBody()
    {
        $e = RequestException::create($this->factory->request('GET', '/'), $this->factory->response(500));
        self::assertStringEndsWith('response', $e->getMessage());
    }
    */

    /**
     * @return void
     */
    public function testHasStatusCodeAsExceptionCode()
    {
        $e = new RequestException(
            'foo',
            $this->factory->request('GET', '/'),
            $this->factory->response(442)
        );
        self::assertSame(442, $e->getCode());
    }

    public function testPrevious()
    {
        $ePrev = new Exception('foo');
        $req = $this->factory->request('GET', 'http://www.oo.com');
        // $e2 = RequestException::wrapException($r, $e);
        $e = new RequestException('foo 2', $req, null, $ePrev);
        // self::assertInstanceOf(RequestException::class, $e);
        self::assertSame($ePrev, $e->getPrevious());
    }

    /*
    public function testDoesNotWrapExistingRequestExceptions()
    {
        $r = $this->factory->request('GET', 'http://www.oo.com');
        $e = new RequestException('foo', $r);
        $e2 = RequestException::wrapException($r, $e);
        self::assertSame($e, $e2);
    }
    */

    /*
    public function testCanProvideHandlerContext()
    {
        $r = $this->factory->request('GET', 'http://www.oo.com');
        $e = new RequestException('foo', $r, null, null, ['bar' => 'baz']);
        self::assertSame(['bar' => 'baz'], $e->getHandlerContext());
    }
    */

    public function testCreateNoResponse()
    {
        $req = $this->factory->request('GET', 'http://example.com');
        $e = RequestException::create($req);
        self::assertInstanceOf($this->classes['RequestException'], $e);
        self::assertSame('Error completing request: `GET http://example.com`', $e->getMessage());
    }

    public function testObfuscateUrlWithUsername()
    {
        $req = $this->factory->request('GET', 'http://username@www.oo.com');
        $res = $this->factory->response(400);
        $e = RequestException::create($req, $res);
        self::assertInstanceOf($this->classes['BadResponseException'], $e);
        self::assertSame('Client error: `GET http://username@www.oo.com` resulted in a `400 Bad Request` response', $e->getMessage());
    }

    public function testObfuscateUrlWithUsernameAndPassword()
    {
        $req = $this->factory->request('GET', 'http://user:password@www.oo.com');
        $res = $this->factory->response(500);
        $e = RequestException::create($req, $res);
        self::assertInstanceOf($this->classes['BadResponseException'], $e);
        self::assertSame('Server error: `GET http://user:***@www.oo.com` resulted in a `500 Internal Server Error` response', $e->getMessage());
    }
}

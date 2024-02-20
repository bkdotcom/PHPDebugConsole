<?php

namespace bdk\Test\CurlHttpMessage\Middleware;

use bdk\CurlHttpMessage\Handler\Mock as MockHandler;
use bdk\CurlHttpMessage\Middleware\Status;
use bdk\Promise;
use bdk\Test\CurlHttpMessage\TestCase;

/**
 * @covers bdk\CurlHttpMessage\Middleware\Status
 */
class StatusTest extends TestCase
{
    public function testNoExceptionOnSuccess()
    {
        $middleware = new Status();
        $handler = new MockHandler([
            $this->factory->response(200, null, [], 'Hello World'),
        ]);
        $callable = $middleware($handler);
        $request = $this->factory->request('GET', 'http://foo.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        $promise = $callable($curlReqRes);
        self::assertTrue(Promise::isPending($promise));

        $response = $promise->wait();
        self::assertSame('Hello World', (string) $response->getBody());
    }

    public function testThrowsExceptionOnHttpClientError()
    {
        $middleware = new Status();
        $handler = new MockHandler([
            $this->factory->response(400, null, [], \str_repeat('a', 1000)),
        ]);
        $callable = $middleware($handler);
        $request = $this->factory->request('GET', 'http://foo.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        $promise = $callable($curlReqRes);
        self::assertTrue(Promise::isPending($promise));

        $this->expectException($this->classes['BadResponseException']);
        $this->expectExceptionMessage('Client error: `GET http://foo.com` resulted in a `400 Bad Request');
        $promise->wait();
    }

    /*
    public function testThrowsExceptionOnHttpClientErrorLongBody()
    {
        $m = Middleware::httpErrors(new BodySummarizer(200));
        $h = new MockHandler([$this->factory->response(404, [], str_repeat('b', 1000))]);
        $f = $m($h);
        $p = $f($this->factory->request('GET', 'http://foo.com'), ['http_errors' => true]);
        self::assertTrue(P\Is::pending($p));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(\sprintf("Client error: `GET http://foo.com` resulted in a `404 Not Found` response:\n%s (truncated...)", str_repeat('b', 200)));
        $p->wait();
    }
    */

    public function testThrowsExceptionOnHttpServerError()
    {
        $middleware = new Status();
        $handler = new MockHandler([
            $this->factory->response(500, null, [], 'Oh no!'),
        ]);
        $callable = $middleware($handler);
        $request = $this->factory->request('GET', 'http://foo.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        $promise = $callable($curlReqRes);
        self::assertTrue(Promise::isPending($promise));

        $this->expectException($this->classes['BadResponseException']);
        $this->expectExceptionMessage('GET http://foo.com` resulted in a `500 Internal Server Error');
        $promise->wait();
    }
}

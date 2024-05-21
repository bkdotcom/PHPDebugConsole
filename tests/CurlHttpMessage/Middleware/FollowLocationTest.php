<?php

namespace bdk\Test\CurlHttpMessage\Middleware;

/*
use bdk\CurlHttpMessage\Handler\CurlMulti;
use bdk\HttpMessage\Request;
*/

use bdk\CurlHttpMessage\Client;
use bdk\CurlHttpMessage\CurlReqRes;
use bdk\CurlHttpMessage\Exception\RequestException;
use bdk\CurlHttpMessage\Handler\Mock as MockHandler;
use bdk\CurlHttpMessage\Middleware\FollowLocation;
use bdk\Promise;
use bdk\Test\CurlHttpMessage\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @covers bdk\CurlHttpMessage\Middleware\FollowLocation
 */
class FollowLocationTest extends TestCase
{
    public function testIgnoresNonRedirects()
    {
        /*
        $response = $this->factory->response(200);
        $stack = new HandlerStack(new MockHandler([$response]));
        $stack->push(Middleware::redirect());
        $handler = $stack->resolve();
        $request = $this->factory->request('GET', 'http://example.com');
        $promise = $handler($request, []);
        $response = $promise->wait();
        self::assertSame(200, $response->getStatusCode());
        */

        $middleware = new FollowLocation();
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

    public function testIgnoresWhenNoLocation()
    {
        /*
        $response = $this->factory->response(304);
        $stack = new HandlerStack(new MockHandler([$response]));
        $stack->push(Middleware::redirect());
        $handler = $stack->resolve();
        $request = $this->factory->request('GET', 'http://example.com');
        $promise = $handler($request, []);
        $response = $promise->wait();
        self::assertSame(304, $response->getStatusCode());
        */
        $middleware = new FollowLocation();
        $handler = new MockHandler([
            $this->factory->response(302, null, [], 'Hello World'),
        ]);
        $callable = $middleware($handler);
        $request = $this->factory->request('GET', 'http://foo.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        $promise = $callable($curlReqRes);
        self::assertTrue(Promise::isPending($promise));

        $response = $promise->wait();
        self::assertSame('Hello World', (string) $response->getBody());
    }

    public function testRedirectsWithAbsoluteUri()
    {
        $middleware = new FollowLocation();
        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://test.com']),
            $this->factory->response(200),
        ]);
        $callable = $middleware($handler);

        $request = $this->factory->request('GET', 'http://example.com?a=b');
        $curlReqRes = $this->factory->curlReqRes($request);
        $promise = $callable($curlReqRes);
        $response = $promise->wait();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('http://test.com', (string) $handler->getLastRequest()->getUri());
    }

    public function testRedirectsWithRelativeUri()
    {
        $middleware = new FollowLocation();
        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => '/foo']),
            $this->factory->response(200),
        ]);
        $callable = $middleware($handler);

        $request = $this->factory->request('GET', 'http://example.com?a=b');
        $curlReqRes = $this->factory->curlReqRes($request);
        $promise = $callable($curlReqRes);
        $response = $promise->wait();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('http://example.com/foo', (string) $handler->getLastRequest()->getUri());
    }

    public function testLimitsToMaxRedirects()
    {
        $middleware = new FollowLocation();
        $handler = new MockHandler([
            $this->factory->response(301, '', ['Location' => 'http://test.com']),
            $this->factory->response(302, '', ['Location' => 'http://test.com']),
            $this->factory->response(303, '', ['Location' => 'http://test.com']),
            $this->factory->response(304, '', ['Location' => 'http://test.com']),
        ]);
        $callable = $middleware($handler);

        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request, array(
            'maxRedirect' => 3,
        ));
        $promise = $callable($curlReqRes);

        $this->expectException($this->classes['RequestException']);
        $this->expectExceptionMessage('Too many redirects (4)');
        $promise->wait();
    }

    public function testTooManyRedirectsExceptionHasResponse()
    {
        $middleware = new FollowLocation();
        $handler = new MockHandler([
            $this->factory->response(301, '', ['Location' => 'http://test.com/1']),
            $this->factory->response(302, '', ['Location' => 'http://test.com/2']),
        ]);
        $callable = $middleware($handler);

        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request, array(
            'maxRedirect' => 1,
        ));
        $promise = $callable($curlReqRes);

        try {
            $promise->wait();
            self::fail();
        } catch (RequestException $e) {
            self::assertSame(302, $e->getResponse()->getStatusCode());
        }
    }

    public function testEnsuresProtocolIsValid()
    {
        $middleware = new FollowLocation();
        $handler = new MockHandler([
            $this->factory->response(301, '', ['Location' => 'ftp://test.com']),
        ]);
        $callable = $middleware($handler);

        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request, array(
            'maxRedirect' => 1,
        ));
        $promise = $callable($curlReqRes);

        $this->expectException($this->classes['BadResponseException']);
        $this->expectExceptionMessage('Redirect URI,');
        $promise->wait();
    }

    /*
    public function testAddsRefererHeader()
    {
        $middleware = new FollowLocation();
        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://test.com']),
            $this->factory->response(200),
        ]);
        $callable = $middleware($handler);

        $request = $this->factory->request('GET', 'http://example.com?a=b');
        $curlReqRes = $this->factory->curlReqRes($request, array(
            // 'allow_redirects' => ['max' => 2, 'referer' => true]
        ));
        $promise = $callable($curlReqRes);

        $promise->wait();
        self::assertSame(
            'http://example.com?a=b',
            $handler->getLastRequest()->getHeaderLine('Referer')
        );
    }
    */

    /*
    public function testAddsRefererHeaderWithoutUserInfo()
    {
        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://test.com']),
            $this->factory->response(200),
        ]);
        $middleware = new FollowLocation();
        $callable = $middleware($handler);

        $request = $this->factory->request('GET', 'http://foo:bar@example.com?a=b');
        $curlReqRes = $this->factory->curlReqRes($request, array(
            // 'allow_redirects' => ['max' => 2, 'referer' => true]
            'allow_redirects' => ['max' => 2, 'referer' => true]
        ));
        $promise = $callable($curlReqRes);

        $promise->wait();
        self::assertSame(
            'http://example.com?a=b',
            $handler->getLastRequest()->getHeaderLine('Referer')
        );
    }
    */

    /*
    public function testAddsRedirectHistoryHeader()
    {
        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://example.com']),
            $this->factory->response(302, '', ['Location' => 'http://example.com/foo']),
            $this->factory->response(302, '', ['Location' => 'http://example.com/bar']),
            $this->factory->response(200),
        ]);
        $middleware = new FollowLocation();
        $callable = $middleware($handler);

        // $stack = new HandlerStack($handler);
        // $stack->push(Middleware::redirect());
        // $handler = $stack->resolve();
        $request = $this->factory->request('GET', 'http://example.com?a=b');
        $curlReqRes = $this->factory->curlReqRes($request, array(
            // 'allow_redirects' => ['max' => 2, 'referer' => true]
            'allow_redirects' => ['track_redirects' => true]
        ));
        $promise = $callable($curlReqRes);

        $response = $promise->wait();
        self::assertSame(
            [
                'http://example.com',
                'http://example.com/foo',
                'http://example.com/bar',
            ],
            $response->getHeader(RedirectMiddleware::HISTORY_HEADER)
        );
    }
    */

    /*
    public function testAddsRedirectStatusHistoryHeader()
    {
        $handler = new MockHandler([
            $this->factory->response(301, '', ['Location' => 'http://example.com']),
            $this->factory->response(302, '', ['Location' => 'http://example.com/foo']),
            $this->factory->response(301, '', ['Location' => 'http://example.com/bar']),
            $this->factory->response(302, '', ['Location' => 'http://example.com/baz']),
            $this->factory->response(200),
        ]);
        $middleware = new FollowLocation();
        $callable = $middleware($handler);

        // $stack = new HandlerStack($handler);
        // $stack->push(Middleware::redirect());
        // $handler = $stack->resolve();
        $request = $this->factory->request('GET', 'http://example.com?a=b');
        $curlReqRes = $this->factory->curlReqRes($request, array(
            // 'allow_redirects' => ['max' => 2, 'referer' => true]
            'allow_redirects' => ['track_redirects' => true]
        ));
        $promise = $callable($curlReqRes);

        $response = $promise->wait();
        self::assertSame(
            [
                '301',
                '302',
                '301',
                '302',
            ],
            $response->getHeader(RedirectMiddleware::STATUS_HISTORY_HEADER)
        );
    }
    */

    public function testDoesNotAddRefererWhenGoingFromHttpsToHttp()
    {
        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://test.com']),
            $this->factory->response(200),
        ]);
        $middleware = new FollowLocation();
        $callable = $middleware($handler);

        $request = $this->factory->request('GET', 'https://example.com?a=b');
        $curlReqRes = $this->factory->curlReqRes($request, array(
            // 'allow_redirects' => ['max' => 2, 'referer' => true]
            'allow_redirects' => ['max' => 2, 'referer' => true]
        ));
        $promise = $callable($curlReqRes);

        $promise->wait();
        self::assertFalse($handler->getLastRequest()->hasHeader('Referer'));
    }

    public function testInvokesOnRedirectCallabck()
    {
        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://test.com']),
            $this->factory->response(200),
        ]);
        $middleware = new FollowLocation();
        $callable = $middleware($handler);

        $request = $this->factory->request('GET', 'http://example.com?a=b');

        $called = false;
        $curlReqRes = $this->factory->curlReqRes($request, array(
            // 'allow_redirects' => ['max' => 2, 'referer' => true]
            'onRedirect' => static function ($request, $response, $uri) use (&$called) {
                self::assertSame(302, $response->getStatusCode());
                self::assertSame('GET', $request->getMethod());
                self::assertSame('http://test.com', (string) $uri);
                $called = true;
            },
        ));
        $promise = $callable($curlReqRes);

        $promise->wait();
        self::assertTrue($called);
    }

    /**
     * @testWith ["digest"]
     *           ["ntlm"]
     */
    public function testRemoveCurlAuthorizationOptionsOnRedirectCrossHost($auth)
    {
        // if (!defined('\CURLOPT_HTTPAUTH')) {
            // self::markTestSkipped('ext-curl is required for this test');
        // }

        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://test.com']),
            function (RequestInterface $request, $options) {
                // $options = $curlReqRes->getOptions();
                self::assertFalse(
                    isset($options['curl'][\CURLOPT_HTTPAUTH]),
                    'curl options still contain CURLOPT_HTTPAUTH entry'
                );
                self::assertFalse(
                    isset($options['curl'][\CURLOPT_USERPWD]),
                    'curl options still contain CURLOPT_USERPWD entry'
                );
                return $this->factory->response(200);
            },
        ]);
        $client = new Client(array(
            'handler' => $handler,
        ));
        $client->request('GET', 'http://example.com?a=b', [
            'curl' => [
                CURLOPT_USERPWD => 'testuser:testpass',
                CURLOPT_HTTPAUTH => $auth === 'digest'
                    ? CURLAUTH_DIGEST
                    : CURLAUTH_NTLM,
            ],
        ]);
    }

    /**
     * @testWith ["digest"]
     *           ["ntlm"]
     */
    public function testRemoveCurlAuthorizationOptionsOnRedirectCrossPort($auth)
    {
        // if (!defined('\CURLOPT_HTTPAUTH')) {
            // self::markTestSkipped('ext-curl is required for this test');
        // }

        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://example.com:81/']),
            function (RequestInterface $request, $options) {
                // $options = $curlReqRes->getOptions();
                self::assertFalse(
                    isset($options['curl'][\CURLOPT_HTTPAUTH]),
                    'curl options still contain CURLOPT_HTTPAUTH entry'
                );
                self::assertFalse(
                    isset($options['curl'][\CURLOPT_USERPWD]),
                    'curl options still contain CURLOPT_USERPWD entry'
                );
                return $this->factory->response(200);
            },
        ]);
        $client = new Client(array(
            'handler' => $handler,
        ));
        $client->request('GET', 'http://example.com?a=b', [
            'curl' => [
                CURLOPT_USERPWD => 'testuser:testpass',
                CURLOPT_HTTPAUTH => $auth === 'digest'
                    ? CURLAUTH_DIGEST
                    : CURLAUTH_NTLM,
            ],
        ]);
    }

    /**
     * @testWith ["digest"]
     *           ["ntlm"]
     */
    public function testRemoveCurlAuthorizationOptionsOnRedirectCrossScheme($auth)
    {
        // if (!defined('\CURLOPT_HTTPAUTH')) {
            // self::markTestSkipped('ext-curl is required for this test');
        // }

        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://example.com?a=b']),
            function (RequestInterface $request, $options) {
                // $options = $curlReqRes->getOptions();
                self::assertFalse(
                    isset($options['curl'][\CURLOPT_HTTPAUTH]),
                    'curl options still contain CURLOPT_HTTPAUTH entry'
                );
                self::assertFalse(
                    isset($options['curl'][\CURLOPT_USERPWD]),
                    'curl options still contain CURLOPT_USERPWD entry'
                );
                return $this->factory->response(200);
            },
        ]);
        $client = new Client(array(
            'handler' => $handler,
        ));
        $client->request('get', 'https://example.com?a=b', [
            'curl' => [
                CURLOPT_USERPWD => 'testuser:testpass',
                CURLOPT_HTTPAUTH => $auth === 'digest'
                    ? CURLAUTH_DIGEST
                    : CURLAUTH_NTLM,
            ],
        ]);
    }

    /**
     * @testWith ["digest"]
     *           ["ntlm"]
     */
    public function testRemoveCurlAuthorizationOptionsOnRedirectCrossSchemeSamePort($auth)
    {
        // if (!defined('\CURLOPT_HTTPAUTH')) {
            // self::markTestSkipped('ext-curl is required for this test');
        // }

        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://example.com:80?a=b']),
            function (RequestInterface $request, $options) {
                // $options = $curlReqRes->getOptions();
                self::assertFalse(
                    isset($options['curl'][\CURLOPT_HTTPAUTH]),
                    'curl options still contain CURLOPT_HTTPAUTH entry'
                );
                self::assertFalse(
                    isset($options['curl'][\CURLOPT_USERPWD]),
                    'curl options still contain CURLOPT_USERPWD entry'
                );
                return $this->factory->response(200);
            },
        ]);
        $client = new Client([
            'handler' => $handler,
        ]);
        $client->request('get', 'https://example.com?a=b', [
            'curl' => [
                CURLOPT_USERPWD => 'testuser:testpass',
                CURLOPT_HTTPAUTH => $auth === 'digest'
                    ? CURLAUTH_DIGEST
                    : CURLAUTH_NTLM,
            ],
        ]);
    }

    /**
     * @testWith ["digest"]
     *           ["ntlm"]
     */
    public function testNotRemoveCurlAuthorizationOptionsOnRedirect($auth)
    {
        // if (!defined('\CURLOPT_HTTPAUTH') || !defined('\CURLOPT_USERPWD')) {
            // self::markTestSkipped('ext-curl is required for this test');
        // }

        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://example.com/2']),
            function (RequestInterface $request, $options) {
                // $options = $curlReqRes->getOptions();
                self::assertTrue(
                    isset($options['curl'][CURLOPT_HTTPAUTH]),
                    'curl options does not contain expected CURLOPT_HTTPAUTH entry'
                );
                self::assertTrue(
                    isset($options['curl'][CURLOPT_USERPWD]),
                    'curl options does not contain expected CURLOPT_USERPWD entry'
                );
                return $this->factory->response(200);
            },
        ]);
        $client = new Client([
            'handler' => $handler,
        ]);
        $client->request('get', 'http://example.com?a=b', [
            'curl' => [
                CURLOPT_USERPWD => 'testuser:testpass',
                CURLOPT_HTTPAUTH => $auth === 'digest'
                    ? CURLAUTH_DIGEST
                    : CURLAUTH_NTLM,
            ],
        ]);
    }

    public static function providerCrossOriginRedirect()
    {
        return [
            ['http://example.com/123', 'http://example.com/', false],
            ['http://example.com/123', 'http://example.com:80/', false],
            ['http://example.com:80/123', 'http://example.com/', false],
            ['http://example.com:80/123', 'http://example.com:80/', false],
            ['http://example.com/123', 'https://example.com/', true],
            ['http://example.com/123', 'http://www.example.com/', true],
            ['http://example.com/123', 'http://example.com:81/', true],
            ['http://example.com:80/123', 'http://example.com:81/', true],
            ['https://example.com/123', 'https://example.com/', false],
            ['https://example.com/123', 'https://example.com:443/', false],
            ['https://example.com:443/123', 'https://example.com/', false],
            ['https://example.com:443/123', 'https://example.com:443/', false],
            ['https://example.com/123', 'http://example.com/', true],
            ['https://example.com/123', 'https://www.example.com/', true],
            ['https://example.com/123', 'https://example.com:444/', true],
            ['https://example.com:443/123', 'https://example.com:444/', true],
        ];
    }

    /**
     * @dataProvider providerCrossOriginRedirect
     */
    public function testHeadersTreatmentOnRedirect($originalUri, $targetUri, $isCrossOrigin)
    {
        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => $targetUri]),
            function (RequestInterface $request, $options) use ($isCrossOrigin) {
                self::assertSame(!$isCrossOrigin, $request->hasHeader('Authorization'));
                self::assertSame(!$isCrossOrigin, $request->hasHeader('Cookie'));
                return $this->factory->response(200);
            },
        ]);
        $client = new Client([
            'handler' => $handler,
        ]);
        $client->request('GET', $originalUri, [
            // 'curl' => [
                // CURLOPT_USERPWD => 'testuser:testpass',
            // ],
            'headers' => [
                'Cookie' => 'foo=bar',
                'Authorization' => 'Basic ' . \base64_encode('testuser:testpass'),
            ],
        ]);
    }

    /*
    public function testNotRemoveAuthorizationHeaderOnRedirect()
    {
        $handler = new MockHandler([
            $this->factory->response(302, '', ['Location' => 'http://example.com/2']),
            function (CurlReqRes $curlReqRes) {
                $request = $curlReqRes->getRequest();
                self::assertTrue($request->hasHeader('Authorization'));
                return $this->factory->response(200);
            },
        ]);
        $client = new Client([
            'handler' => $handler,
        ]);
        $client->get('http://example.com?a=b', [
            // 'curl' => [
                // CURLOPT_USERPWD => 'testuser:testpass',
            // ],
            'headers' => [
                'Cookie' => 'foo=bar',
                'Authorization' => 'Basic ' . base64_encode('testuser:testpass'),
            ],
        ]);
    }
    */

    /**
     * Verifies how RedirectMiddleware::modifyRequest() modifies the method and body
     * of a request issued when encountering a redirect response.
     *
     * @param string $expectedFollowRequestMethod
     *
     * @dataProvider providerModifyRequestFollowRequestMethodAndBody
     */
    public function testModifyRequestFollowRequestMethodAndBody(
        RequestInterface $request,
        $expectedFollowRequestMethod
    )
    {
        $handler = new MockHandler([
            // $this->factory->response(302, '', ['Location' => $targetUri]),
            function (RequestInterface $request) {
                return $this->factory->response(302, '', [
                    'Location' => $this->baseUrl . '/echo?redirect=1',
                ]);
            },
            function (RequestInterface $request) use ($expectedFollowRequestMethod) {
                self::assertSame($expectedFollowRequestMethod, $request->getMethod());
                self::assertEquals(0, $request->getBody()->getSize());
                self::assertFalse($request->hasHeader('Content-Type'));
                return $this->factory->response(200);
            },
        ]);
        $client = new Client([
            'handler' => $handler,
        ]);
        $client->handle($request);
    }

    /**
     * @return array
     */
    public static function providerModifyRequestFollowRequestMethodAndBody()
    {
        return [
            'DELETE' => [
                'request' => self::$factory->request('DELETE', 'http://example.com/'),
                'expectedFollowRequestMethod' => 'GET',
            ],
            'GET' => [
                'request' => self::$factory->request('GET', 'http://example.com/'),
                'expectedFollowRequestMethod' => 'GET',
            ],
            'HEAD' => [
                'request' => self::$factory->request('HEAD', 'http://example.com/'),
                'expectedFollowRequestMethod' => 'HEAD',
            ],
            'OPTIONS' => [
                'request' => self::$factory->request('OPTIONS', 'http://example.com/'),
                'expectedFollowRequestMethod' => 'OPTIONS',
            ],
            'PATCH' => [
                'request' => self::$factory->request('PATCH', 'http://example.com/'),
                'expectedFollowRequestMethod' => 'GET',
            ],
            'POST' => [
                'request' => self::$factory->request('POST', 'http://example.com/', [
                    'Content-Type' => 'application/json',
                ], array('foo' => 'bar')),
                'expectedFollowRequestMethod' => 'GET',
            ],
            'PUT' => [
                'request' => self::$factory->request('PUT', 'http://example.com/', [
                    'Content-Type' => 'application/json',
                ], array('foo' => 'bar')),
                'expectedFollowRequestMethod' => 'GET',
            ],
            'TRACE' => [
                'request' => self::$factory->request('TRACE', 'http://example.com/'),
                'expectedFollowRequestMethod' => 'GET',
            ],
        ];
    }
}

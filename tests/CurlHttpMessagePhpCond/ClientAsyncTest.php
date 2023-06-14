<?php

namespace bdk\Test\CurlHttpMessagePhpCond;

use bdk\CurlHttpMessage\ClientAsync;
use bdk\CurlHttpMessage\Handler\Mock as MockHandler;
use bdk\Promise;
use bdk\Test\CurlHttpMessage\TestCase;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \bdk\CurlHttpMessage\ClientAsync
 */
class ClientAsyncTest extends TestCase
{
    use ExpectExceptionTrait;

    protected $handled = array();

    public function testExtendsClient()
    {
        $client = new ClientAsync();
        self::assertInstanceOf($this->classes['Client'], $client);
    }

    /**
     * @dataProvider methodProvider
     */
    public function testHelperMethods($method, $uri, $headers = array(), $body = null)
    {
        $handler = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new ClientAsync(array(
            'handler' => $handler,
        ));

        $promise = \call_user_func(array($client, $method), $uri, $headers, $body);
        self::assertInstanceOf($this->classes['PromiseInterface'], $promise);
        $response = $promise->wait();
        self::assertInstanceOf($this->classes['ResponseInterface'], $response);
        $lastRequest = $handler->getLastRequest();
        self::assertSame(\strtoupper($method), $lastRequest->getMethod());
        self::assertSame($uri, (string) $lastRequest->getUri());
        if ($body) {
            self::assertSame(
                \json_encode(array('foo' => 'bar'), JSON_PRETTY_PRINT),
                (string) $lastRequest->getBody()
            );
        }
    }

    /**
     * @dataProvider methodProvider
     */
    public function testRequest($method, $uri, $headers = array(), $body = null)
    {
        $handler = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new ClientAsync(array(
            'handler' => $handler,
        ));

        $promise = $client->request(\strtoupper($method), $uri, array(
            'headers' => $headers,
            'body' => $body,
        ));
        self::assertInstanceOf($this->classes['PromiseInterface'], $promise);
        $response = $promise->wait();
        self::assertInstanceOf($this->classes['ResponseInterface'], $response);
        $lastRequest = $handler->getLastRequest();
        self::assertSame(\strtoupper($method), $lastRequest->getMethod());
        self::assertSame($uri, (string) $lastRequest->getUri());
        if ($body) {
            self::assertSame(
                \json_encode(array('foo' => 'bar'), JSON_PRETTY_PRINT),
                (string) $lastRequest->getBody()
            );
        }
    }

    /**
     * @dataProvider methodProvider
     */
    public function testHandle($method, $uri, $headers = array(), $body = null)
    {
        $handler = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new ClientAsync(array(
            'handler' => $handler,
        ));

        $request = $this->factory->request($method, $uri, $headers, $body);
        $promise = $client->handle($request);
        self::assertInstanceOf($this->classes['PromiseInterface'], $promise);
        $response = $promise->wait();
        self::assertInstanceOf($this->classes['ResponseInterface'], $response);
        $lastRequest = $handler->getLastRequest();
        self::assertSame(\strtoupper($method), $lastRequest->getMethod());
        self::assertSame($uri, (string) $lastRequest->getUri());
        if ($body) {
            self::assertSame(
                \json_encode(array('foo' => 'bar'), JSON_PRETTY_PRINT),
                (string) $lastRequest->getBody()
            );
        }
    }

    public static function methodProvider()
    {
        return [
            'DELETE' => [
                'method' => 'delete',
                'uri' => 'http://example.com/',
            ],
            'GET' => [
                'method' => 'get',
                'uri' => 'http://example.com/',
            ],
            'HEAD' => [
                'method' => 'head',
                'uri' => 'http://example.com/',
            ],
            'OPTIONS' => [
                'method' => 'options',
                'uri' => 'http://example.com/',
            ],
            'PATCH' => [
                'method' => 'patch',
                'uri' => 'http://example.com/',
            ],
            'POST' => [
                'method' => 'post',
                'uri' => 'http://example.com/',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => array('foo' => 'bar'),
            ],
            'PUT' => [
                'method' => 'put',
                'uri' => 'http://example.com/',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => array('foo' => 'bar'),
            ],
            'TRACE' => [
                'method' => 'trace',
                'uri' => 'http://example.com/',
            ],
        ];
    }

    public function testValidatesIterable()
    {
        $client = new ClientAsync();
        $this->expectException($this->classes['InvalidArgumentException']);
        $client->each('foo')->wait();
    }

    public function testValidatesEachElement()
    {
        $client = new ClientAsync();
        $this->expectException($this->classes['InvalidArgumentException']);
        $client->each(array(
            'foo',
        ))->wait();
    }

    public function testSendsAndRealizesFuture()
    {
        $client = $this->getClient();
        $called = false;
        $client->each(array(
            $this->factory->request('GET', 'http://example.com'),
        ), array(
            'fulfilled' => static function (ResponseInterface $response) use (&$called) {
                $called = true;
            },
        ))->wait();
        self::assertTrue($called);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testExecutesPendingWhenWaiting()
    {
        $r1 = new Promise(function () use (&$r1) {
            $r1->resolve($this->factory->response());
        });
        $r2 = new Promise(function () use (&$r2) {
            $r2->resolve($this->factory->response());
        });
        $r3 = new Promise(function () use (&$r3) {
            $r3->resolve($this->factory->response());
        });
        $client = $this->getClient(array($r1, $r2, $r3));
        $client->each(array(
            $this->factory->request('GET', 'http://example.com'),
            $this->factory->request('GET', 'http://example.com'),
            $this->factory->request('GET', 'http://example.com'),
        ))->wait();
    }

    public function testUsesRequestOptions()
    {
        $client = $this->getClient();
        $opts = array(
            'options' => [
                'headers' => ['x-foo' => 'bar'],
            ],
        );

        $client->each(array(
            $this->factory->request('GET', 'http://example.com'),
        ), $opts)->wait();
        self::assertCount(1, $this->handled);
        self::assertTrue($this->handled[0]->hasHeader('x-foo'));
    }

    public function testCanProvideCallablesThatReturnResponses()
    {
        /*
        $handled = array();
        $handler = new MockHandler([
            function (RequestInterface $request) use (&$handled) {
                $handled[] = $request;
                return $this->factory->response();
            },
        ]);
        $client = new ClientAsync(array(
            'handler' => $handler,
        ));
        */
        // $optHistory = [];
        $client = $this->getClient();
        $callable = static function (array $opts) use ($client) {
            // $optHistory = $opts;
            return $client->request('GET', 'http://example.com', $opts);
        };
        $opts = array(
            'options' => [
                'headers' => ['x-foo' => 'bar'],
            ],
        );
        $client->each(array(
            $callable,
        ), $opts);
        self::assertCount(1, $this->handled);
        self::assertTrue($this->handled[0]->hasHeader('x-foo'));
    }

    public function testBatchesResults()
    {
        $requests = array(
            $this->factory->request('GET', 'http://foo.com/200'),
            $this->factory->request('GET', 'http://foo.com/201'),
            $this->factory->request('GET', 'http://foo.com/202'),
            $this->factory->request('GET', 'http://foo.com/404'),
        );
        $client = $this->getClient(4);
        $results = $client->batch($requests);
        self::assertCount(4, $results);
        self::assertSame([0, 1, 2, 3], \array_keys($results));
        self::assertSame(200, $results[0]->getStatusCode());
        self::assertSame(201, $results[1]->getStatusCode());
        self::assertSame(202, $results[2]->getStatusCode());
        self::assertInstanceOf($this->classes['BadResponseException'], $results[3]);
    }

    public function testBatchesResultsWithCallbacks()
    {
        $requests = array(
            $this->factory->request('GET', 'http://foo.com/200'),
            $this->factory->request('GET', 'http://foo.com/201'),
        );
        $client = $this->getClient(2);
        $callbackResponses = array();
        $results = $client->batch($requests, array(
            'fulfilled' => static function (ResponseInterface $response) use (&$callbackResponses) {
                $callbackResponses[] = $response;
            },
        ));
        self::assertCount(2, $results);
        self::assertCount(2, $callbackResponses);
    }

    public function testUsesYieldedKeyInFulfilledCallback()
    {
        $r1 = new Promise(function () use (&$r1) {
            $r1->resolve($this->factory->response());
        });
        $r2 = new Promise(function () use (&$r2) {
            $r2->resolve($this->factory->response());
        });
        $r3 = new Promise(function () use (&$r3) {
            $r3->resolve($this->factory->response());
        });
        $client = $this->getClient(array($r1, $r2, $r3));
        $keys = [];
        $requests = [
            'request_1' => $this->factory->request('GET', 'http://example.com'),
            'request_2' => $this->factory->request('GET', 'http://example.com'),
            'request_3' => $this->factory->request('GET', 'http://example.com'),
        ];
        $client->each($requests, array(
            'concurrency' => 2,
            'fulfilled' => static function (ResponseInterface $res, $index) use (&$keys) {
                $keys[] = $index;
            },
        ))->wait();
        self::assertCount(3, $keys);
        self::assertSame($keys, \array_keys($requests));
    }

    private function getClient($responseQueue = 1)
    {
        $queue = $responseQueue;
        if (\is_int($queue)) {
            $total = $queue;
            $queue = array();
            for ($i = 0; $i < $total; $i++) {
                $queue[] = function (RequestInterface $request) {
                    $status = \substr($request->getUri()->getPath(), 1);
                    if (\is_numeric($status) === false) {
                        $status = 200;
                    }
                    $this->handled[] = $request;
                    return $this->factory->response($status);
                };
            }
        }
        $this->handled = array();
        return new ClientAsync(array(
            'handler' => new MockHandler($queue),
        ));
    }
}

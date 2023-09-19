<?php

namespace bdk\Test\CurlHttpMessage\Handler;

use bdk\CurlHttpMessage\Handler\Mock as MockHandler;
use bdk\Test\CurlHttpMessage\TestCase;
use Exception;
use Psr\Http\Message\RequestInterface;

/**
 * @covers \bdk\CurlHttpMessage\Handler\Mock
 */
class MockTest extends TestCase
{
    public function testReturnsMockResponse()
    {
        $res = $this->factory->response();
        $mock = new MockHandler([$res]);
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        self::assertSame($res, $mock($curlReqRes)->wait());
    }

    public function testDelay()
    {
        $res = $this->factory->response();
        $mock = new MockHandler([$res]);
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        $curlReqRes->setOption('delay', 500);

        $tsStart = \microtime(true);
        self::assertSame($res, $mock($curlReqRes)->wait());
        $tsEnd = \microtime(true);
        $ellapsed = ($tsEnd - $tsStart) * 1000;
        self::assertGreaterThan(500, $ellapsed);
        self::assertLessThan(550, $ellapsed);
    }

    public function testIsCountable()
    {
        $res = $this->factory->response();
        $mock = new MockHandler([$res, $res]);
        self::assertCount(2, $mock);
    }

    public function testEmptyHandlerIsCountable()
    {
        self::assertCount(0, new MockHandler());
    }

    public function testEnsuresEachAppendOnCreationIsValid()
    {
        $this->expectException($this->classes['InvalidArgumentException']);
        new MockHandler(['a']);
    }

    public function testEnsuresEachAppendIsValid()
    {
        $mock = new MockHandler();
        $this->expectException($this->classes['InvalidArgumentException']);
        $mock->append(['a']);
    }

    public function testCanQueueExceptions()
    {
        $e = new Exception('a');
        $mock = new MockHandler([$e]);
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        try {
            $mock($curlReqRes)->wait();
            self::fail();
        } catch (\Exception $e2) {
            self::assertSame($e, $e2);
        }
    }

    public function testCanGetLastRequestAndOptions()
    {
        $res = $this->factory->response();
        $mock = new MockHandler([$res]);
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        $curlReqRes->setOption('foo', 'bar');
        $mock($curlReqRes);
        self::assertSame($request, $mock->getLastRequest());
        self::assertSame('bar', $mock->getLastOptions()['foo']);
    }

    /*
    public function testSinkFilename()
    {
        $filename = \sys_get_temp_dir() . '/mock_test_' . \uniqid();
        $res = $this->factory->response(200, [], 'TEST CONTENT');
        $mock = new MockHandler([$res]);
        $request = $this->factory->request('GET', '/');
        $p = $mock($request, ['sink' => $filename]);
        $p->wait();

        self::assertFileExists($filename);
        self::assertStringEqualsFile($filename, 'TEST CONTENT');

        \unlink($filename);
    }
    */

    /*
    public function testSinkResource()
    {
        $file = \tmpfile();
        $meta = \stream_get_meta_data($file);
        $res = $this->factory->response(200, [], 'TEST CONTENT');
        $mock = new MockHandler([$res]);
        $request = $this->factory->request('GET', '/');
        $p = $mock($request, ['sink' => $file]);
        $p->wait();

        self::assertFileExists($meta['uri']);
        self::assertStringEqualsFile($meta['uri'], 'TEST CONTENT');
    }
    */

    /*
    public function testSinkStream()
    {
        $stream = new Stream(\tmpfile());
        $res = $this->factory->response(200, '', [], 'TEST CONTENT');
        $mock = new MockHandler([$res]);
        $request = $this->factory->request('GET', '/');
        $p = $mock($request, ['sink' => $stream]);
        $p->wait();

        self::assertFileExists($stream->getMetadata('uri'));
        self::assertStringEqualsFile($stream->getMetadata('uri'), 'TEST CONTENT');
    }
    */

    public function testCanEnqueueCallables()
    {
        $response = $this->factory->response();
        $mock = new MockHandler([
            static function (RequestInterface $request, $options) use ($response) {
                return $response;
            },
        ]);
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        self::assertSame($response, $mock($curlReqRes)->wait());
    }

    /*
    public function testEnsuresOnHeadersIsCallable()
    {
        $res = $this->factory->response();
        $mock = new MockHandler([$res]);
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);

        $this->expectException($this->classes['InvalidArgumentException']);
        $mock($curlReqRes, ['on_headers' => 'error!']);
    }
    */

    /*
    public function testRejectsPromiseWhenOnHeadersFails()
    {
        $res = $this->factory->response();
        $mock = new MockHandler([$res]);
        $request = $this->factory->request('GET', 'http://example.com');
        $promise = $mock($request, [
            'on_headers' => static function () {
                throw new Exception('test');
            }
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('An error was encountered during the on_headers event');
        $promise->wait();
    }
    */

    public function testInvokesOnFulfilled()
    {
        $res = $this->factory->response();
        $mock = new MockHandler(array(
            $res,
        ), static function ($v) use (&$c) {
            $c = $v;
        });
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        $mock($curlReqRes)->wait();
        self::assertSame($res, $c);
    }

    public function testInvokesOnRejected()
    {
        $e = new Exception('a');
        $c = null;
        $mock = new MockHandler([$e], null, static function ($v) use (&$c) {
            $c = $v;
        });
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        $mock($curlReqRes)->wait(false);
        self::assertSame($e, $c);
    }

    public function testThrowsWhenNoMoreResponses()
    {
        $mock = new MockHandler();
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);

        $this->expectException($this->classes['OutOfBoundsException']);
        $mock($curlReqRes);
    }

    /*
    public function testCanCreateWithDefaultMiddleware()
    {
        $res = $this->factory->response(500);
        $mock = MockHandler::createWithMiddleware([$res]);
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);

        $this->expectException(BadResponseException::class);
        $mock($request, ['http_errors' => true])->wait();
    }
    */

    /*
    public function testInvokesOnStatsFunctionForResponse()
    {
        $res = $this->factory->response();
        $mock = new MockHandler([$res]);
        $request = $this->factory->request('GET', 'http://example.com');
        // @var TransferStats|null $stats
        $stats = null;
        $onStats = static function (TransferStats $s) use (&$stats) {
            $stats = $s;
        };
        $p = $mock($request, ['on_stats' => $onStats]);
        $p->wait();
        self::assertSame($res, $stats->getResponse());
        self::assertSame($request, $stats->getRequest());
    }
    */

    /*
    public function testInvokesOnStatsFunctionForError()
    {
        $e = new Exception('a');
        $c = null;
        $mock = new MockHandler([$e], null, static function ($v) use (&$c) {
            $c = $v;
        });
        $request = $this->factory->request('GET', 'http://example.com');

        // @var TransferStats|null $stats
        $stats = null;
        $onStats = static function (TransferStats $s) use (&$stats) {
            $stats = $s;
        };
        $mock($request, ['on_stats' => $onStats])->wait(false);
        self::assertSame($e, $stats->getHandlerErrorData());
        self::assertNull($stats->getResponse());
        self::assertSame($request, $stats->getRequest());
    }
    */

    /*
    public function testTransferTime()
    {
        $e = new Exception('a');
        $c = null;
        $mock = new MockHandler([$e], null, static function ($v) use (&$c) {
            $c = $v;
        });
        $request = $this->factory->request('GET', 'http://example.com');
        $stats = null;
        $onStats = static function (TransferStats $s) use (&$stats) {
            $stats = $s;
        };
        $mock($request, ['on_stats' => $onStats, 'transfer_time' => 0.4])->wait(false);
        self::assertEquals(0.4, $stats->getTransferTime());
    }
    */

    public function testResetQueue()
    {
        $mock = new MockHandler([
            $this->factory->response(200),
            $this->factory->response(204),
        ]);
        self::assertCount(2, $mock);

        $mock->reset();
        self::assertEmpty($mock);

        $mock->append($this->factory->response(500));
        self::assertCount(1, $mock);
    }
}

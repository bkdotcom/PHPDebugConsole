<?php

namespace bdk\Test\CurlHttpMessage\Handler;

use bdk\CurlHttpMessage\Handler\CurlMulti;
use bdk\Promise;
use bdk\Test\CurlHttpMessage\TestCase;

/**
 * @covers bdk\CurlHttpMessage\Handler\CurlMulti
 */
class CurlMultiTest extends TestCase
{
    public function testCanAddCustomCurlOptions()
    {
        if (PHP_VERSION_ID < 50500) {
            self::markTestSkipped('CURLMOPT_MAXCONNECTS is php >= 5.5');
        }
        // we overload curl_multi_setopt in test bootstrap to save option to GLOBALS
        $curlMulti = new CurlMulti([
            // CURLMOPT_MAXCONNECTS = curl 7.16.3 (php 5.5+)
            'curlMulti' => [CURLMOPT_MAXCONNECTS => 5],
        ]);
        $request = $this->factory->request('GET', $this->baseUrl . '/echo');
        $curlReqRes = $this->factory->curlReqRes($request);
        $curlMulti($curlReqRes);
        self::assertEquals(5, $GLOBALS['curlMultiOptions'][CURLMOPT_MAXCONNECTS]);
    }

    public function testSendsRequest()
    {
        $curlMulti = new CurlMulti();
        $request = $this->factory->request('GET', $this->baseUrl . '/echo');
        $curlReqRes = $this->factory->curlReqRes($request);
        $response = $curlMulti($curlReqRes)->wait();
        self::assertSame(200, $response->getStatusCode());
    }

    public function testProcessMultiple()
    {
        $curlMulti = new CurlMulti(array(
            'maxIdleHandles' => 1,
            'maxConcurrent' => 1,
        ));
        $promises = array();
        for ($i = 0; $i < 3; $i++) {
            $request = $this->factory->request('GET', $this->baseUrl . '/echo');
            $curlReqRes = $this->factory->curlReqRes($request);
            $promise = $curlMulti($curlReqRes);
            $promises[] = $promise;
        }

        $curlMulti->process();

        foreach ($promises as $promise) {
            self::assertTrue(Promise::isFulfilled($promise));
        }
    }

    public function testCreatesExceptions()
    {
        $curlMulti = new CurlMulti();
        $request = $this->factory->request('GET', 'http://localhost:123');
        $curlReqRes = $this->factory->curlReqRes($request);
        $promise = $curlMulti($curlReqRes);
        $this->expectException($this->classes['NetworkException']);
        $this->expectExceptionMessage('cURL error');
        $promise->wait();
    }

    public function testCanSetSelectTimeout()
    {
        $curlMulti = new CurlMulti(array(
            'selectTimeout' => 2,
        ));
        self::assertSame(2, self::propGet($curlMulti, 'options')['selectTimeout']);
    }

    public function testCanCancel()
    {
        $curlMulti = new CurlMulti(array(
            'maxIdleHandles' => 2,
        ));
        $promises = array();
        for ($i = 0; $i < 5; $i++) {
            $request = $this->factory->request('GET', $this->baseUrl . '/echo');
            $curlReqRes = $this->factory->curlReqRes($request);
            $promise = $curlMulti($curlReqRes);
            $promise->cancel();
            $promises[] = $promise;
        }

        foreach ($promises as $promise) {
            self::assertTrue(Promise::isRejected($promise));
        }
    }

    public function testCancelAssignedHandle()
    {
        $curlMulti = new CurlMulti(array(
            'maxIdleHandles' => 2,
        ));
        $promises = array();
        for ($i = 0; $i < 5; $i++) {
            $request = $this->factory->request('GET', $this->baseUrl . '/echo');
            $curlReqRes = $this->factory->curlReqRes($request);
            $promise = $curlMulti($curlReqRes);
            $promises[] = $promise;
        }
        $i = 0;
        $promises[0]->then(
            function () use ($promises, $curlMulti, &$i) {
                $curlMulti->process();
                foreach ($promises as $i => $promise) {
                    if ($i === 0) {
                        self::assertTrue(Promise::isFulfilled($promise));
                        continue;
                    }
                    $promise->cancel();
                    self::assertTrue(Promise::isRejected($promise));
                }
            }
        );
        $promises[0]->wait();
        self::assertSame(4, $i);
    }

    public function testCannotCancelFinished()
    {
        $curlMulti = new CurlMulti();
        $request = $this->factory->request('GET', $this->baseUrl . '/echo');
        $curlReqRes = $this->factory->curlReqRes($request);
        $promise = $curlMulti($curlReqRes);
        $promise->wait();
        $promise->cancel();
        self::assertTrue(Promise::isFulfilled($promise));
    }

    public function testDelaysConcurrently()
    {
        $curlMulti = new CurlMulti();
        $tsStart = \microtime(true);
        $request = $this->factory->request('GET', $this->baseUrl . '/echo');
        $curlReqRes = $this->factory->curlReqRes($request);
        $curlReqRes->setOption('delay', 100);
        $promise = $curlMulti($curlReqRes);
        $promise->wait();
        self::assertGreaterThanOrEqual($tsStart + 100 / 1000, \microtime(true));
    }

    public function testIdleHandles()
    {
        $curlMulti = new CurlMulti(array(
            'maxConcurrent' => 2,
            'maxIdleHandles' => 1,
        ));
        $fulfilled = array();
        $promises = array();
        for ($i = 0; $i < 5; $i++) {
            $request = $this->factory->request('GET', $this->baseUrl . '/echo');
            $curlReqRes = $this->factory->curlReqRes($request);
            $promise = $curlMulti($curlReqRes)->then(static function () use ($i, &$fulfilled) {
                $fulfilled[] = $i;
            });
            $promises[] = $promise;
        }
        $promises[0]->wait();
        self::assertSame(array(0, 1, 2, 3, 4), $fulfilled);
    }

    /*
    public function testUsesTimeoutEnvironmentVariables()
    {
        unset($_SERVER['CURL_SELECT_TIMEOUT']);
        \putenv('CURL_SELECT_TIMEOUT=');

        try {
            $a = new CurlMultiHandler();
            // Default if no options are given and no environment variable is set
            self::assertEquals(1, Helpers::readObjectAttribute($a, 'selectTimeout'));

            \putenv("CURL_SELECT_TIMEOUT=3");
            $a = new CurlMultiHandler();
            // Handler reads from the environment if no options are given
            self::assertEquals(3, Helpers::readObjectAttribute($a, 'selectTimeout'));
        } finally {
            \putenv('CURL_SELECT_TIMEOUT=');
        }
    }
    */

    /*
    public function testThrowsWhenAccessingInvalidProperty()
    {
        $curlMulti = new CurlMulti();

        $this->expectException(\BadMethodCallException::class);
        $curlMulti->foo;
    }
    */
}

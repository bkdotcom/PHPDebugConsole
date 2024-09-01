<?php

namespace bdk\Test\CurlHttpMessage;

use bdk\CurlHttpMessage\Exception\RequestException;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\Promise;
use bdk\Test\CurlHttpMessage\TestCase;

/**
 * @covers \bdk\CurlHttpMessage\CurlReqRes
 */
class CurlReqResTest extends TestCase
{
    use AssertionTrait;

    public function testConstruct()
    {
        $request = $this->factory->request('GET', 'http://example.com');
        $curlReqRes = $this->factory->curlReqRes($request);
        self::assertSame($request, $curlReqRes->getRequest());
        self::assertInstanceOf($this->classes['ResponseInterface'], $curlReqRes->getResponse());
    }

    public function testSetRequest()
    {
        $request = $this->factory->request('GET', 'http://example.com');
        $request2 = $this->factory->request('POST', '/foo');
        $curlReqRes = $this->factory->curlReqRes($request);

        $response1 = $curlReqRes->getResponse();
        self::assertInstanceOf($this->classes['ResponseInterface'], $response1);

        $curlReqRes->setRequest($request2);
        self::assertSame($request2, $curlReqRes->getRequest());

        $response2 = $curlReqRes->getResponse();
        self::assertInstanceOf($this->classes['ResponseInterface'], $response2);
        self::assertNotSame($response1, $response2);
    }

    public function testSetRequestUpdatesCurlOptions()
    {
        $request = $this->factory->request('GET', 'http://example.com');
        $request2 = $this->factory->request('POST', '/foo');
        $curlReqRes = $this->factory->curlReqRes($request);
        $curlReqRes->setCurlHandle(\curl_init());
        $curlReqRes->setRequest($request2);
        self::assertSame('/foo', $curlReqRes->getOption(array('curl', CURLOPT_URL)));
    }

    public function testSetPromise()
    {
        $request = $this->factory->request('GET', $this->baseUrl);
        $curlReqRes = $this->factory->curlReqRes($request);
        $promise = new Promise();
        $curlReqRes->setPromise($promise);
        self::assertSame($promise, $curlReqRes->getPromise());
    }

    /*
    public function testExecThrowsException()
    {
        $request = $this->factory->request();
        $curlReqRes = new CurlReqRes($request);
        $this->expectException(\RuntimeException::class);
        $curlReqRes->exec();
    }
    */

    public function testExec()
    {
        $request = $this->factory->request('GET', $this->baseUrl . '/echo');
        $curlReqRes = $this->factory->curlReqRes($request);
        $response = $curlReqRes->exec();
        self::assertInstanceOf($this->classes['ResponseInterface'], $response);
    }

    public function testError()
    {
        $request = $this->factory->request('GET', 'bogus://127.0.0.1/');
        $curlReqRes = $this->factory->curlReqRes($request);
        try {
            $curlReqRes->exec();
            $this->fail('RequestException not thrown');
        } catch (RequestException $e) {
            self::assertStringContainsString('cURL error 1: Protocol "bogus" not supported', $e->getMessage());
            self::assertStringContainsString(' for GET bogus://127.0.0.1/', $e->getMessage());
        }
    }

    public function testSetCurlHandle()
    {
        $request = $this->factory->request('GET', $this->baseUrl . '/echo');
        $curlReqRes = $this->factory->curlReqRes($request);
        $curl = \curl_init();
        $curlReqRes->setCurlHandle($curl);
        self::assertSame($curl, $curlReqRes->getCurlHandle());
    }

    public function testGetOption()
    {
        $request = $this->factory->request('GET', $this->baseUrl . '/echo');
        $curlReqRes = $this->factory->curlReqRes($request);
        $curlReqRes->setOption('foo.bar', 'baz');
        self::assertSame('baz', $curlReqRes->getOption('foo.bar'));
    }

    public function testSetOptionsDoesShallowMerge()
    {
        $request = $this->factory->request('GET', $this->baseUrl . '/echo');
        $curlReqRes = $this->factory->curlReqRes($request);
        $curlReqRes->setOptions(array(
            'foo' => array(
                'bar' => 'baz',
            ),
            'bip' => 'bop',
        ));
        $curlReqRes->setOptions(array(
            'foo' => array(
                'ding' => 'dong',
            ),
            'bip' => 'bam',
        ));
        self::assertNull($curlReqRes->getOption('delay'));
        self::assertFalse($curlReqRes->getOption('isAsynchronous'));
        self::assertSame(array(
            'ding' => 'dong',
        ), $curlReqRes->getOption('foo'));
        self::assertSame('bam', $curlReqRes->getOption('bip'));
    }
}

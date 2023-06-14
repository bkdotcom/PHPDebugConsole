<?php

namespace bdk\Test\CurlHttpMessage;

use bdk\CurlHttpMessage\Factory;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\Uri;
use bdk\Test\CurlHttpMessage\TestCase;

/**
 * @covers \bdk\CurlHttpMessage\CurlReqResOptions
 */
class CurlReqResOptionsTest extends TestCase
{
    /*
    public function testBadHeader()
    {
        $query = \http_build_query(array(
            'headers' => array(
                'Bad Header',
            ),
        ));
        $uri = (new Uri($this->baseUrl . '/echo'))->withQuery($query);
        $request = $this->factory->request('GET', $uri);
        $curlReqRes = $this->factory->curlReqRes($request);
        $curlReqRes->exec();
    }
    */

    /*
    public function testBadStatus()
    {
        $query = \http_build_query(array(
            'headers' => array(
                'HTTP/1.1',
            ),
        ));
        $uri = (new Uri($this->baseUrl . '/echo'))->withQuery($query);
        $request = $this->factory->request('GET', $uri);
        $curlReqRes = $this->factory->curlReqRes($request);
        $curlReqRes->exec();
    }
    */

    public function testDefaultReasonPhrase()
    {
        $query = \http_build_query(array(
            'headers' => array(
                'HTTP/1.1 418',
            ),
        ));
        $uri = (new Uri($this->baseUrl . '/echo'))->withQuery($query);
        $request = $this->factory->request('GET', $uri);
        $curlReqRes = $this->factory->curlReqRes($request);
        $response = $curlReqRes->exec();
        self::assertSame('I\'m a teapot', $response->getReasonPhrase());
    }


    public function testGet()
    {
        // $handler = new MockHandler([$this->factory->response()]);
        // $handler = new CurlHandler();
        $uri = (new Uri($this->baseUrl . '/echo#fragment'))->withUserInfo('user', 'pass');
        $request = $this->factory->request('GET', $uri);
        $curlReqRes = $this->factory->curlReqRes($request);
        // $middleware = new Curl();
        // $callable = $middleware($handler);
        // $response = $callable($curlReqRes)->wait();
        $response = $curlReqRes->exec();
        self::assertInstanceOf($this->classes['Response'], $response);

        $curlOptions = $curlReqRes->getOptions()['curl'];
        foreach ($curlOptions as $k => $v) {
            if ($v === 0) {
                $curlOptions[$k] = false;
            }
        }
        $subset = \array_replace($curlOptions, array(
            CURLOPT_URL => (string) $uri->withFragment(''),
            CURLOPT_FOLLOWLOCATION => false, // handled through middleware
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'Host: ' . $uri->getHost() . ':' . $uri->getPort(),
                'Accept:',
                'Expect:',
            ),
            CURLOPT_USERPWD => 'user:pass',
        ));
        self::assertSame($subset, $curlOptions);
        self::assertInstanceOf($this->classes['Closure'], $curlOptions[CURLOPT_HEADERFUNCTION]);
        self::assertInstanceOf($this->classes['Closure'], $curlOptions[CURLOPT_WRITEFUNCTION]);
    }

    public function testPost()
    {
        // $handler = new CurlHandler();
        $uri = new Uri($this->baseUrl . '/echo#fragment');
        $request = $this->factory->request('POST', $uri, array(
            // 'Content-Length' => 1,
            'Expect' => 'ignore',
        ), array(
            'foo' => 'bar baz',
            'stuff' => array('snap', 'crackle'),
        ));
        $request = $request->withProtocolVersion('1.0');
        $curlReqRes = $this->factory->curlReqRes($request);
        // $middleware = new Curl();
        // $callable = $middleware($handler);
        // $response = $callable($curlReqRes)->wait();

        $response = $curlReqRes->exec();
        self::assertInstanceOf($this->classes['Response'], $response);

        $curlOptions = $curlReqRes->getOptions()['curl'];
        foreach ($curlOptions as $k => $v) {
            if ($v === 0) {
                $curlOptions[$k] = false;
            }
        }
        $subset = \array_replace($curlOptions, array(
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_URL => (string) $uri->withFragment(''),
            CURLOPT_FOLLOWLOCATION => false, // handled through middleware
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
            CURLOPT_HTTPHEADER => array(
                'Host: ' . $uri->getHost() . ':' . $uri->getPort(),
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: 50',
                'Accept:',
                'Expect:',
            ),
            CURLOPT_POSTFIELDS => 'foo=bar+baz&stuff%5B0%5D=snap&stuff%5B1%5D=crackle',
            // CURLOPT_USERPWD => 'user:pass',
        ));
        self::assertSame($subset, $curlOptions);

        self::assertSame('application/json; charset="utf-8"', $response->getHeaderLine('Content-Type'));
        $responseData = \json_decode($response->getBody(), true);
        self::assertSame('foo=bar+baz&stuff%5B0%5D=snap&stuff%5B1%5D=crackle', $responseData['body']);
    }

    public function testHead()
    {
        if (PHP_VERSION_ID < 70400) {
            self::markTestSkipped('PHP\'s built in server (ver < 7.4) does not handle HEAD request');
        }

        // $handler = new CurlHandler();
        $uri = new Uri($this->baseUrl . '/echo');
        $request = $this->factory->request('HEAD', $uri);
        if (\defined('CURL_HTTP_VERSION_2_0')) {
            $request = $request->withProtocolVersion('2.0');
        }
        $curlReqRes = $this->factory->curlReqRes($request);
        // $middleware = new Curl();
        // $callable = $middleware($handler);
        // $response = $callable($curlReqRes)->wait();

        $response = $curlReqRes->exec();
        self::assertInstanceOf($this->classes['Response'], $response);

        $curlOptions = $curlReqRes->getOptions()['curl'];
        $subset = \array_replace($curlOptions, array(
            CURLOPT_NOBODY => true,
            CURLOPT_URL => (string) $uri->withFragment(''),
            CURLOPT_FOLLOWLOCATION => false, // handled through middleware
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTP_VERSION => $request->getProtocolVersion() === '2.0'
                ? CURL_HTTP_VERSION_2_0
                : CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'Host: ' . $uri->getHost() . ':' . $uri->getPort(),
                'Accept:',
                'Expect:',
            ),
        ));

        self::assertSame($subset, $curlOptions);
    }
}

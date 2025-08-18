<?php

namespace bdk\Test\CurlHttpMessage\Exception;

use bdk\CurlHttpMessage\Exception\RequestException;
use bdk\Test\CurlHttpMessage\AbstractTestCase;
use Exception;

/**
 * @covers \bdk\CurlHttpMessage\Exception\RequestException
 */
class RequestExceptionTest extends AbstractTestCase
{
    public function testHasRequestAndResponse()
    {
        $req = $this->factory->request('GET', '/');
        $res = $this->factory->response(200);
        $e = new RequestException('foo', $req, $res);
        self::assertSame('foo', $e->getMessage());
        self::assertSame($req, $e->getRequest());
        self::assertSame($res, $e->getResponse());
    }

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
        $e = new RequestException('foo 2', $req, null, $ePrev);
        self::assertSame($ePrev, $e->getPrevious());
    }

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

<?php

namespace bdk\Test\CurlHttpMessage\Exception;

use bdk\CurlHttpMessage\Exception\BadResponseException;
use bdk\Test\CurlHttpMessage\TestCase;
use Exception;

/**
 * @covers \bdk\CurlHttpMessage\Exception\BadResponseException
 */
class BadResponseExceptionTest extends TestCase
{
    public function testHasResponse()
    {
        $req = $this->factory->request('GET', '/');
        $res = $this->factory->response(404);
        $prev = new Exception();
        $e = new BadResponseException('foo', $req, $res, $prev);
        self::assertSame($req, $e->getRequest());
        self::assertSame($res, $e->getResponse());
        // self::assertTrue($e->hasResponse());
        self::assertSame('foo', $e->getMessage());
        self::assertSame($prev, $e->getPrevious());
        self::assertSame(404, $e->getCode());
    }
}

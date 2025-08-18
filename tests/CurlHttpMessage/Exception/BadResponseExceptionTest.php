<?php

namespace bdk\Test\CurlHttpMessage\Exception;

use bdk\CurlHttpMessage\Exception\BadResponseException;
use bdk\Test\CurlHttpMessage\AbstractTestCase;
use Exception;

/**
 * @covers \bdk\CurlHttpMessage\Exception\BadResponseException
 */
class BadResponseExceptionTest extends AbstractTestCase
{
    public function testHasResponse()
    {
        $req = $this->factory->request('GET', '/');
        $res = $this->factory->response(404);
        $prev = new Exception();
        $e = new BadResponseException('foo', $req, $res, $prev);
        self::assertSame($req, $e->getRequest());
        self::assertSame($res, $e->getResponse());
        self::assertSame('foo', $e->getMessage());
        self::assertSame($prev, $e->getPrevious());
        self::assertSame(404, $e->getCode());
    }
}

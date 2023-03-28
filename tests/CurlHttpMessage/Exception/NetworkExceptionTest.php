<?php

namespace bdk\Test\CurlHttpMessage\Exception;

use bdk\CurlHttpMessage\Exception\NetworkException;
use bdk\Test\CurlHttpMessage\TestCase;
use Exception;

/**
 * @covers \bdk\CurlHttpMessage\Exception\NetworkException
 */
class NetworkExceptionTest extends TestCase
{
    public function testHasRequest()
    {
        $req = $this->factory->request('GET', '/');
        $prev = new Exception();
        $e = new NetworkException('foo', $req, null, $prev);
        self::assertSame($req, $e->getRequest());
        self::assertSame('foo', $e->getMessage());
        self::assertSame($prev, $e->getPrevious());
    }
}

<?php

namespace bdk\Test\Promise;

use bdk\Promise\Exception\RejectionException;
use bdk\Test\Promise\Fixture\JsonSerializable;
use bdk\Test\Promise\Fixture\Stringable;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\Promise\Exception\RejectionException
 */
class RejectionExceptionTest extends TestCase
{
    public function testCanGetReasonFromException()
    {
        $thing = new Stringable('foo');
        $e = new RejectionException($thing);

        $this->assertSame($thing, $e->getReason());
        $this->assertSame('The promise was rejected with reason: foo', $e->getMessage());
    }

    public function testCanGetReasonMessageFromJson()
    {
        $data = array('foo' => 'bar');
        $reason = new JsonSerializable($data);
        $e = new RejectionException($reason);
        $this->assertStringContainsString(
            \json_encode($data, JSON_PRETTY_PRINT),
            $e->getMessage()
        );
    }
}

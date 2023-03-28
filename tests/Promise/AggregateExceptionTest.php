<?php

namespace bdk\Test\Promise;

use bdk\Promise\Exception\AggregateException;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\Promise\Exception\AggregateException
 * @covers bdk\Promise\Exception\RejectionException
 */
class AggregateExceptionTest extends TestCase
{
    public function testMessageAndReason()
    {
        $e = new AggregateException('foo', ['baz', 'bar']);
        $this->assertStringContainsString('foo', $e->getMessage());
        $this->assertSame(['baz', 'bar'], $e->getReason());
    }
}

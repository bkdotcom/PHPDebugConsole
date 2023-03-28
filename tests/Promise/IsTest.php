<?php

namespace bdk\Test\Promise;

use bdk\Promise;
use bdk\Promise\FulfilledPromise;
use bdk\Promise\Is;
use bdk\Promise\RejectedPromise;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\Promise\Is
 */
class IsTest extends TestCase
{
    public function testKnowsIfFulfilled()
    {
        $p = new FulfilledPromise(null);

        $this->assertTrue(Is::fulfilled($p));
        $this->assertFalse(Is::rejected($p));

        $this->assertTrue(Promise::isFulfilled($p));
        $this->assertFalse(Promise::isRejected($p));

        $this->assertTrue($p->isFulfilled());
        $this->assertFalse($p->isRejected());
    }

    public function testKnowsIfRejected()
    {
        $p = new RejectedPromise(null);

        $this->assertTrue(Is::rejected($p));
        $this->assertFalse(Is::fulfilled($p));

        $this->assertTrue(Promise::isRejected($p));
        $this->assertFalse(Promise::isFulfilled($p));

        $this->assertTrue($p->isRejected());
        $this->assertFalse($p->isFulfilled());
    }

    public function testKnowsIfSettled()
    {
        $p = new RejectedPromise(null);

        $this->assertTrue(Is::settled($p));
        $this->assertFalse(Is::pending($p));

        $this->assertTrue(Promise::isSettled($p));
        $this->assertFalse(Promise::isPending($p));

        $this->assertTrue($p->isSettled());
        $this->assertFalse($p->isPending());
    }

    public function testKnowsIfPending()
    {
        $p = new Promise();

        $this->assertFalse(Is::settled($p));
        $this->assertTrue(Is::pending($p));

        $this->assertFalse(Promise::isSettled($p));
        $this->assertTrue(Promise::isPending($p));

        $this->assertFalse($p->isSettled());
        $this->assertTrue($p->isPending());
    }
}

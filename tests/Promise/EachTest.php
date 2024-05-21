<?php

namespace bdk\Test\Promise;

use bdk\Promise;
use bdk\Promise\Is;
use bdk\Promise\Each;
use bdk\Promise\FulfilledPromise;
use bdk\Promise\RejectedPromise;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\Promise\Each
 */
class EachTest extends TestCase
{
    public function testCallsEachLimit()
    {
        $p = new Promise();
        $aggregate = Each::ofLimit($p, 2);

        $p->resolve('a');
        Promise::queue()->run();
        $this->assertTrue($aggregate->isFulfilled());
    }

    public function testEachLimitAllRejectsOnFailure()
    {
        $p = array(
            new FulfilledPromise('a'),
            new RejectedPromise('b'),
        );
        $aggregate = Each::ofLimitAll($p, 2);

        Promise::queue()->run();
        $this->assertTrue(Is::rejected($aggregate));

        $result = Promise::inspect($aggregate);

        $this->assertSame('b', $result['reason']);
    }
}

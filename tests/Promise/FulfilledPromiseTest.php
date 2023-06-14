<?php

namespace bdk\Test\Promise;

use bdk\Promise;
use bdk\Promise\FulfilledPromise;
use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;
use bdk\Test\PolyFill\ExpectExceptionTrait;

/**
 * @covers bdk\Promise\FulfilledPromise
 */
class FulfilledPromiseTest extends TestCase
{
    use ExpectExceptionTrait;

    protected $classes = array(
        'LogicException' => 'LogicException',
        'InvalidArgumentException' => 'InvalidArgumentException',
    );

    public function testReturnsValueWhenWaitedUpon()
    {
        $p = new FulfilledPromise('foo');
        $this->assertTrue($p->isFulfilled());
        $this->assertSame('foo', $p->wait(true));
    }

    public function testCannotCancel()
    {
        $p = new FulfilledPromise('foo');
        $this->assertTrue($p->isFulfilled());
        $p->cancel();
        $this->assertSame('foo', $p->wait());
    }

    /**
     * @exepctedExceptionMessage Cannot resolve a fulfilled promise
     */
    public function testCannotResolve()
    {
        $this->expectException($this->classes['LogicException']);

        $p = new FulfilledPromise('foo');
        $p->resolve('bar');
    }

    /**
     * @exepctedExceptionMessage Cannot reject a fulfilled promise
     */
    public function testCannotReject()
    {
        $this->expectException($this->classes['LogicException']);

        $p = new FulfilledPromise('foo');
        $p->reject('bar');
    }

    public function testCanResolveWithSameValue()
    {
        $p = new FulfilledPromise('foo');
        $p->resolve('foo');
        $this->assertSame('foo', $p->wait());
    }

    public function testCannotResolveWithPromise()
    {
        $this->expectException($this->classes['InvalidArgumentException']);

        new FulfilledPromise(new Promise());
    }

    public function testReturnsSelfWhenNoOnFulfilled()
    {
        $p = new FulfilledPromise('a');
        $this->assertSame($p, $p->then());
    }

    public function testAsynchronouslyInvokesOnFulfilled()
    {
        $p = new FulfilledPromise('a');
        $r = null;
        $p2 = $p->then(static function ($d) use (&$r) {
            $r = $d;
        });
        $this->assertNotSame($p, $p2);
        $this->assertNull($r);
        Promise::queue()->run();
        $this->assertSame('a', $r);
    }

    public function testReturnsNewRejectedWhenOnFulfilledFails()
    {
        $p = new FulfilledPromise('a');
        $p2 = $p->then(static function () {
            throw new Exception('b');
        });
        $this->assertNotSame($p, $p2);
        try {
            $p2->wait();
            $this->fail();
        } catch (Exception $e) {
            $this->assertSame('b', $e->getMessage());
        }
    }

    public function testOtherwiseIsSugarForRejections()
    {
        $c = null;
        $p = new FulfilledPromise('foo');
        $p->otherwise(static function ($v) use (&$c) {
            $c = $v;
        });
        $this->assertNull($c);
    }

    public function testDoesNotTryToFulfillTwiceDuringTrampoline()
    {
        $fp = new FulfilledPromise('a');
        $t1 = $fp->then(static function ($v) {
            return $v . ' b';
        });
        $t1->resolve('why!');
        $this->assertSame('why!', $t1->wait());
    }
}

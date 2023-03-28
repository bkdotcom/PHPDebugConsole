<?php

namespace bdk\Test\Promise;

use bdk\Promise;
use bdk\Promise\RejectedPromise;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\Promise\RejectedPromise
 */
class RejectedPromiseTest extends TestCase
{
    protected $classes = array(
        'InvalidArgumentException' => 'InvalidArgumentException',
        'LogicException' => 'LogicException',
    );

    public function testThrowsReasonWhenWaitedUpon()
    {
        $p = new RejectedPromise('foo');
        $this->assertTrue($p->isRejected());
        try {
            $p->wait(true);
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue($p->isRejected());
            $this->assertStringContainsString('foo', $e->getMessage());
        }
    }

    public function testCannotCancel()
    {
        $p = new RejectedPromise('foo');
        $p->cancel();
        $this->assertTrue($p->isRejected());
    }

    /**
     * @exepctedExceptionMessage Cannot resolve a rejected promise
     */
    public function testCannotResolve()
    {
        $this->expectException($this->classes['LogicException']);

        $p = new RejectedPromise('foo');
        $p->resolve('bar');
    }

    /**
     * @exepctedExceptionMessage Cannot reject a rejected promise
     */
    public function testCannotReject()
    {
        $this->expectException($this->classes['LogicException']);

        $p = new RejectedPromise('foo');
        $p->reject('bar');
    }

    public function testCanRejectWithSameValue()
    {
        $p = new RejectedPromise('foo');
        $p->reject('foo');
        $this->assertTrue($p->isRejected($p));
    }

    public function testThrowsSpecificException()
    {
        $e = new Exception();
        $p = new RejectedPromise($e);
        try {
            $p->wait(true);
            $this->fail();
        } catch (Exception $e2) {
            $this->assertSame($e, $e2);
        }
    }

    public function testCannotResolveWithPromise()
    {
        $this->expectException($this->classes['InvalidArgumentException']);

        new RejectedPromise(new Promise());
    }

    public function testReturnsSelfWhenNoOnReject()
    {
        $p = new RejectedPromise('a');
        $this->assertSame($p, $p->then());
    }

    public function testInvokesOnRejectedAsynchronously()
    {
        $p = new RejectedPromise('a');
        $r = null;
        $p->then(null, static function ($reason) use (&$r) {
            $r = $reason;
        });
        $this->assertNull($r);
        Promise::queue()->run();
        $this->assertSame('a', $r);
    }

    public function testReturnsNewRejectedWhenOnRejectedFails()
    {
        $p = new RejectedPromise('a');
        $p2 = $p->then(null, static function () {
            throw new Exception('b');
        });
        $this->assertNotSame($p, $p2);
        try {
            $p2->wait();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertSame('b', $e->getMessage());
        }
    }

    public function testWaitingIsNoOp()
    {
        $p = new RejectedPromise('a');
        $p->wait(false);
        $this->assertTrue($p->isRejected());
    }

    public function testOtherwiseIsSugarForRejections()
    {
        $p = new RejectedPromise('foo');
        $p->otherwise(static function ($v) use (&$c) {
            $c = $v;
        });
        Promise::queue()->run();
        $this->assertSame('foo', $c);
    }

    public function testCanResolveThenWithSuccess()
    {
        $actual = null;
        $p = new RejectedPromise('foo');
        $p->otherwise(static function ($v) {
            return $v . ' bar';
        })->then(static function ($v) use (&$actual) {
            $actual = $v;
        });
        Promise::queue()->run();
        $this->assertSame('foo bar', $actual);
    }

    public function testDoesNotTryToRejectTwiceDuringTrampoline()
    {
        $fp = new RejectedPromise('a');
        $t1 = $fp->then(null, static function ($v) {
            return $v . ' b';
        });
        $t1->resolve('why!');
        $this->assertSame('why!', $t1->wait());
    }
}

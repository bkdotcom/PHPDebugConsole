<?php

namespace bdk\Test\Promise;

use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Promise;
use bdk\Promise\Exception\CancellationException;
use bdk\Promise\Exception\RejectionException;
use bdk\Promise\RejectedPromise;
use bdk\Test\Promise\Fixture\ExtendsPromise;
use bdk\Test\Promise\Fixture\Thenable;
use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

/**
 * @covers bdk\Promise\AbstractPromise
 * @covers bdk\Promise
 */
class PromiseTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    protected $classes = array(
        'BadMethodCallException' => 'BadMethodCallException',
        'CancellationException' => 'bdk\\Promise\\Exception\\CancellationException',
        'FulfilledPromise' => 'bdk\\Promise\\FulfilledPromise',
        'LogicException' => 'LogicException',
        'RejectedPromise' => 'bdk\\Promise\\RejectedPromise',
        'RejectionException' => 'bdk\\Promise\\Exception\\RejectionException',
        'UnexpectedValueException' => 'UnexpectedValueException',
    );

    public function testUndefinedMethod()
    {
        $this->expectException($this->classes['BadMethodCallException']);
        $this->expectExceptionMessage('Undefined method: bdk\\Promise::bogus()');

        $p = new Promise();
        $p->bogus();
    }

    public function testCannotResolveNonPendingPromise()
    {
        $this->expectException($this->classes['LogicException']);
        $this->expectExceptionMessage('The promise is already fulfilled');

        $p = new Promise();
        $p->resolve('foo');
        $p->resolve('bar');
        // $this->assertSame('foo', $p->wait());
    }

    public function testCanResolveWithSameValue()
    {
        $p = new Promise();
        $p->resolve('foo');
        $p->resolve('foo');
        $this->assertSame('foo', $p->wait());
    }

    public function testCannotRejectNonPendingPromise()
    {
        $this->expectException($this->classes['LogicException']);
        $this->expectExceptionMessage('Cannot change a fulfilled promise to rejected');

        $p = new Promise();
        $p->resolve('foo');
        $p->reject('bar');
        $this->assertSame('foo', $p->wait());
    }

    public function testCanRejectWithSameValue()
    {
        $p = new Promise();
        $p->reject('foo');
        $p->reject('foo');
        $this->assertTrue($p->isRejected());
    }

    public function testCannotRejectResolveWithSameValue()
    {
        $this->expectException($this->classes['LogicException']);
        $this->expectExceptionMessage('Cannot change a fulfilled promise to rejected');

        $p = new Promise();
        $p->resolve('foo');
        $p->reject('foo');
    }

    public function testInvokesWaitFunction()
    {
        $p = new Promise(static function () use (&$p) {
            $p->resolve('10');
        });
        $this->assertSame('10', $p->wait());
    }

    public function testRejectsAndThrowsWhenWaitFailsToResolve()
    {
        $this->expectException($this->classes['RejectionException']);
        $this->expectExceptionMessage('The promise was rejected with reason: The wait callback did not resolve the promise');

        $p = new Promise(static function () {
        });
        $p->wait();
    }

    public function testThrowsWhenUnwrapIsRejectedWithNonException()
    {
        $this->expectException($this->classes['RejectionException']);
        $this->expectExceptionMessage('The promise was rejected with reason: foo');

        $p = new Promise(static function () use (&$p) {
            $p->reject('foo');
        });
        $p->wait();
    }

    public function testThrowsWhenUnwrapIsRejectedWithException()
    {
        $this->expectException($this->classes['UnexpectedValueException']);
        $this->expectExceptionMessage('foo');

        $e = new UnexpectedValueException('foo');
        $p = new Promise(static function () use (&$p, $e) {
            $p->reject($e);
        });
        $p->wait();
    }

    public function testDoesNotUnwrapExceptionsWhenDisabled()
    {
        $p = new Promise(static function () use (&$p) {
            $p->reject('foo');
        });
        $this->assertTrue($p->isPending());
        $p->wait(false);
        $this->assertTrue($p->isRejected());
    }

    public function testRejectsSelfWhenWaitThrows()
    {
        $e = new UnexpectedValueException('foo');
        $p = new Promise(static function () use ($e) {
            throw $e;
        });
        try {
            $p->wait();
            $this->fail();
        } catch (\UnexpectedValueException $e) {
            $this->assertTrue($p->isRejected());
        }
    }

    public function testWaitsOnNestedPromises()
    {
        $p = new Promise(static function () use (&$p) {
            $p->resolve('_');
        });
        $p2 = new Promise(static function () use (&$p2) {
            $p2->resolve('foo');
        });
        $p3 = $p->then(static function () use ($p2) {
            return $p2;
        });
        $this->assertSame('foo', $p3->wait());
    }

    public function testThrowsWhenWaitingOnPromiseWithNoWaitFunction()
    {
        $this->expectException($this->classes['RejectionException']);

        $p = new Promise();
        $p->wait();
    }

    public function testThrowsWaitExceptionAfterPromiseIsResolved()
    {
        $p = new Promise(static function () use (&$p) {
            $p->reject('Foo!');
            throw new Exception('Bar?');
        });

        try {
            $p->wait();
            $this->fail();
        } catch (Exception $e) {
            $this->assertSame('Bar?', $e->getMessage());
        }
    }

    public function testGetsActualWaitValueFromThen()
    {
        $p = new Promise(static function () use (&$p) {
            $p->reject('Foo!');
        });
        $p2 = $p->then(null, static function ($reason) {
            return new RejectedPromise([$reason]);
        });

        try {
            $p2->wait();
            $this->fail('Should have thrown');
        } catch (RejectionException $e) {
            $this->assertSame(['Foo!'], $e->getReason());
        }
    }

    public function testWaitBehaviorIsBasedOnLastPromiseInChain()
    {
        $p3 = new Promise(static function () use (&$p3) {
            $p3->resolve('Whoop');
        });
        $p2 = new Promise(static function () use (&$p2, $p3) {
            $p2->reject($p3);
        });
        $p = new Promise(static function () use (&$p, $p2) {
            $p->reject($p2);
        });
        $this->assertSame('Whoop', $p->wait());
    }

    public function testWaitsOnAPromiseChainEvenWhenNotUnwrapped()
    {
        $p2 = new Promise(static function () use (&$p2) {
            $p2->reject('Fail');
        });
        $p = new Promise(static function () use ($p2, &$p) {
            $p->resolve($p2);
        });
        $p->wait(false);
        $this->assertTrue($p2->isRejected());
    }

    public function testCannotCancelNonPending()
    {
        $p = new Promise();
        $p->resolve('foo');
        $p->cancel();
        $this->assertTrue($p->isFulfilled());
    }

    public function testCancelsPromiseWhenNoCancelFunction()
    {
        $this->expectException($this->classes['CancellationException']);

        $p = new Promise();
        $p->cancel();
        $this->assertTrue($p->isRejected());
        $p->wait();
    }

    public function testCancelsPromiseWithCancelFunction()
    {
        $called = false;
        $p = new Promise(null, static function () use (&$called) {
            $called = true;
        });
        $p->cancel();
        $this->assertTrue($p->isRejected());
        $this->assertTrue($called);
    }

    public function testCancelsUppermostPendingPromise()
    {
        $called = false;
        $p1 = new Promise(null, static function () use (&$called) {
            $called = true;
        });
        $p2 = $p1->then(static function () {});
        $p3 = $p2->then(static function () {});
        $p4 = $p3->then(static function () {});
        $p3->cancel();
        $this->assertTrue($p1->isRejected());
        $this->assertTrue($p2->isRejected());
        $this->assertTrue($p3->isRejected());
        $this->assertTrue($p4->isPending());
        $this->assertTrue($called);

        try {
            $p3->wait();
            $this->fail();
        } catch (CancellationException $e) {
            $this->assertStringContainsString('cancelled', $e->getMessage());
        }

        try {
            $p4->wait();
            $this->fail();
        } catch (CancellationException $e) {
            $this->assertStringContainsString('cancelled', $e->getMessage());
        }

        $this->assertTrue($p4->isRejected());
    }

    public function testCancelsChildPromises()
    {
        $called1 = false;
        $called2 = false;
        $called3 = false;
        $p1 = new Promise(null, static function () use (&$called1) {
            $called1 = true;
        });
        $p2 = new Promise(null, static function () use (&$called2) {
            $called2 = true;
        });
        $p3 = new Promise(null, static function () use (&$called3) {
            $called3 = true;
        });
        $p4 = $p2->then(static function () use ($p3) {
            return $p3;
        });
        $p5 = $p4->then(static function () {
            $this->fail();
        });
        $p4->cancel();
        $this->assertTrue($p1->isPending());
        $this->assertTrue($p2->isRejected());
        $this->assertTrue($p3->isPending());
        $this->assertTrue($p4->isRejected());
        $this->assertTrue($p5->isPending());
        $this->assertFalse($called1);
        $this->assertTrue($called2);
        $this->assertFalse($called3);
    }

    public function testRejectsPromiseWhenCancelFails()
    {
        $called = false;
        $p = new Promise(null, static function () use (&$called) {
            $called = true;
            throw new Exception('e');
        });
        $p->cancel();
        $this->assertTrue($p->isRejected($p));
        $this->assertTrue($called);
        try {
            $p->wait();
            $this->fail();
        } catch (Exception $e) {
            $this->assertSame('e', $e->getMessage());
        }
    }

    public function testCreatesPromiseWhenFulfilledAfterThen()
    {
        $p = new Promise();
        $carry = null;
        $p2 = $p->then(static function ($v) use (&$carry) {
            $carry = $v;
        });
        $this->assertNotSame($p, $p2);
        $p->resolve('foo');
        Promise::queue()->run();

        $this->assertSame('foo', $carry);
    }

    public function testCreatesPromiseWhenFulfilledBeforeThen()
    {
        $p = new Promise();
        $p->resolve('foo');
        $carry = null;
        $p2 = $p->then(static function ($v) use (&$carry) {
            $carry = $v;
        });
        $this->assertNotSame($p, $p2);
        $this->assertNull($carry);
        Promise::queue()->run();
        $this->assertSame('foo', $carry);
    }

    public function testCreatesPromiseWhenFulfilledWithNoCallback()
    {
        $p = new Promise();
        $p->resolve('foo');
        $p2 = $p->then();
        $this->assertNotSame($p, $p2);
        $this->assertInstanceOf($this->classes['FulfilledPromise'], $p2);
    }

    public function testCreatesPromiseWhenRejectedAfterThen()
    {
        $p = new Promise();
        $carry = null;
        $p2 = $p->then(null, static function ($v) use (&$carry) {
            $carry = $v;
        });
        $this->assertNotSame($p, $p2);
        $p->reject('foo');
        Promise::queue()->run();
        $this->assertSame('foo', $carry);
    }

    public function testCreatesPromiseWhenRejectedBeforeThen()
    {
        $p = new Promise();
        $p->reject('foo');
        $carry = null;
        $p2 = $p->then(null, static function ($v) use (&$carry) {
            $carry = $v;
        });
        $this->assertNotSame($p, $p2);
        $this->assertNull($carry);
        Promise::queue()->run();
        $this->assertSame('foo', $carry);
    }

    public function testCreatesPromiseWhenRejectedWithNoCallback()
    {
        $p = new Promise();
        $p->reject('foo');
        $p2 = $p->then();
        $this->assertNotSame($p, $p2);
        $this->assertInstanceOf($this->classes['RejectedPromise'], $p2);
    }

    public function testInvokesWaitFnsForThens()
    {
        $p = new Promise(static function () use (&$p) {
            $p->resolve('a');
        });
        $p2 = $p
            ->then(static function ($v) {
                return $v . '-1-';
            })
            ->then(static function ($v) {
                return $v . '2';
            });
        $this->assertSame('a-1-2', $p2->wait());
    }

    public function testStacksThenWaitFunctions()
    {
        $p1 = new Promise(static function () use (&$p1) {
            $p1->resolve('a');
        });
        $p2 = new Promise(static function () use (&$p2) {
            $p2->resolve('b');
        });
        $p3 = new Promise(static function () use (&$p3) {
            $p3->resolve('c');
        });
        $p4 = $p1
            ->then(static function () use ($p2) {
                return $p2;
            })
            ->then(static function () use ($p3) {
                return $p3;
            });
        $this->assertSame('c', $p4->wait());
    }

    public function testForwardsFulfilledDownChainBetweenGaps()
    {
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(static function ($v) use (&$r) {
                $r = $v;
                return $v . '2';
            })
            ->then(static function ($v) use (&$r2) {
                $r2 = $v;
            });
        $p->resolve('foo');
        Promise::queue()->run();
        $this->assertSame('foo', $r);
        $this->assertSame('foo2', $r2);
    }

    public function testForwardsRejectedPromisesDownChainBetweenGaps()
    {
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(null, static function ($v) use (&$r) {
                $r = $v;
                return $v . '2';
            })
            ->then(static function ($v) use (&$r2) {
                $r2 = $v;
            });
        $p->reject('foo');
        Promise::queue()->run();
        $this->assertSame('foo', $r);
        $this->assertSame('foo2', $r2);
    }

    public function testForwardsThrownPromisesDownChainBetweenGaps()
    {
        $e = new Exception();
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(null, static function ($v) use (&$r, $e) {
                $r = $v;
                throw $e;
            })
            ->then(
                null,
                static function ($v) use (&$r2) {
                    $r2 = $v;
                }
            );
        $p->reject('foo');
        Promise::queue()->run();
        $this->assertSame('foo', $r);
        $this->assertSame($e, $r2);
    }

    public function testForwardsReturnedRejectedPromisesDownChainBetweenGaps()
    {
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(null, static function ($v) use (&$r) {
                $r = $v;
                return new RejectedPromise('bar');
            })
            ->then(
                null,
                static function ($v) use (&$r2) {
                    $r2 = $v;
                }
            );
        $p->reject('foo');
        Promise::queue()->run();
        $this->assertSame('foo', $r);
        $this->assertSame('bar', $r2);
        try {
            $p->wait();
        } catch (RejectionException $e) {
            $this->assertSame('foo', $e->getReason());
        }
    }

    public function testForwardsHandlersToNextPromise()
    {
        $p = new Promise();
        $p2 = new Promise();
        $resolved = null;
        $p
            ->then(static function ($v) use ($p2) {
                return $p2;
            })
            ->then(static function ($value) use (&$resolved) {
                $resolved = $value;
            });
        $p->resolve('a');
        $p2->resolve('b');
        Promise::queue()->run();
        $this->assertSame('b', $resolved);
    }

    public function testRemovesReferenceFromChildWhenParentWaitedUpon()
    {
        $r = null;
        $p = new Promise(static function () use (&$p) {
            $p->resolve('a');
        });
        $p2 = new Promise(static function () use (&$p2) {
            $p2->resolve('b');
        });
        $pb = $p->then(
            static function ($v) use ($p2, &$r) {
                $r = $v;
                return $p2;
            }
        )
            ->then(static function ($v) {
                return $v . '.';
            });
        $this->assertSame('a', $p->wait());
        $this->assertSame('b', $p2->wait());
        $this->assertSame('b.', $pb->wait());
        $this->assertSame('a', $r);
    }

    public function testForwardsHandlersWhenFulfilledPromiseIsReturned()
    {
        $res = [];
        $p = new Promise();
        $p2 = new Promise();
        $p2->resolve('foo');
        $p2->then(static function ($v) use (&$res) {
            $res[] = 'A:' . $v;
        });
        // $res is A:foo
        $p
            ->then(static function () use ($p2, &$res) {
                $res[] = 'B';
                return $p2;
            })
            ->then(static function ($v) use (&$res) {
                $res[] = 'C:' . $v;
            });
        $p->resolve('a');
        $p->then(static function ($v) use (&$res) {
            $res[] = 'D:' . $v;
        });
        Promise::queue()->run();
        $this->assertSame(['A:foo', 'B', 'D:a', 'C:foo'], $res);
    }

    public function testForwardsHandlersWhenRejectedPromiseIsReturned()
    {
        $res = [];
        $p = new Promise();
        $p2 = new Promise();
        $p2->reject('foo');
        $p2->then(null, static function ($v) use (&$res) {
            $res[] = 'A:' . $v;
        });
        $p->then(null, static function () use ($p2, &$res) {
            $res[] = 'B';
            return $p2;
        })
            ->then(null, static function ($v) use (&$res) {
                $res[] = 'C:' . $v;
            });
        $p->reject('a');
        $p->then(null, static function ($v) use (&$res) {
            $res[] = 'D:' . $v;
        });
        Promise::queue()->run();
        $this->assertSame(['A:foo', 'B', 'D:a', 'C:foo'], $res);
    }

    public function testDoesNotForwardRejectedPromise()
    {
        $res = [];
        $p = new Promise();
        $p2 = new Promise();
        $p2->cancel();
        $p2->then(static function ($v) use (&$res) {
            $res[] = "B:$v";
            return $v;
        });
        $p->then(static function ($v) use ($p2, &$res) {
            $res[] = "B:$v";
            return $p2;
        })
            ->then(static function ($v) use (&$res) {
                $res[] = 'C:' . $v;
            });
        $p->resolve('a');
        $p->then(static function ($v) use (&$res) {
            $res[] = 'D:' . $v;
        });
        Promise::queue()->run();
        $this->assertSame(['B:a', 'D:a'], $res);
    }

    public function testRecursivelyForwardsWhenOnlyThenable()
    {
        $res = [];
        $p = new Promise();
        $p2 = new Thenable();
        $p2->resolve('foo');
        $p2->then(static function ($v) use (&$res) {
            $res[] = 'A:' . $v;
        });
        $p->then(static function () use ($p2, &$res) {
            $res[] = 'B';
            return $p2;
        })
            ->then(static function ($v) use (&$res) {
                $res[] = 'C:' . $v;
            });
        $p->resolve('a');
        $p->then(static function ($v) use (&$res) {
            $res[] = 'D:' . $v;
        });
        Promise::queue()->run();
        $this->assertSame(['A:foo', 'B', 'D:a', 'C:foo'], $res);
    }

    public function testRecursivelyForwardsWhenNotInstanceOfPromise()
    {
        $res = [];
        $p1 = new Promise();
        $p2 = new ExtendsPromise();
        $p2->then(static function ($v) use (&$res) {
            $res[] = 'p2a:' . $v;
        });
        $p1
            ->then(static function ($v) use ($p2, &$res) {
                $res[] = 'p1a:' . $v;
                return $p2;
            })
            ->then(static function ($v) use (&$res) {
                $res[] = 'p2b:' . $v;
            });

        $p1->resolve('foo');
        $p1->then(static function ($v) use (&$res) {
            $res[] = 'p1b:' . $v;
        });
        Promise::queue()->run();
        $this->assertSame(['p1a:foo', 'p1b:foo'], $res);

        $p2->resolve('bar');
        Promise::queue()->run();
        $this->assertSame(['p1a:foo', 'p1b:foo', 'p2a:bar', 'p2b:bar'], $res);
    }

    public function testCannotResolveWithSelf()
    {
        $this->expectException($this->classes['LogicException']);
        $this->expectExceptionMessage('Cannot fulfill or reject a promise with itself');

        $p = new Promise();
        $p->resolve($p);
    }

    public function testCannotRejectWithSelf()
    {
        $this->expectException($this->classes['LogicException']);
        $this->expectExceptionMessage('Cannot fulfill or reject a promise with itself');

        $p = new Promise();
        $p->reject($p);
    }

    public function testDoesNotBlowStackWhenWaitingOnNestedThens()
    {
        $inner = new Promise(static function () use (&$inner) {
            $inner->resolve(0);
        });
        $prev = $inner;
        for ($i = 1; $i < 100; $i++) {
            $prev = $prev->then(static function ($i) {
                return $i + 1;
            });
        }

        $parent = new Promise(static function () use (&$parent, $prev) {
            $parent->resolve($prev);
        });

        $this->assertSame(99, $parent->wait());
    }

    public function testOtherwiseIsSugarForRejections()
    {
        $p = new Promise();
        $p->reject('foo');
        $p->otherwise(static function ($v) use (&$c) {
            $c = $v;
        });
        Promise::queue()->run();
        $this->assertSame($c, 'foo');
    }

    public function testRepeatedWaitFulfilled()
    {
        $promise = new Promise(static function () use (&$promise) {
            $promise->resolve('foo');
        });

        $this->assertSame('foo', $promise->wait());
        $this->assertSame('foo', $promise->wait());
    }

    public function testRepeatedWaitRejected()
    {
        $promise = new Promise(static function () use (&$promise) {
            $promise->reject(new RuntimeException('foo'));
        });

        $exceptionCount = 0;
        try {
            $promise->wait();
        } catch (Exception $e) {
            $this->assertSame('foo', $e->getMessage());
            $exceptionCount++;
        }

        try {
            $promise->wait();
        } catch (Exception $e) {
            $this->assertSame('foo', $e->getMessage());
            $exceptionCount++;
        }

        $this->assertSame(2, $exceptionCount);
    }
}

<?php

namespace bdk\Test\PromisePhpCond;

use ArrayIterator;
use bdk\Promise;
use bdk\Promise\EachPromise;
use bdk\Promise\FulfilledPromise;
use bdk\Promise\RejectedPromise;
use bdk\Test\Promise\PropertyHelper;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\Promise\EachPromise
 */
class EachPromiseTest extends TestCase
{
    protected $classes = array(
        'Exception' => 'Exception',
    );

    public function testReturnsSameInstance()
    {
        $each = new EachPromise([], ['concurrency' => 100]);
        $this->assertSame($each->promise(), $each->promise());
    }

    public function testResolvesInCaseOfAnEmptyList()
    {
        $promises = array();
        $each = new EachPromise($promises);
        $p = $each->promise();
        $this->assertNull($p->wait());
        $this->assertTrue($p->isFulfilled());
    }

    public function testResolvesInCaseOfAnEmptyListAndInvokesFulfilled()
    {
        $promises = array();
        $each = new EachPromise($promises);
        $p = $each->promise();
        $onFulfilledCalled = false;
        $onRejectedCalled = false;
        $p->then(
            static function () use (&$onFulfilledCalled) {
                $onFulfilledCalled = true;
            },
            static function () use (&$onRejectedCalled) {
                $onRejectedCalled = true;
            }
        );
        $this->assertNull($p->wait());
        $this->assertTrue($p->isFulfilled());
        $this->assertTrue($onFulfilledCalled);
        $this->assertFalse($onRejectedCalled);
    }

    public function testInvokesAllPromises()
    {
        $promises = array(
            new Promise(),
            new Promise(),
            new Promise(),
        );
        $called = [];
        $each = new EachPromise($promises, [
            'fulfilled' => static function ($value) use (&$called) {
                $called[] = $value;
            },
        ]);
        $p = $each->promise();
        $promises[0]->resolve('a');
        $promises[1]->resolve('c');
        $promises[2]->resolve('b');
        Promise::queue()->run();
        $this->assertSame(['a', 'c', 'b'], $called);
        $this->assertTrue($p->isFulfilled());
    }

    public function testIsWaitable()
    {
        $a = $this->createSelfResolvingPromise('a');
        $b = $this->createSelfResolvingPromise('b');
        $called = [];
        $each = new EachPromise(array($a, $b), array(
            'fulfilled' => static function ($value) use (&$called) {
                $called[] = $value;
            },
        ));
        $p = $each->promise();
        $this->assertNull($p->wait());
        $this->assertTrue($p->isFulfilled());
        $this->assertSame(['a', 'b'], $called);
    }

    public function testCanResolveBeforeConsumingAll()
    {
        $called = 0;
        $a = $this->createSelfResolvingPromise('a');
        $b = new Promise(function () {
            $this->fail();
        });
        $each = new EachPromise(array($a, $b), array(
            'fulfilled' => function ($value, $idx, Promise $aggregate) use (&$called) {
                $this->assertSame($idx, 0);
                $this->assertSame('a', $value);
                $aggregate->resolve(null);
                $called++;
            },
            'rejected' => function (\Exception $reason) {
                $this->fail($reason->getMessage());
            },
        ));
        $p = $each->promise();
        $p->wait();
        $this->assertNull($p->wait());
        $this->assertSame(1, $called);
        $this->assertTrue($a->isFulfilled());
        $this->assertTrue($b->isPending());
        // Resolving $b has no effect on the aggregate promise.
        $b->resolve('foo');
        $this->assertSame(1, $called);
    }

    public function testLimitsPendingPromises()
    {
        $pending = array(
            new Promise(),
            new Promise(),
            new Promise(),
            new Promise(),
        );
        $promises = new ArrayIterator($pending);
        $each = new EachPromise($promises, ['concurrency' => 2]);
        $p = $each->promise();
        $this->assertCount(2, PropertyHelper::get($each, 'pending'));

        $pending[0]->resolve('a');
        $this->assertCount(2, PropertyHelper::get($each, 'pending'));
        $this->assertTrue($promises->valid());

        $pending[1]->resolve('b');
        Promise::queue()->run();
        $this->assertCount(2, PropertyHelper::get($each, 'pending'));
        $this->assertTrue($promises->valid());

        $promises[2]->resolve('c');
        Promise::queue()->run();
        $this->assertCount(1, PropertyHelper::get($each, 'pending'));
        $this->assertTrue($p->isPending());

        $promises[3]->resolve('d');
        Promise::queue()->run();
        $this->assertNull(PropertyHelper::get($each, 'pending'));
        $this->assertTrue($p->isFulfilled());

        $this->assertFalse($promises->valid());
    }

    public function testDynamicallyLimitsPendingPromises()
    {
        $calls = [];
        $pendingFn = static function ($count) use (&$calls) {
            $calls[] = $count;
            return 2;
        };
        $pending = array(
            new Promise(),
            new Promise(),
            new Promise(),
            new Promise(),
        );
        $promises = new ArrayIterator($pending);
        $each = new EachPromise($promises, ['concurrency' => $pendingFn]);
        $p = $each->promise();
        $this->assertCount(2, PropertyHelper::get($each, 'pending'));

        $pending[0]->resolve('a');
        $this->assertCount(2, PropertyHelper::get($each, 'pending'));
        $this->assertTrue($promises->valid());

        $pending[1]->resolve('b');
        $this->assertCount(2, PropertyHelper::get($each, 'pending'));
        Promise::queue()->run();
        $this->assertTrue($promises->valid());

        $promises[2]->resolve('c');
        Promise::queue()->run();
        $this->assertCount(1, PropertyHelper::get($each, 'pending'));
        $this->assertTrue($p->isPending());

        $promises[3]->resolve('d');
        Promise::queue()->run();
        $this->assertNull(PropertyHelper::get($each, 'pending'));
        $this->assertTrue($p->isFulfilled($p));
        $this->assertSame([0, 1, 1, 1], $calls);
        $this->assertFalse($promises->valid());
    }

    public function testClearsReferencesWhenResolved()
    {
        $called = false;
        $a = new Promise(static function () use (&$a, &$called) {
            $a->resolve('a');
            $called = true;
        });
        $each = new EachPromise([$a], [
            'concurrency' => static function () {
                return 1;
            },
            'fulfilled' => static function () {
            },
            'rejected'  => static function () {
            },
        ]);
        $each->promise()->wait();
        $this->assertNull(PropertyHelper::get($each, 'onFulfilled'));
        $this->assertNull(PropertyHelper::get($each, 'onRejected'));
        $this->assertNull(PropertyHelper::get($each, 'iterable'));
        $this->assertNull(PropertyHelper::get($each, 'pending'));
        $this->assertNull(PropertyHelper::get($each, 'concurrency'));
        $this->assertTrue($called);
    }

    public function testCanBeCancelled()
    {
        $called = false;
        $a = new FulfilledPromise('a');
        $b = new Promise(static function () use (&$called) {
            $called = true;
        });
        $each = new EachPromise([$a, $b], [
            'fulfilled' => static function ($value, $index, Promise $aggregate) {
                $aggregate->cancel();
            },
            'rejected' => static function ($reason) use (&$called) {
                $called = true;
            },
        ]);
        $p = $each->promise();
        $p->wait(false);
        $this->assertTrue($a->isFulfilled($a));
        $this->assertTrue($b->isPending($b));
        $this->assertTrue($p->isRejected($p));
        $this->assertFalse($called);
    }

    public function testDoesNotBlowStackWithFulfilledPromises()
    {
        $pending = [];
        for ($i = 0; $i < 100; $i++) {
            $pending[] = new FulfilledPromise($i);
        }
        $values = [];
        $each = new EachPromise($pending, [
            'fulfilled' => static function ($value) use (&$values) {
                $values[] = $value;
            },
        ]);
        $called = false;
        $each->promise()->then(static function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
        Promise::queue()->run();
        $this->assertTrue($called);
        $this->assertSame(\range(0, 99), $values);
    }

    public function testDoesNotBlowStackWithRejectedPromises()
    {
        $pending = [];
        for ($i = 0; $i < 100; $i++) {
            $pending[] = new RejectedPromise($i);
        }
        $values = [];
        $each = new EachPromise($pending, [
            'rejected' => static function ($value) use (&$values) {
                $values[] = $value;
            },
        ]);
        $called = false;
        $each->promise()->then(
            static function () use (&$called) {
                $called = true;
            },
            function () {
                $this->fail('Should not have rejected.');
            }
        );
        $this->assertFalse($called);
        Promise::queue()->run();
        $this->assertTrue($called);
        $this->assertSame(\range(0, 99), $values);
    }

    public function testReturnsPromiseForWhatever()
    {
        $called = [];
        $arr = ['a', 'b'];
        $each = new EachPromise($arr, [
            'fulfilled' => static function ($v) use (&$called) {
                $called[] = $v;
            },
        ]);
        $p = $each->promise();
        $this->assertNull($p->wait());
        $this->assertSame(['a', 'b'], $called);
    }

    public function testRejectsAggregateWhenNextThrows()
    {
        $iter = static function () {
            yield 'a';
            throw new Exception('Failure');
        };
        $each = new EachPromise($iter());
        $p = $each->promise();
        $e = null;
        $p->then(null, static function ($reason) use (&$e) {
            $e = $reason;
        });
        Promise::queue()->run();
        $this->assertInstanceOf($this->classes['Exception'], $e);
        $this->assertSame('Failure', $e->getMessage());
    }

    public function testDoesNotCallNextOnIteratorUntilNeededWhenWaiting()
    {
        $results = [];
        $values = [10];
        $remaining = 9;
        $iter = static function () use (&$values) {
            while ($value = \array_pop($values)) {
                yield $value;
            }
        };
        $each = new EachPromise($iter(), [
            'concurrency' => 1,
            'fulfilled' => static function ($r) use (&$results, &$values, &$remaining) {
                $results[] = $r;
                if ($remaining > 0) {
                    $values[] = $remaining--;
                }
            },
        ]);
        $each->promise()->wait();
        $this->assertSame(\range(10, 1), $results);
    }

    public function testDoesNotCallNextOnIteratorUntilNeededWhenAsync()
    {
        $firstPromise = new Promise();
        $pending = [$firstPromise];
        $values = [$firstPromise];
        $results = [];
        $remaining = 9;
        $iter = static function () use (&$values) {
            while ($value = \array_pop($values)) {
                yield $value;
            }
        };
        $each = new EachPromise($iter(), [
            'concurrency' => 1,
            'fulfilled' => static function ($r) use (&$results, &$values, &$remaining, &$pending) {
                $results[] = $r;
                if ($remaining-- > 0) {
                    $p = new Promise();
                    $pending[] = $p;
                    $values[] = $p;
                }
            },
        ]);
        $i = 0;
        $each->promise();
        while ($promise = \array_pop($pending)) {
            $promise->resolve($i++);
            Promise::queue()->run();
        }
        $this->assertSame(\range(0, 9), $results);
    }

    private function createSelfResolvingPromise($value)
    {
        $p = new Promise(static function () use (&$p, $value) {
            $p->resolve($value);
        });
        $trickCsFixer = true;
        return $p;
    }

    public function testMutexPreventsGeneratorRecursion()
    {
        if (\defined('HHVM_VERSION')) {
            $this->markTestIncomplete('Broken on HHVM.');
        }

        $pending = array();
        $promises = array();
        $results = array();
        for ($i = 0; $i < 20; $i++) {
            $p = $this->createSelfResolvingPromise($i);
            $pending[] = $p;
            $promises[] = $p;
        }

        $iter = static function () use (&$promises, &$pending) {
            foreach ($promises as $promise) {
                // Resolve a promises, which will trigger the then() function,
                // which would cause the EachPromise to try to add more
                // promises to the queue. Without a lock, this would trigger
                // a "Cannot resume an already running generator" fatal error.
                $p = \array_pop($pending);
                if ($p) {
                    $p->wait();
                }
                yield $promise;
            }
        };

        $each = new EachPromise($iter(), [
            'concurrency' => 5,
            'fulfilled' => static function ($r) use (&$results, &$pending) {
                $results[] = $r;
            },
        ]);

        $each->promise()->wait();
        $this->assertCount(20, $results);
    }

    public function testIteratorWithSameKey()
    {
        if (\defined('HHVM_VERSION')) {
            $this->markTestIncomplete('Broken on HHVM.');
        }

        $iter = function () {
            yield 'foo' => $this->createSelfResolvingPromise(1);
            yield 'foo' => $this->createSelfResolvingPromise(2);
            yield 1 => $this->createSelfResolvingPromise(3);
            yield 1 => $this->createSelfResolvingPromise(4);
        };
        $called = 0;
        $each = new EachPromise($iter(), [
            'fulfilled' => function ($value, $index, Promise $aggregate) use (&$called) {
                $called++;
                if ($value < 3) {
                    $this->assertSame('foo', $index);
                } else {
                    $this->assertSame(1, $index);
                }
            },
        ]);
        $each->promise()->wait();
        $this->assertSame(4, $called);
    }

    public function testIsWaitableWhenLimited()
    {
        $promises = [
            $this->createSelfResolvingPromise('a'),
            $this->createSelfResolvingPromise('c'),
            $this->createSelfResolvingPromise('b'),
            $this->createSelfResolvingPromise('d'),
        ];
        $called = [];
        $each = new EachPromise($promises, [
            'concurrency' => 2,
            'fulfilled' => static function ($value) use (&$called) {
                $called[] = $value;
            },
        ]);
        $p = $each->promise();
        $this->assertNull($p->wait());
        $this->assertSame(['a', 'c', 'b', 'd'], $called);
        $this->assertTrue($p->isFulfilled());
    }
}

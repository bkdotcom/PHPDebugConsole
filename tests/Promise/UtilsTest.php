<?php

namespace bdk\Test\Promise;

use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Promise;
use bdk\Promise\FulfilledPromise;
use bdk\Promise\RejectedPromise;
use bdk\Promise\Utils;
use bdk\Test\Promise\PropertyHelper;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\Promise\Each
 * @covers bdk\Promise\Utils
 */
class UtilsTest extends TestCase
{
    use ExpectExceptionTrait;

    protected $classes = array(
        'AggregateException' => 'bdk\\Promise\\Exception\\AggregateException',
        'RejectionException' => 'bdk\\Promise\\Exception\\RejectionException',
        'TaskQueue' => 'bdk\\Promise\\TaskQueue',
    );

    /**
     * @param mixed       $value
     * @param string      $type
     * @param bool        $allowNull
     * @param null|string $exceptionMessage
     *
     * @dataProvider providerAssertType
     */
    public function testAssertType($value, $type, $allowNull = true, $exceptionMessage = null)
    {
        if ($exceptionMessage !== null) {
            $this->expectException('InvalidArgumentException');
            $this->expectExceptionMessage($exceptionMessage);
        }
        Utils::assertType($value, $type, $allowNull);
        self::assertTrue(true);
    }

    public function providerAssertType()
    {
        return [
            [array(), 'array', false],
            ['call_user_func', 'callable', false],
            [(object) array(), 'object', false],
            [new \bdk\PubSub\Event(), 'bdk\PubSub\Event', false],

            [array(), 'array', true],
            ['call_user_func', 'callable'],
            [(object) array(), 'object', true],
            [new \bdk\PubSub\Event(), 'bdk\PubSub\Event', true],

            [null, 'array', true ],
            [null, 'callable', true],
            [null, 'object', true],
            [null, 'bdk\PubSub\Event', true],

            [(object) array(), 'array', true, 'Expected array (or null), got stdClass'],

            [null, 'array', false, 'Expected array, got null'],
            [null, 'callable', false, 'Expected callable, got null'],
            [null, 'object', false, 'Expected object, got null'],
            [null, 'bdk\PubSub\Event', false, 'Expected bdk\PubSub\Event, got null'],

            [false, 'array', true, 'Expected array (or null), got bool'],
            [false, 'callable', true, 'Expected callable (or null), got bool'],
            [false, 'object', true, 'Expected object (or null), got bool'],
            [false, 'bdk\PubSub\Event', true, 'Expected bdk\PubSub\Event (or null), got bool'],

            [false, 'array', false, 'Expected array, got bool'],
            [false, 'callable', false, 'Expected callable, got bool'],
            [false, 'object', false, 'Expected object, got bool'],
            [false, 'bdk\PubSub\Event', false, 'Expected bdk\PubSub\Event, got bool'],

        ];
    }

    public function testQueue()
    {
        PropertyHelper::set('bdk\\Promise\\Utils', 'queue', null);
        $queue = Promise::queue();
        self::assertInstanceOf($this->classes['TaskQueue'], $queue);
    }

    public function testWaitsOnAllPromisesIntoArray()
    {
        $e = new Exception();
        $a = new Promise(static function () use (&$a) {
            $a->resolve('a');
        });
        $b = new Promise(static function () use (&$b) {
            $b->reject('b');
        });
        $c = new Promise(static function () use (&$c, $e) {
            $c->reject($e);
        });
        $results = Promise::inspectAll([$a, $b, $c]);
        self::assertSame([
            ['state' => 'fulfilled', 'value' => 'a'],
            ['state' => 'rejected', 'reason' => 'b'],
            ['state' => 'rejected', 'reason' => $e],
        ], $results);
    }

    public function testUnwrapsPromisesWithNoDefaultAndFailure()
    {
        $this->expectException($this->classes['RejectionException']);
        $promises = array(new FulfilledPromise('a'), new Promise());
        Promise::unwrap($promises);
    }

    public function testUnwrapsPromisesWithNoDefault()
    {
        $promises = [new FulfilledPromise('a')];
        self::assertSame(['a'], Promise::unwrap($promises));
    }

    public function testUnwrapsPromisesWithKeys()
    {
        $promises = [
            'foo' => new FulfilledPromise('a'),
            'bar' => new FulfilledPromise('b'),
        ];
        self::assertSame([
            'foo' => 'a',
            'bar' => 'b',
        ], Promise::unwrap($promises));
    }

    public function testAllAggregatesSortedArray()
    {
        $a = new Promise();
        $b = new Promise();
        $c = new Promise();
        $d = Promise::all([$a, $b, $c]);
        $b->resolve('b');
        $a->resolve('a');
        $c->resolve('c');
        $d->then(
            static function ($value) use (&$result) {
                $result = $value;
            },
            static function ($reason) use (&$result) {
                $result = $reason;
            }
        );
        Promise::queue()->run();
        self::assertSame(['a', 'b', 'c'], $result);
    }

    public function testPromisesDynamicallyAddedToStack()
    {
        $promises = new \ArrayIterator();
        $counter = 0;
        $promises['a'] = new FulfilledPromise('a');
        $promise = new Promise(static function () use (&$promise, &$promises, &$counter) {
            $counter++; // Make sure the wait function is called only once
            $promise->resolve('b');
            $subPromise = new Promise(static function () use (&$subPromise) {
                $subPromise->resolve('c');
            });
            $promises['c'] = $subPromise;
        });
        $promises['b'] = $promise;
        $result = Promise::all($promises, true)->wait();
        self::assertCount(3, $promises);
        self::assertCount(3, $result);
        self::assertSame($result['c'], 'c');
        self::assertSame(1, $counter);
    }

    public function testAllThrowsWhenAnyRejected()
    {
        $a = new Promise();
        $b = new Promise();
        $c = new Promise();
        $d = Promise::all([$a, $b, $c]);
        $b->resolve('b');
        $a->reject('fail');
        $c->resolve('c');
        $d->then(
            static function ($value) use (&$result) {
                $result = $value;
            },
            static function ($reason) use (&$result) {
                $result = $reason;
            }
        );
        Promise::queue()->run();
        self::assertSame('fail', $result);
    }

    public function testSomeAggregatesSortedArrayWithMax()
    {
        $a = new Promise();
        $b = new Promise();
        $c = new Promise();
        $d = Promise::some(2, [$a, $b, $c]);
        $b->resolve('b');
        $c->resolve('c');
        $a->resolve('a');
        $d->then(static function ($value) use (&$result) {
            $result = $value;
        });
        Promise::queue()->run();
        self::assertSame(['b', 'c'], $result);
    }

    public function testSomeRejectsWhenTooManyRejections()
    {
        $a = new Promise();
        $b = new Promise();
        $d = Promise::some(2, [$a, $b]);
        $a->reject('bad');
        $b->resolve('good');
        Promise::queue()->run();
        self::assertTrue(Promise::isRejected($d));
        $d->then(null, static function ($reason) use (&$called) {
            $called = $reason;
        });
        Promise::queue()->run();
        self::assertInstanceOf($this->classes['AggregateException'], $called);
        self::assertContains('bad', $called->getReason());
    }

    public function testCanWaitUntilSomeCountIsSatisfied()
    {
        $a = new Promise(static function () use (&$a) {
            $a->resolve('a');
        });
        $b = new Promise(static function () use (&$b) {
            $b->resolve('b');
        });
        $c = new Promise(static function () use (&$c) {
            $c->resolve('c');
        });
        $d = Promise::some(2, [$a, $b, $c]);
        self::assertSame(['a', 'b'], $d->wait());
    }

    public function testThrowsIfImpossibleToWaitForSomeCount()
    {
        $this->expectException($this->classes['AggregateException']);
        $this->expectExceptionMessage('Not enough promises to fulfill count');

        $a = new Promise(static function () use (&$a) {
            $a->resolve('a');
        });
        $d = Promise::some(2, [$a]);
        $d->wait();
    }

    public function testThrowsIfResolvedWithoutCountTotalResults()
    {
        $this->expectException($this->classes['AggregateException']);
        $this->expectExceptionMessage('Not enough promises to fulfill count');

        $a = new Promise();
        $b = new Promise();
        $d = Promise::some(3, [$a, $b]);
        $a->resolve('a');
        $b->resolve('b');
        $d->wait();
    }

    public function testAnyReturnsFirstMatch()
    {
        $a = new Promise();
        $b = new Promise();
        $c = Promise::any([$a, $b]);
        $b->resolve('b');
        $a->resolve('a');
        $c->then(static function ($value) use (&$result) {
            $result = $value;
        });
        Promise::queue()->run();
        self::assertSame('b', $result);
    }

    public function testSettleFulfillsWithFulfilledAndRejected()
    {
        $a = new Promise();
        $b = new Promise();
        $c = new Promise();
        $d = Promise::settle([$a, $b, $c]);
        $b->resolve('b');
        $c->resolve('c');
        $a->reject('a');
        Promise::queue()->run();
        self::assertTrue(Promise::isFulfilled($d));
        $d->then(static function ($value) use (&$result) {
            $result = $value;
        });
        Promise::queue()->run();
        self::assertSame([
            ['reason' => 'a', 'state' => 'rejected'],
            ['state' => 'fulfilled', 'value' => 'b'],
            ['state' => 'fulfilled', 'value' => 'c'],
        ], $result);
    }

    public function testCanInspectFulfilledPromise()
    {
        $p = new FulfilledPromise('foo');
        self::assertSame([
            'state' => 'fulfilled',
            'value' => 'foo',
        ], Promise::inspect($p));
    }

    public function testCanInspectRejectedPromise()
    {
        $p = new RejectedPromise('foo');
        self::assertSame([
            'state'  => 'rejected',
            'reason' => 'foo',
        ], Promise::inspect($p));
    }

    public function testCanInspectRejectedPromiseWithNormalException()
    {
        $e = new Exception('foo');
        $p = new RejectedPromise($e);
        self::assertSame([
            'state'  => 'rejected',
            'reason' => $e,
        ], Promise::inspect($p));
    }

    public function testReturnsTrampoline()
    {
        self::assertInstanceOf($this->classes['TaskQueue'], Promise::queue());
        self::assertSame(Promise::queue(), Promise::queue());
    }

    public function testCanScheduleThunk()
    {
        $tramp = Promise::queue();
        $promise = Promise::task(static function () {
            return 'Hi!';
        });
        $c = null;
        $promise->then(static function ($v) use (&$c) {
            $c = $v;
        });
        self::assertNull($c);
        $tramp->run();
        self::assertSame('Hi!', $c);
    }

    public function testCanScheduleThunkWithRejection()
    {
        $tramp = Promise::queue();
        $promise = Promise::task(static function () {
            throw new Exception('Hi!');
        });
        $c = null;
        $promise->otherwise(static function ($v) use (&$c) {
            $c = $v;
        });
        self::assertNull($c);
        $tramp->run();
        self::assertSame('Hi!', $c->getMessage());
    }

    public function testCanScheduleThunkWithWait()
    {
        $tramp = Promise::queue();
        $promise = Promise::task(static function () {
            return 'a';
        });
        self::assertSame('a', $promise->wait());
        $tramp->run();
    }

    public function testCanManuallySettleTaskQueueGeneratedPromises()
    {
        $p1 = Promise::task(static function () {
            return 'a';
        });
        $p2 = Promise::task(static function () {
            return 'b';
        });
        $p3 = Promise::task(static function () {
            return 'c';
        });

        $p1->cancel();
        $p2->resolve('b2');

        $results = Promise::inspectAll([$p1, $p2, $p3]);

        self::assertSame([
            ['state' => 'rejected', 'reason' => 'Promise has been cancelled'],
            ['state' => 'fulfilled', 'value' => 'b2'],
            ['state' => 'fulfilled', 'value' => 'c'],
        ], $results);
    }
}

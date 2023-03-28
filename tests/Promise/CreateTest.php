<?php

namespace bdk\Test\Promise;

use ArrayIterator;
use bdk\Promise;
use bdk\Promise\FulfilledPromise;
use bdk\Promise\PromiseInterface;
use bdk\Promise\RejectedPromise;
use bdk\Test\Promise\Fixture\Thenable;
use bdk\Test\Promise\PropertyHelper;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\Promise\Create
 */
class CreateTest extends TestCase
{
    protected $classes = array(
        'Exception' => 'Exception',
        'FulfilledPromise' => 'bdk\\Promise\\FulfilledPromise',
        'Promise' => 'bdk\\Promise',
        'RejectedPromise' => 'bdk\\Promise\\RejectedPromise',
        'RejectionException' => 'bdk\\Promise\\Exception\\RejectionException',
    );

    public function testCreatesPromiseForValue()
    {
        $promise = Promise::promiseFor('foo');
        $this->assertInstanceOf($this->classes['FulfilledPromise'], $promise);
    }

    public function testReturnsPromiseForPromise()
    {
        $promise = new Promise();
        $this->assertSame($promise, Promise::promiseFor($promise));
    }

    public function testReturnsPromiseForThenable()
    {
        $promise = new Thenable();
        $wrapped = Promise::promiseFor($promise);
        $this->assertNotSame($promise, $wrapped);
        $this->assertInstanceOf($this->classes['Promise'], $wrapped);
        $promise->resolve('foo');
        Promise::queue()->run();
        $this->assertSame('foo', $wrapped->wait());
    }

    public function testReturnsException()
    {
        $exception = Promise::exceptionFor('some reason');
        $this->assertInstanceOf($this->classes['RejectionException'], $exception);
        $this->assertSame('some reason', $exception->getReason());
        $this->assertSame('The promise was rejected with reason: some reason', $exception->getMessage());

        $exception = Promise::exceptionFor(new Exception('reason'));
        $this->assertInstanceOf($this->classes['Exception'], $exception);
    }

    public function testReturnsRejection()
    {
        $promise = Promise::rejectionFor('fail');
        $this->assertInstanceOf($this->classes['RejectedPromise'], $promise);
        $this->assertSame('fail', PropertyHelper::get($promise, 'result'));
    }

    public function testReturnsPromisesAsIsInRejectionFor()
    {
        $p1 = new Promise();
        $p2 = Promise::rejectionFor($p1);
        $this->assertSame($p1, $p2);
    }

    public function testIteratorPassedIterator()
    {
        $iter = new ArrayIterator();
        $this->assertSame($iter, Promise::iteratorFor($iter));
    }

    public function testIteratorPassedArray()
    {
        $val = array('foo', 'bar');
        $iterator = Promise::iteratorFor($val);
        $this->assertInstanceOf('ArrayIterator', $iterator);
        $this->assertSame($val, \iterator_to_array($iterator));
    }

    public function testIteratorPassedScalar()
    {
        $val = 'test me';
        $iterator = Promise::iteratorFor($val);
        $this->assertInstanceOf('ArrayIterator', $iterator);
        $this->assertSame(array($val), \iterator_to_array($iterator));
    }
}

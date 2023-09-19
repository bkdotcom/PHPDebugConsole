<?php

namespace bdk\Test\PromisePhpCond;

use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\Promise;
use bdk\Promise\Coroutine;
use bdk\Promise\Exception\RejectionException;
use bdk\Promise\FulfilledPromise;
use bdk\Promise\PromiseInterface;
use bdk\Promise\RejectedPromise;
use bdk\Test\Promise\PropertyHelper;
use Exception;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\Promise\Coroutine
 */
class CoroutineTest extends TestCase
{
    use AssertionTrait;

    protected static $classes = array(
        'Coroutine' => 'bdk\\Promise\\Coroutine',
        'OutOfBoundsException' => 'OutOfBoundsException',
        'Promise' => 'bdk\\Promise',
        'RejectionException' => 'bdk\\Promise\\Exception\\RejectionException',
    );

    public function testReturnsCoroutine()
    {
        self::assertInstanceOf(self::$classes['Coroutine'], Coroutine::of(static function () {
            yield 'foo';
        }));
    }

    public function testGeneratorException()
    {
        $promise = Coroutine::of(static function () {
            throw new Exception('thrown in generator');
            yield 'Never yielded';
        });
        self::assertTrue(Promise::isRejected($promise));
        $promise->then(null, function (Exception $reason) {
            self::assertSame('thrown in generator', $reason->getMessage());
        });
    }

    /**
     * @dataProvider providerPromiseInterfaceMethod
     *
     * @param string $method
     * @param array  $args
     */
    public function testShouldProxyPromiseMethodsToResultPromise($method, $args = [])
    {
        $coroutine = new Coroutine(static function () {
            yield 0;
        });
        $mockPromise = $this
            ->getMockBuilder(self::$classes['Promise'])
            ->setMethods([$method])
            ->getMock();
        \call_user_func_array(
            [$mockPromise->expects(self::once())->method($method), 'with'],
            $args
        );

        PropertyHelper::set($coroutine, 'promise', $mockPromise);
        \call_user_func_array([$coroutine, $method], $args);
    }

    public static function providerPromiseInterfaceMethod()
    {
        return array(
            ['then', [null, null]],
            ['otherwise', [static function () {}]],
            ['wait', [true]],
            ['getState', []],
            ['resolve', [null]],
            ['reject', [null]],
        );
    }

    public function testShouldCancelResultPromiseAndOutsideCurrentPromise()
    {
        $coroutine = new Coroutine(static function () {
            yield 0;
        });

        $mockPromises = [
            'promise' => $this
                ->getMockBuilder(self::$classes['Promise'])
                ->setMethods(['cancel'])
                ->getMock(),
            'currentPromise' => $this
                ->getMockBuilder(self::$classes['Promise'])
                ->setMethods(['cancel'])
                ->getMock(),
        ];
        foreach ($mockPromises as $propName => $mockPromise) {
            /**
             * @var $mockPromise \PHPUnit_Framework_MockObject_MockObject
             */
            $mockPromise->expects($this->once())
                ->method('cancel')
                ->with();
            PropertyHelper::set($coroutine, $propName, $mockPromise);
        }

        $coroutine->cancel();
    }

    public function testWaitShouldResolveChainedCoroutines()
    {
        $promisor = static function () {
            return Coroutine::of(static function () {
                $promise = new Promise(static function () use (&$promise) {
                    $promise->resolve(1);
                });
                yield $promise;
            });
        };

        $promise = $promisor()
            ->then($promisor)
            ->then($promisor);

        self::assertSame(1, $promise->wait());
    }

    public function testWaitShouldHandleIntermediateErrors()
    {
        $promise = Coroutine::of(static function () {
            $promise = new Promise(static function () use (&$promise) {
                $promise->resolve(1);
            });
            yield $promise;
        })
        ->then(static function () {
            return Coroutine::of(static function () {
                $promise = new Promise(static function () use (&$promise) {
                    $promise->reject(new Exception('dang'));
                });
                yield $promise;
            });
        })
        ->otherwise(static function (Exception $error = null) {
            if (!$error) {
                self::fail('Error did not propagate.');
            }
            return 3;
        });

        self::assertSame(3, $promise->wait());
    }

    public function testHandleFailureNextCoroutine()
    {
        $promise = Coroutine::of(static function () {
            try {
                $promise = new Promise(static function () use (&$promise) {
                    $promise->reject(new Exception('beans'));
                });
                yield $promise;
            } catch (Exception $e) {
            }
            yield 'next yield';
        });
        self::assertSame('next yield', $promise->wait());
    }

    public function testYieldsFromCoroutine()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }
        $promise = Coroutine::of(static function () {
            $value = yield new FulfilledPromise('a');
            yield $value . 'b';
        });
        $promise->then(static function ($value) use (&$result) {
            $result = $value;
        });
        Promise::queue()->run();
        self::assertSame('ab', $result);
    }

    public function testCanCatchExceptionsInCoroutine()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $promise = Coroutine::of(function () {
            try {
                yield new RejectedPromise('a');
                self::fail('Should have thrown into the coroutine!');
            } catch (RejectionException $e) {
                $value = yield new FulfilledPromise($e->getReason());
                yield $value . 'b';
            }
        });
        $promise->then(static function ($value) use (&$result) {
            $result = $value;
        });
        Promise::queue()->run();
        self::assertTrue(Promise::isFulfilled($promise));
        self::assertSame('ab', $result);
    }

    public function testCanRejectFromRejectionCallback()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $promise = Coroutine::of(static function () {
            yield new FulfilledPromise(0);
            yield new RejectedPromise('no!');
        });
        $promise->then(
            function () {
                $this->fail();
            },
            static function ($reason) use (&$result) {
                $result = $reason;
            }
        );
        Promise::queue()->run();
        self::assertInstanceOf(self::$classes['RejectionException'], $result);
        self::assertSame('no!', $result->getReason());
    }

    public function testCanAsyncReject()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $rej = new Promise();
        $promise = Coroutine::of(static function () use ($rej) {
            yield new FulfilledPromise(0);
            yield $rej;
        });
        $promise->then(
            function () {
                $this->fail();
            },
            static function ($reason) use (&$result) {
                $result = $reason;
            }
        );
        $rej->reject('no!');
        Promise::queue()->run();
        self::assertInstanceOf(self::$classes['RejectionException'], $result);
        self::assertSame('no!', $result->getReason());
    }

    public function testCanCatchAndThrowOtherException()
    {
        $promise = Coroutine::of(function () {
            try {
                yield new RejectedPromise('a');
                $this->fail('Should have thrown into the coroutine!');
            } catch (RejectionException $e) {
                throw new \Exception('foo');
            }
        });
        $promise->otherwise(static function ($value) use (&$result) {
            $result = $value;
        });
        Promise::queue()->run();
        self::assertTrue(Promise::isRejected($promise));
        self::assertStringContainsString('foo', $result->getMessage());
    }

    public function testCanCatchAndYieldOtherException()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $promise = Coroutine::of(function () {
            try {
                yield new RejectedPromise('a');
                self::fail('Should have thrown into the coroutine!');
            } catch (RejectionException $e) {
                yield new RejectedPromise('foo');
            }
        });
        $promise->otherwise(static function ($value) use (&$result) {
            $result = $value;
        });
        Promise::queue()->run();
        self::assertTrue(Promise::isRejected($promise));
        self::assertStringContainsString('foo', $result->getMessage());
    }

    public function testLotsOfTryCatchingDoesNotBlowStack()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $promise = $this->createLotsOfFlappingPromise();
        $promise->then(static function ($v) use (&$r) {
            $r = $v;
        });
        Promise::queue()->run();
        self::assertSame(999, $r);
    }

    public function testLotsOfTryCatchingWaitingDoesNotBlowStack()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $promise = $this->createLotsOfFlappingPromise();
        $promise->then(static function ($v) use (&$r) {
            $r = $v;
        });
        self::assertSame(999, $promise->wait());
        self::assertSame(999, $r);
    }

    public function testAsyncPromisesWithCorrectlyYieldedValues()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $promises = [
            new Promise(),
            new Promise(),
            new Promise(),
        ];

        eval('
        $promise = \bdk\Promise\Coroutine::of(function () use ($promises) {
            $value = null;
            self::assertSame(\'skip\', (yield new \bdk\Promise\FulfilledPromise(\'skip\')));
            foreach ($promises as $idx => $p) {
                $value = (yield $p);
                self::assertSame($idx, $value);
                self::assertSame(\'skip\', (yield new \bdk\Promise\FulfilledPromise(\'skip\')));
            }
            self::assertSame(\'skip\', (yield new \bdk\Promise\FulfilledPromise(\'skip\')));
            yield $value;
        });
        ');

        $promises[0]->resolve(0);
        $promises[1]->resolve(1);
        $promises[2]->resolve(2);

        $promise->then(static function ($v) use (&$r) {
            $r = $v;
        });
        Promise::queue()->run();
        self::assertSame(2, $r);
    }

    public function testYieldFinalWaitablePromise()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $p1 = new Promise(static function () use (&$p1) {
            $p1->resolve('skip me');
        });
        $p2 = new Promise(static function () use (&$p2) {
            $p2->resolve('hello!');
        });
        $co = Coroutine::of(static function () use ($p1, $p2) {
            yield $p1;
            yield $p2;
        });
        Promise::queue()->run();
        self::assertSame('hello!', $co->wait());
    }

    public function testCanYieldFinalPendingPromise()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $p1 = new Promise();
        $p2 = new Promise();
        $co = Coroutine::of(static function () use ($p1, $p2) {
            yield $p1;
            yield $p2;
        });
        $p1->resolve('a');
        $p2->resolve('b');
        $co->then(static function ($value) use (&$result) {
            $result = $value;
        });
        Promise::queue()->run();
        self::assertSame('b', $result);
    }

    public function testCanNestYieldsAndFailures()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $p1 = new Promise();
        $p2 = new Promise();
        $p3 = new Promise();
        $p4 = new Promise();
        $p5 = new Promise();
        $co = Coroutine::of(static function () use ($p1, $p2, $p3, $p4, $p5) {
            try {
                yield $p1;
            } catch (Exception $e) {
                yield $p2;
                try {
                    yield $p3;
                    yield $p4;
                } catch (Exception $e) {
                    yield $p5;
                }
            }
        });
        $p1->reject('a');
        $p2->resolve('b');
        $p3->resolve('c');
        $p4->reject('d');
        $p5->resolve('e');
        $co->then(static function ($value) use (&$result) {
            $result = $value;
        });
        Promise::queue()->run();
        self::assertSame('e', $result);
    }

    public function testCanYieldErrorsAndSuccessesWithoutRecursion()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $promises = [];
        for ($i = 0; $i < 20; $i++) {
            $promises[] = new Promise();
        }

        $co = Coroutine::of(static function () use ($promises) {
            for ($i = 0; $i < 20; $i += 4) {
                try {
                    yield $promises[$i];
                    yield $promises[$i + 1];
                } catch (\Exception $e) {
                    yield $promises[$i + 2];
                    yield $promises[$i + 3];
                }
            }
        });

        for ($i = 0; $i < 20; $i += 4) {
            $promises[$i]->resolve($i);
            $promises[$i + 1]->reject($i + 1);
            $promises[$i + 2]->resolve($i + 2);
            $promises[$i + 3]->resolve($i + 3);
        }

        $co->then(static function ($value) use (&$result) {
            $result = $value;
        });
        Promise::queue()->run();
        self::assertSame(19, $result);
    }

    public function testCanWaitOnPromiseAfterFulfilled()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $f = static function () {
            static $i = 0;
            $i++;
            $p = new Promise(static function () use (&$p, $i) {
                $p->resolve($i . '-bar');
            });
            return $p;
        };

        $promises = [];
        for ($i = 0; $i < 20; $i++) {
            $promises[] = $f();
        }

        $p = Coroutine::of(static function () use ($promises) {
            yield new FulfilledPromise('foo!');
            foreach ($promises as $promise) {
                yield $promise;
            }
        });

        self::assertSame('20-bar', $p->wait());
    }

    public function testCanWaitOnErroredPromises()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $p1 = new Promise(static function () use (&$p1) {
            $p1->reject('a');
        });
        $p2 = new Promise(static function () use (&$p2) {
            $p2->resolve('b');
        });
        $p3 = new Promise(static function () use (&$p3) {
            $p3->resolve('c');
        });
        $p4 = new Promise(static function () use (&$p4) {
            $p4->reject('d');
        });
        $p5 = new Promise(static function () use (&$p5) {
            $p5->resolve('e');
        });
        $p6 = new Promise(static function () use (&$p6) {
            $p6->reject('f');
        });

        $co = Coroutine::of(static function () use ($p1, $p2, $p3, $p4, $p5, $p6) {
            try {
                yield $p1;
            } catch (\Exception $e) {
                yield $p2;
                try {
                    yield $p3;
                    yield $p4;
                } catch (\Exception $e) {
                    yield $p5;
                    yield $p6;
                }
            }
        });

        $res = Promise::inspect($co);
        self::assertSame('f', $res['reason']);
    }

    public function testCoroutineOtherwiseIntegrationTest()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $a = new Promise();
        $b = new Promise();
        $promise = Coroutine::of(static function () use ($a, $b) {
            // Execute the pool of commands concurrently, and process errors.
            yield $a;
            yield $b;
        })->otherwise(static function (Exception $e) {
            // Throw errors from the operations as a specific Multipart error.
            throw new OutOfBoundsException('a', 0, $e);
        });
        $a->resolve('a');
        $b->reject('b');
        $reason = Promise::inspect($promise)['reason'];
        self::assertInstanceOf(self::$classes['OutOfBoundsException'], $reason);
        self::assertInstanceOf(self::$classes['RejectionException'], $reason->getPrevious());
    }

    public function testLotsOfSynchronousDoesNotBlowStack()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $promise = $this->createLotsOfSynchronousPromise();
        $promise->then(static function ($v) use (&$r) {
            $r = $v;
        });
        Promise::queue()->run();
        self::assertSame(999, $r);
    }

    public function testLotsOfSynchronousWaitDoesNotBlowStack()
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestIncomplete('Broken on HHVM.');
        }

        $promise = $this->createLotsOfSynchronousPromise();
        $promise->then(static function ($v) use (&$r) {
            $r = $v;
        });
        self::assertSame(999, $promise->wait());
        self::assertSame(999, $r);
    }

    /**
     * @dataProvider providerRejectsParentException
     */
    public function testRejectsParentExceptionWhenException(PromiseInterface $promise)
    {
        $promise->then(
            function () {
                $this->fail();
            },
            static function ($reason) use (&$result) {
                $result = $reason;
            }
        );
        Promise::queue()->run();
        self::assertInstanceOf('Exception', $result);
        self::assertSame('a', $result->getMessage());
    }

    public static function providerRejectsParentException()
    {
        return array(
            [Coroutine::of(static function () {
                yield new FulfilledPromise(0);
                throw new Exception('a');
            })],
            [Coroutine::of(static function () {
                throw new Exception('a');
                yield new FulfilledPromise(0);
            })],
        );
    }

    private function createLotsOfFlappingPromise()
    {
        return Coroutine::of(static function () {
            for ($i = 0; $i < 1000; $i++) {
                try {
                    if ($i % 2) {
                        yield new FulfilledPromise($i);
                    }
                    yield new RejectedPromise($i);
                } catch (Exception $e) {
                    yield new FulfilledPromise($i);
                }
            }
        });
    }

    private function createLotsOfSynchronousPromise()
    {
        return Coroutine::of(static function () {
            for ($i = 0; $i < 1000; $i++) {
                yield new FulfilledPromise($i);
            }
        });
    }
}

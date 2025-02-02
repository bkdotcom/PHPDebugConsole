<?php

/**
 * @package   bdk\promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk;

use BadMethodCallException;
use bdk\Promise\Exception\CancellationException;
use bdk\Promise\PromiseInterface;
use Exception;
use LogicException;
use Throwable;

/**
 * Promises/A+ implementation that avoids recursion when possible.
 *
 * @link https://promisesaplus.com/
 *
 * @method bool isPending()
 * @method bool isSettled()
 * @method bool isFulfilled()
 * @method bool isRejected()
 *
 * @psalm-consistent-constructor
 */
class Promise implements PromiseInterface
{
    /** @var mixed */
    protected $result;

    /** @var string */
    protected $state = self::PENDING;

    /** @var callable|null */
    protected $waitFn;

    /** @var callable|null */
    private $cancelFn;

    /** @var list<list{Promise,callable|null,callable|null}> */
    private $handlers = array();

    /** @var self[] Promise chain */
    private $waitList = array();

    /**
     * @param callable|null $waitFn   Fn that when invoked resolves the promise.
     * @param callable|null $cancelFn Fn that when invoked cancels the promise.
     */
    public function __construct($waitFn = null, $cancelFn = null)
    {
        \bdk\Promise\Utils::assertType($waitFn, 'callable');
        \bdk\Promise\Utils::assertType($cancelFn, 'callable');

        $this->waitFn = $waitFn;
        $this->cancelFn = $cancelFn;
    }

    /**
     * Magic method... inaccessible method called.
     *
     * @param string $method Inaccessible method name
     * @param array  $args   Arguments passed to method
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call($method, array $args)
    {
        if (\preg_match('/^is([A-Z][a-z]+)$/', $method)) {
            $args = [$this];
        }
        return $this->__callStatic($method, $args);
    }

    /**
     * Magic method... inaccessible static method called.
     *
     * @param string $method Inaccessible method name
     * @param array  $args   Arguments passed to method
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public static function __callStatic($method, array $args)
    {
        $isClass = 'bdk\\Promise\\Is';
        $utilClasses = [
            'bdk\\Promise\\Utils',
            'bdk\\Promise\\Create',
        ];
        if (\preg_match('/^is([A-Z][a-z]+)$/', $method, $matches) && \method_exists($isClass, \strtolower($matches[1]))) {
            $callable = [$isClass, \strtolower($matches[1])];
            return $callable($args[0]);
        }
        foreach ($utilClasses as $class) {
            if (\method_exists($class, $method)) {
                return \call_user_func_array([$class, $method], $args);
            }
        }
        throw new BadMethodCallException(\sprintf(
            'Undefined method: %s::%s()',
            \get_called_class(),
            $method
        ));
    }

    /**
     * Appends fulfillment and rejection handlers to the promise, and returns
     * a new promise resolving to the return value of the called handler.
     *
     * @param callable|null $onFulfilled Invoked when the promise fulfills.
     * @param callable|null $onRejected  Invoked when the promise is rejected.
     *
     * @return Promise
     */
    public function then($onFulfilled = null, $onRejected = null)
    {
        \bdk\Promise\Utils::assertType($onFulfilled, 'callable');
        \bdk\Promise\Utils::assertType($onRejected, 'callable');

        if ($this->state === self::PENDING) {
            $promise = new static(null, [$this, 'cancel']);
            $this->handlers[] = [$promise, $onFulfilled, $onRejected];
            $promise->waitList = $this->waitList;
            $promise->waitList[] = $this;
            return $promise;
        }

        // Return a fulfilled promise and immediately invoke any callbacks.
        if ($this->state === self::FULFILLED) {
            $promise = self::promiseFor($this->result);
            return $onFulfilled
                ? $promise->then($onFulfilled)
                : $promise;
        }

        // It's either cancelled or rejected, so return a rejected promise
        // and immediately invoke any callbacks.
        $rejection = self::rejectionFor($this->result);
        return $onRejected
            ? $rejection->then(null, $onRejected)
            : $rejection;
    }

    /**
     * Appends a rejection handler callback to the promise, and returns a new
     * promise resolving to the return value of the callback if it is called,
     * or to its original fulfillment value if the promise is instead
     * fulfilled.
     *
     * @param callable $onRejected Invoked when the promise is rejected.
     *
     * @return static
     */
    public function otherwise(callable $onRejected)
    {
        return $this->then(null, $onRejected);
    }

    /**
     * Waits until the promise completes if possible.
     *
     * Pass $unwrap as true to unwrap the result of the promise, either
     * returning the resolved value or throwing the rejected exception.
     *
     * If the promise cannot be waited on, then the promise will be rejected.
     *
     * @param bool $unwrap whether to return the fulfilled value
     *
     * @return mixed
     *
     * @throws LogicException if the promise has no wait function or if the
     *                         promise does not settle after waiting.
     */
    public function wait($unwrap = true)
    {
        $this->waitIfPending();
        if ($this->result instanceof PromiseInterface) {
            return $this->result->wait($unwrap);
        }
        if ($unwrap) {
            if ($this->state === self::FULFILLED) {
                return $this->result;
            }
            // It's rejected so throw an exception.
            throw self::exceptionFor($this->result);
        }
    }

    /**
     * Get the state of the promise ("pending", "rejected", or "fulfilled").
     *
     * The three states can be checked against the constants defined on
     * PromiseInterface: PENDING, FULFILLED, and REJECTED.
     *
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Cancels the promise if possible.
     *
     * @link https://github.com/promises-aplus/cancellation-spec/issues/7
     *
     * @return void
     */
    public function cancel()
    {
        if ($this->state !== self::PENDING) {
            return;
        }

        $this->waitFn = null;
        $this->waitList = array();

        if ($this->cancelFn) {
            $cancelFn = $this->cancelFn;
            $this->cancelFn = null;
            try {
                $cancelFn();
            } catch (Throwable $e) {
                $this->reject($e);
            } catch (Exception $e) {
                $this->reject($e);
            }
        }

        // Reject the promise only if it wasn't rejected in a then callback.
        /** @psalm-suppress RedundantCondition */
        if ($this->state === self::PENDING) {
            $this->reject(new CancellationException('Promise has been cancelled'));
        }
    }

    /**
     * Resolve the promise with the given value.
     *
     * @param mixed $value resolve value
     *
     * @return void
     *
     * @throws RuntimeException if the promise is already resolved.
     */
    public function resolve($value)
    {
        $this->settle(self::FULFILLED, $value);
    }

    /**
     * Reject the promise with the given reason.
     *
     * @param mixed $reason reject reason
     *
     * @return void
     *
     * @throws RuntimeException if the promise is already resolved.
     */
    public function reject($reason)
    {
        $this->settle(self::REJECTED, $reason);
    }

    /**
     * Resolve/reject the promise
     *
     * @param string $state self::FULFILLED OR self::REJECTED
     * @param mixed  $value resolve value or reject reason
     *
     * @return void
     *
     * @throws LogicException
     */
    private function settle($state, $value)
    {
        if ($state === $this->state && $value === $this->result) {
            // Ignore calls with the same resolution.
            return;
        }
        if ($this->state !== self::PENDING) {
            throw $this->state === $state
                ? new LogicException('The promise is already ' . $state . '.')
                : new LogicException('Cannot change a ' . $this->state . ' promise to ' . $state);
        }
        if ($value === $this) {
            throw new LogicException('Cannot fulfill or reject a promise with itself');
        }

        // Clear out the state of the promise but stash the handlers.
        $handlers = $this->handlers;
        $this->state = $state;
        $this->result = $value;
        $this->handlers = array();
        $this->waitList = array();
        $this->waitFn = null;
        $this->cancelFn = null;

        if ($handlers) {
            $this->settleInvokeHandlers($state, $value, $handlers);
        }
    }

    /**
     * Invoke or merge our handlers
     *
     * @param string $state    self::FULFILLED OR self::REJECTED
     * @param mixed  $value    resolve value or reject reason
     * @param array  $handlers list of Promise, onFulfilled, onRejected
     *
     * @return void
     */
    private function settleInvokeHandlers($state, $value, $handlers)
    {
        /*
            could optimize.. if $value instance of self (not extended from)
            then We can just merge our handlers onto the next promise.
            $value->handlers = \array_merge($value->handlers, $handlers);
        */
        $isThenable = \is_object($value) && \method_exists($value, 'then');
        if ($isThenable) {
            // value is some other thenable implementation
            //   invoke our handlers when value is resolved.
            $value->then(
                static function ($value) use ($handlers) {
                    self::invokeHandlers($handlers, 1, $value);
                },
                static function ($reason) use ($handlers) {
                    self::invokeHandlers($handlers, 2, $reason);
                }
            );
            return;
        }

        // not thenable.. resolve the handlers in the task queue.
        $index = $state === self::FULFILLED ? 1 : 2;
        self::queue()->add(static function () use ($handlers, $index, $value) {
            self::invokeHandlers($handlers, $index, $value);
        });
    }

    /**
     * Invoke promise's onFulfilled or onReject callbacks with value
     *
     * @param array $handlers promise, onFulfilled, onRejected
     * @param int   $index    1 (resolve) or 2 (reject).
     * @param mixed $value    Value to pass to the callback.
     *
     * @return void
     */
    private static function invokeHandlers(array $handlers, $index, $value)
    {
        foreach ($handlers as $handler) {
            self::invokeHandler($handler, $index, $value);
        }
    }

    /**
     * Invoke promise's onFulfilled or onReject callback with value
     *
     * @param array $handler promise, onFulfilled, onRejected
     * @param int   $index   1 (resolve) or 2 (reject).
     * @param mixed $value   Value to pass to the callback.
     *
     * @return void
     */
    private static function invokeHandler(array $handler, $index, $value)
    {
        /** @var PromiseInterface */
        $promise = $handler[0];

        // The promise may have been cancelled or resolved before placing this in the queue.
        if (self::isSettled($promise)) {
            return;
        }

        try {
            if (isset($handler[$index])) {
                /*
                    If $callable throws an exception, $handler will be in the exception stack trace.
                    Since $handler contains a reference to the callable itself we get a circular reference.
                    We clear the $handler here to avoid that memory leak.
                */
                $callable = $handler[$index];
                unset($handler);
                $promise->resolve($callable($value));
                return;
            }
            if ($index === 1) {
                // Forward resolution values as-is.
                $promise->resolve($value);
                return;
            }
            // Forward rejections down the chain.
            $promise->reject($value);
        } catch (Throwable $reason) {
            $promise->reject($reason);
        } catch (Exception $reason) {
            $promise->reject($reason);
        }
    }

    /**
     * Invoke the wait function
     *
     * @return void
     */
    private function waitIfPending()
    {
        if ($this->state !== self::PENDING) {
            return;
        }

        $haveWait = false;
        if ($this->waitFn) {
            $this->invokeWaitFn();
            $haveWait = true;
        } elseif ($this->waitList) {
            $this->invokeWaitList();
            $haveWait = true;
        }

        if (!$haveWait) {
            $this->reject('Cannot wait on a promise that has no wait function.'
                . ' You must provide a wait function when constructing the promise'
                . ' to be able to wait on it.');
        }

        self::queue()->run();

        /** @psalm-suppress RedundantCondition */
        if ($this->state === self::PENDING) {
            $this->reject('The wait callback did not resolve the promise');
        }
    }

    /**
     * Invoke the wait function
     *
     * @return void
     *
     * @throws Exception
     */
    private function invokeWaitFn()
    {
        $waitFn = $this->waitFn;
        $this->waitFn = null;
        try {
            $waitFn();
        } catch (Exception $e) {
            if ($this->state !== self::PENDING) {
                // The promise was already resolved,
                //   there's a problem in the application.
                throw $e;
            }
            // The promise has not been resolved yet,
            //   reject the promise with the exception.
            $this->reject($e);
        }
    }

    /**
     * Invoke the wait list
     *
     * @return void
     */
    private function invokeWaitList()
    {
        $waitList = $this->waitList;
        $this->waitList = array();
        foreach ($waitList as $result) {
            do {
                $result->waitIfPending();
                $result = $result->result;
            } while ($result instanceof self);

            if ($result instanceof PromiseInterface) {
                $result->wait(false);
            }
        }
    }
}

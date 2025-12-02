<?php

/**
 * @package   bdk/promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk;

use bdk\Promise\AbstractPromise;
use bdk\Promise\Exception\CancellationException;
use bdk\Promise\PromiseInterface;
use LogicException;

/**
 * Promises/A+ implementation that avoids recursion when possible.
 *
 * @link https://promisesaplus.com/
 *
 * @method bool isPending()
 * @method bool isSettled()
 * @method bool isFulfilled()
 * @method bool isRejected()
 * @method bool isThenable(mixed $value)
 *
 * @psalm-consistent-constructor
 */
class Promise extends AbstractPromise
{
    const TYPE_CALLABLE = 'callable|null';

    /**
     * @param callable|null $waitFn   Fn that when invoked resolves the promise.
     * @param callable|null $cancelFn Fn that when invoked cancels the promise.
     */
    public function __construct($waitFn = null, $cancelFn = null)
    {
        \bdk\Promise\Utils::assertType($waitFn, self::TYPE_CALLABLE, 'waitFn');
        \bdk\Promise\Utils::assertType($cancelFn, self::TYPE_CALLABLE, 'cancelFn');

        $this->waitFn = $waitFn;
        $this->cancelFn = $cancelFn;
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
        $cancelFn = $this->cancelFn;
        $this->cancelFn = null;
        $this->waitFn = null;
        $this->waitList = [];

        if ($cancelFn) {
            self::tryReject($this, $cancelFn);
        }

        // Reject the promise only if it is still pending
        if ($this->state === self::PENDING) {
            $this->reject(new CancellationException('Promise has been cancelled'));
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
        \bdk\Promise\Utils::assertType($onFulfilled, self::TYPE_CALLABLE, 'onFulfilled');
        \bdk\Promise\Utils::assertType($onRejected, self::TYPE_CALLABLE, 'onRejected');

        if ($this->state === self::PENDING) {
            $promise = new static(null, [$this, 'cancel']);
            $this->handlers[] = [$promise, $onFulfilled, $onRejected];
            $promise->waitList = $this->waitList;
            $promise->waitList[] = $this;
            return $promise;
        }

        if ($this->state === self::FULFILLED) {
            $promise = self::promiseFor($this->result);
            return $onFulfilled
                ? $promise->then($onFulfilled)
                : $promise;
        }

        $promise = self::rejectionFor($this->result);
        return $onRejected
            ? $promise->then(null, $onRejected)
            : $promise;
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
}

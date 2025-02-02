<?php

/**
 * @package   bdk\promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise;

/**
 * A promise represents the eventual result of an asynchronous operation.
 *
 * The primary way of interacting with a promise is through its then method,
 * which registers callbacks to receive either a promise’s eventual value or
 * the reason why the promise cannot be fulfilled.
 *
 * @link https://promisesaplus.com/
 */
interface PromiseInterface
{
    const PENDING = 'pending';
    const FULFILLED = 'fulfilled';
    const REJECTED = 'rejected';

    /**
     * Appends fulfillment and rejection handlers to the promise, and returns
     * a new promise resolving to the return value of the called handler.
     *
     * @param callable|null $onFulfilled Invoked when the promise fulfills.
     * @param callable|null $onRejected  Invoked when the promise is rejected.
     *
     * @return PromiseInterface
     */
    public function then($onFulfilled = null, $onRejected = null);

    /**
     * Appends a rejection handler callback to the promise, and returns a new
     * promise resolving to the return value of the callback if it is called,
     * or to its original fulfillment value if the promise is instead
     * fulfilled.
     *
     * @param callable $onRejected Invoked when the promise is rejected.
     *
     * @return PromiseInterface
     */
    public function otherwise(callable $onRejected);

    /**
     * Get the state of the promise ("pending", "rejected", or "fulfilled").
     *
     * The three states can be checked against the constants defined on
     * PromiseInterface: PENDING, FULFILLED, and REJECTED.
     *
     * @return string
     */
    public function getState();

    /**
     * Resolve the promise with the given value.
     *
     * @param mixed $value Value promise should return
     *
     * @return void
     *
     * @throws \RuntimeException if the promise is already resolved.
     */
    public function resolve($value);

    /**
     * Reject the promise with the given reason.
     *
     * @param mixed $reason reason value / message / exception
     *
     * @return void
     *
     * @throws \RuntimeException if the promise is already resolved.
     */
    public function reject($reason);

    /**
     * Cancels the promise if possible.
     *
     * @link https://github.com/promises-aplus/cancellation-spec/issues/7
     *
     * @return void
     */
    public function cancel();

    /**
     * Waits until the promise completes if possible.
     *
     * If the promise cannot be waited on, then the promise will be rejected.
     *
     * @param bool $unwrap (true) unwrap the result of the promise, either returning
     *                     the resolved value or throwing the rejected exception.
     *
     * @return mixed
     *
     * @throws \LogicException if the promise has no wait function or if the
     *                         promise does not settle after waiting.
     */
    public function wait($unwrap = true);
}

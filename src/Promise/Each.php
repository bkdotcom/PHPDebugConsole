<?php

declare(strict_types=1);

namespace bdk\Promise;

use bdk\Promise\EachPromise;
use bdk\Promise\PromiseInterface;

/**
 * Utilities for iterating over promises
 */
final class Each
{
    /**
     * Given an iterator that yields promises or values, returns a promise that
     * is fulfilled with a null value when the iterator has been consumed or
     * the aggregate promise has been fulfilled or rejected.
     *
     * @param mixed    $iterable    Iterator or array to iterate over.
     * @param callable $onFulfilled callable that accepts the fulfilled value, iterator
     *                         index, and the aggregate promise. The callback can invoke any necessary
     *                         side effects and choose to resolve or reject the aggregate if needed.
     * @param callable $onRejected  that accepts the rejection reason, iterator
     *                         index, and the aggregate promise. The callback can invoke any necessary
     *                         side effects and choose to resolve or reject the aggregate if needed.
     *
     * @return PromiseInterface
     *
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    public static function of($iterable, callable $onFulfilled = null, callable $onRejected = null)
    {
        return (new EachPromise($iterable, array(
            'fulfilled' => $onFulfilled,
            'rejected'  => $onRejected,
        )))->promise();
    }

    /**
     * Like of, but only allows a certain number of outstanding promises at any
     * given time.
     *
     * $concurrency may be an
     *
     * @param mixed        $iterable    Promises or values to iterate.
     * @param int|callable $concurrency Integer or a function that accepts the number of
     *                                    pending promises and returns a numeric concurrency limit
     *                                    value to allow for dynamic a concurrency size.
     * @param callable     $onFulfilled Invoked when a promise fulfills
     * @param callable     $onRejected  Invoked when a promise is rejected
     *
     * @return PromiseInterface
     */
    public static function ofLimit(
        $iterable,
        $concurrency,
        callable $onFulfilled = null,
        callable $onRejected = null
    )
    {
        return (new EachPromise($iterable, array(
            'concurrency' => $concurrency,
            'fulfilled'   => $onFulfilled,
            'rejected'    => $onRejected,
        )))->promise();
    }

    /**
     * Like limit, but ensures that no promise in the given $iterable argument
     * is rejected. If any promise is rejected, then the aggregate promise is
     * rejected with the encountered rejection.
     *
     * @param mixed        $iterable    Promises or values to iterate.
     * @param int|callable $concurrency Integer or a function that accepts the number of
     *                                    pending promises and returns a numeric concurrency limit
     *                                    value to allow for dynamic a concurrency size.
     * @param callable     $onFulfilled Invoked when a promise fulfills
     *
     * @return PromiseInterface
     */
    public static function ofLimitAll(
        $iterable,
        $concurrency,
        callable $onFulfilled = null
    )
    {
        return self::ofLimit(
            $iterable,
            $concurrency,
            $onFulfilled,
            // @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
            static function ($reason, $index, PromiseInterface $aggregate) {
                $aggregate->reject($reason);
            }
        );
    }
}

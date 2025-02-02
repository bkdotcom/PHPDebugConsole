<?php

/**
 * @package   bdk\promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise;

use ArrayIterator;
use bdk\Promise;
use bdk\Promise\Exception\RejectionException;
use bdk\Promise\PromiseInterface;
use Exception;
use Iterator;
use Throwable;

/**
 * Internal Helper methods
 */
final class Create
{
    /**
     * Creates a promise for a value if the value is not a promise.
     *
     * @param mixed $value Promise or value.
     *
     * @return PromiseInterface
     */
    public static function promiseFor($value)
    {
        if ($value instanceof PromiseInterface) {
            return $value;
        }

        $isThenable = \is_object($value) && \method_exists($value, 'then');
        if ($isThenable === false) {
            return new FulfilledPromise($value);
        }

        // Return a new promise that shadows the given thenable.
        $waitFn = \method_exists($value, 'wait') ? [$value, 'wait'] : null;
        $cancelFn = \method_exists($value, 'cancel') ? [$value, 'cancel'] : null;
        $promise = new Promise($waitFn, $cancelFn);
        $value->then([$promise, 'resolve'], [$promise, 'reject']);
        return $promise;
    }

    /**
     * Creates a rejected promise for a reason if the reason is not a promise.
     * If the provided reason is a promise, then it is returned as-is.
     *
     * @param mixed $reason Promise or reason.
     *
     * @return PromiseInterface
     */
    public static function rejectionFor($reason)
    {
        if ($reason instanceof PromiseInterface) {
            return $reason;
        }
        return new RejectedPromise($reason);
    }

    /**
     * Create an exception for a rejected promise value.
     *
     * @param mixed $reason Exception or Reason
     *
     * @return Exception|Throwable
     */
    public static function exceptionFor($reason)
    {
        return $reason instanceof Exception || $reason instanceof Throwable
            ? $reason
            : new RejectionException($reason);
    }

    /**
     * Returns an iterator for the given value.
     *
     * @param mixed $value Value to Iterator-ify
     *
     * @return Iterator
     */
    public static function iteratorFor($value)
    {
        if ($value instanceof Iterator) {
            return $value;
        }

        if (\is_array($value)) {
            return new ArrayIterator($value);
        }

        return new ArrayIterator([$value]);
    }
}

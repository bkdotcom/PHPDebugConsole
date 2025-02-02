<?php

/**
 * @package   bdk\promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise;

use bdk\Promise;
use Exception;
use Throwable;

/**
 * Represents a promise that iterates over many promises and invokes
 * side-effect functions in the process.
 */
class EachPromise extends Promise
{
    /** @var Promise|null */
    private $aggregate;

    /** @var callable|int|null */
    private $concurrency;

    /** @var \Iterator|null */
    private $iterable;

    /** @var bool|null */
    private $mutex;

    /** @var int */
    private $nextPendingIndex = 0;

    /** @var callable|null */
    private $onFulfilled;

    /** @var callable|null */
    private $onRejected;

    /** @var array<int,Promise> */
    private $pending = array();

    /**
     * Configuration hash can include the following key value pairs:
     *
     * - fulfilled: (callable) Invoked when a promise fulfills. The function
     *   is invoked with three arguments: the fulfillment value, the index
     *   position from the iterable list of the promise, and the aggregate
     *   promise that manages all of the promises. The aggregate promise may
     *   be resolved from within the callback to short-circuit the promise.
     * - rejected: (callable) Invoked when a promise is rejected. The
     *   function is invoked with three arguments: the rejection reason, the
     *   index position from the iterable list of the promise, and the
     *   aggregate promise that manages all of the promises. The aggregate
     *   promise may be resolved from within the callback to short-circuit
     *   the promise.
     * - concurrency: (integer) Pass this configuration option to limit the
     *   allowed number of outstanding concurrently executing promises,
     *   creating a capped pool of promises. There is no limit by default.
     *
     * @param mixed $iterable Promises or values to iterate.
     * @param array $config   Configuration options
     */
    public function __construct($iterable, array $config = array())
    {
        $this->iterable = self::iteratorFor($iterable);

        if (isset($config['concurrency'])) {
            $this->concurrency = $config['concurrency'];
        }

        if (isset($config['fulfilled'])) {
            $this->onFulfilled = $config['fulfilled'];
        }

        if (isset($config['rejected'])) {
            $this->onRejected = $config['rejected'];
        }
    }

    /**
     * @return Promise
     *
     * @psalm-suppress InvalidNullableReturnType
     */
    public function promise()
    {
        if ($this->aggregate) {
            return $this->aggregate;
        }

        try {
            $this->createPromise();
            /** @psalm-assert Promise $this->aggregate */
            $this->iterable->rewind();
            $this->refillPending();
        } catch (Throwable $e) {
            $this->aggregate->reject($e);
        } catch (Exception $e) {
            $this->aggregate->reject($e);
        }

        /**
         * @psalm-suppress NullableReturnStatement
         *
         * @phpstan-ignore-next-line
         */
        return $this->aggregate;
    }

    /**
     * @return bool
     */
    private function addPending()
    {
        if (!$this->iterable || !$this->iterable->valid()) {
            return false;
        }

        $promise = self::promiseFor($this->iterable->current());
        $key = $this->iterable->key();

        // Iterable keys may not be unique, so we use a counter to
        // guarantee uniqueness
        $index = $this->nextPendingIndex++;

        $this->pending[$index] = $promise->then(
            function ($value) use ($index, $key) {
                if ($this->onFulfilled) {
                    \call_user_func($this->onFulfilled, $value, $key, $this->aggregate);
                }
                $this->step($index);
            },
            function ($reason) use ($index, $key) {
                if ($this->onRejected) {
                    \call_user_func($this->onRejected, $reason, $key, $this->aggregate);
                }
                $this->step($index);
            }
        );

        return true;
    }

    /**
     * @return bool
     */
    private function advanceIterator()
    {
        // Place a lock on the iterator so that we ensure to not recurse,
        // preventing fatal generator errors.
        if ($this->mutex) {
            return false;
        }

        $this->mutex = true;

        try {
            $this->iterable->next();
            $this->mutex = false;
            return true;
        } catch (Throwable $e) {
            $this->aggregate->reject($e);
            $this->mutex = false;
            return false;
        } catch (Exception $e) {
            $this->aggregate->reject($e);
            $this->mutex = false;
            return false;
        }
    }

    /**
     * @return bool
     */
    private function checkIfFinished()
    {
        if (!$this->pending && !$this->iterable->valid()) {
            // Resolve the promise if there's nothing left to do.
            $this->aggregate->resolve(null);
            return true;
        }
        return false;
    }

    /**
     * @return void
     */
    private function createPromise()
    {
        $this->mutex = false;
        $this->aggregate = new Promise(function () {
            if ($this->checkIfFinished()) {
                return;
            }
            \reset($this->pending);
            // Consume a potentially fluctuating list of promises while
            // ensuring that indexes are maintained (precluding array_shift).
            while ($promise = \current($this->pending)) {
                \next($this->pending);
                $promise->wait();
                if ($this->aggregate->isSettled()) {
                    return;
                }
            }
        });

        // Clear the references when the promise is resolved.
        $clearFn = function () {
            $this->concurrency = null;
            $this->iterable = null;
            $this->nextPendingIndex = 0;
            $this->onFulfilled = null;
            $this->onRejected = null;
            $this->pending = null;
        };

        $this->aggregate->then($clearFn, $clearFn);
    }

    /**
     * @return void
     */
    private function refillPending()
    {
        if (!$this->concurrency) {
            // Add all pending promises.
            while ($this->addPending() && $this->advanceIterator());
            return;
        }

        // Add only up to N pending promises.
        $concurrency = \is_callable($this->concurrency)
            ? \call_user_func($this->concurrency, \count($this->pending))
            : $this->concurrency;
        $concurrency = \max($concurrency - \count($this->pending), 0);
        // Concurrency may be set to 0 to disallow new promises.
        if (!$concurrency) {
            return;
        }
        // Add the first pending promise.
        $this->addPending();
        // Note this is special handling for concurrency=1 so that we do
        // not advance the iterator after adding the first promise. This
        // helps work around issues with generators that might not have the
        // next value to yield until promise callbacks are called.
        while (
            --$concurrency
            && $this->advanceIterator()
            && $this->addPending()
        );
    }

    /**
     * Remove promise from pending and refill
     *
     * @param int $index pending index
     *
     * @return void
     */
    private function step($index)
    {
        // If the promise was already resolved, then ignore this step.
        if ($this->aggregate->isSettled()) {
            return;
        }

        unset($this->pending[$index]);

        // Only refill pending promises if we are not locked, preventing the
        // EachPromise to recursively invoke the provided iterator, which
        // cause a fatal error: "Cannot resume an already running generator"
        if ($this->advanceIterator() && !$this->checkIfFinished()) {
            // Add more pending promises if possible.
            $this->refillPending();
        }
    }
}

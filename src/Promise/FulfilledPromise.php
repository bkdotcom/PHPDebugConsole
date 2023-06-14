<?php

namespace bdk\Promise;

use bdk\Promise;
use Exception;
use InvalidArgumentException;
use Throwable;

/**
 * A promise that has been fulfilled.
 *
 * Thening off of this promise will invoke the onFulfilled callback
 * immediately and ignore other callbacks.
 */
class FulfilledPromise extends Promise // implements PromiseInterface
{
    /**
     * Constructor
     *
     * @param mixed $value Resolved value
     *
     * @throws InvalidArgumentException
     */
    public function __construct($value)
    {
        if (\is_object($value) && \method_exists($value, 'then')) {
            throw new InvalidArgumentException(
                'You cannot create a FulfilledPromise with a promise.'
            );
        }

        $this->result = $value;
        $this->state = self::FULFILLED;
    }

    /**
     * {@inheritDoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        // Return self if there is no onFulfilled function.
        if (!$onFulfilled) {
            return $this;
        }

        $queue = self::queue();
        $result = $this->result;
        $promise = new Promise(array($queue, 'run'));
        $queue->add(static function () use ($promise, $result, $onFulfilled) {
            if ($promise->isSettled()) {
                return;
            }
            try {
                // Return a resolved promise if onFulfilled does not throw.
                $promise->resolve($onFulfilled($result));
            } catch (Throwable $e) {
                // onFulfilled threw, so return a rejected promise.
                $promise->reject($e);
            } catch (Exception $e) {
                // onFulfilled threw, so return a rejected promise.
                $promise->reject($e);
            }
        });

        return $promise;
    }
}

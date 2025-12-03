<?php

namespace bdk\Promise;

use bdk\Promise;
use bdk\Promise\Is;
use InvalidArgumentException;

/**
 * Base class for FulfilledPromise and RejectedPromise
 */
abstract class AbstractResolvedPromise extends Promise
{
    /**
     * Constructor
     *
     * @param mixed $value Resolved value
     *
     * @throws InvalidArgumentException
     */
    public function __construct($state, $result)
    {
        if (Is::thenable($result)) {
            $classStr = \str_replace(__NAMESPACE__ . '\\', '', \get_called_class());
            throw new InvalidArgumentException(\sprintf(
                'You cannot create a %s with a promise.',
                $classStr
            ));
        }

        $this->result = $result;
        $this->state = $state;
    }

    /**
     * {@inheritDoc}
     */
    public function then($onFulfilled = null, $onRejected = null)
    {
        \bdk\Promise\Utils::assertType($onFulfilled, self::TYPE_CALLABLE, 'onFulfilled');
        \bdk\Promise\Utils::assertType($onRejected, self::TYPE_CALLABLE, 'onRejected');

        if ($this->state === self::FULFILLED && $onFulfilled) {
            return $this->addQueuedCallback($onFulfilled);
        }
        if ($this->state === self::REJECTED && $onRejected) {
            return $this->addQueuedCallback($onRejected);
        }
        return $this;
    }

    /**
     * Helper method for FulfilledPromise and RejectedPromise
     *
     * @param callable $callback onFulfilled or onRejected callback
     *
     * @return Promise
     */
    private function addQueuedCallback(callable $callback)
    {
        $queue = self::queue();
        $result = $this->result;
        $promise = new Promise([$queue, 'run']);
        $queue->add(static function () use ($promise, $result, $callback) {
            self::tryReject($promise, static function () use ($promise, $result, $callback) {
                $promise->resolve($callback($result));
            });
        });
        return $promise;
    }
}

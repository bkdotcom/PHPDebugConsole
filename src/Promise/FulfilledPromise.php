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
    public function then($onFulfilled = null, $onRejected = null)
    {
        \bdk\Promise\Utils::assertType($onFulfilled, 'callable');
        \bdk\Promise\Utils::assertType($onRejected, 'callable');

        // Return self if there is no onFulfilled function.
        if (!$onFulfilled) {
            return $this;
        }

        $queue = self::queue();
        $result = $this->result;
        $promise = new Promise([$queue, 'run']);
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

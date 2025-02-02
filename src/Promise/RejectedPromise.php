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
 * A promise that has been rejected.
 *
 * Thening off of this promise will invoke the onRejected callback
 * immediately and ignore other callbacks.
 */
class RejectedPromise extends Promise
{
    /**
     * Constructor
     *
     * @param mixed $reason rejection reason
     *
     * @throws InvalidArgumentException
     */
    public function __construct($reason)
    {
        if (\is_object($reason) && \method_exists($reason, 'then')) {
            throw new InvalidArgumentException(
                'You cannot create a RejectedPromise with a promise.'
            );
        }
        $this->result = $reason;
        $this->state = self::REJECTED;
    }

    /**
     * {@inheritDoc}
     */
    public function then($onFulfilled = null, $onRejected = null)
    {
        \bdk\Promise\Utils::assertType($onFulfilled, 'callable');
        \bdk\Promise\Utils::assertType($onRejected, 'callable');

        // Return self if there is no onRejected function.
        if (!$onRejected) {
            return $this;
        }
        [$onFulfilled]; // suppress unused

        $queue = self::queue();
        $result = $this->result;
        $promise = new Promise([$queue, 'run']);
        $queue->add(static function () use ($promise, $result, $onRejected) {
            if ($promise->isSettled()) {
                return;
            }
            try {
                // Return a resolved promise if onRejected does not throw.
                $promise->resolve($onRejected($result));
            } catch (Throwable $e) {
                // onRejected threw, so return a rejected promise.
                $promise->reject($e);
            } catch (Exception $e) {
                // onRejected threw, so return a rejected promise.
                $promise->reject($e);
            }
        });

        return $promise;
    }
}

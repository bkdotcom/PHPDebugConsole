<?php

/**
 * @package   bdk/promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise;

use bdk\Promise;
use bdk\Promise\Is;
use InvalidArgumentException;

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
        if (Is::thenable($reason)) {
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
        \bdk\Promise\Utils::assertType($onFulfilled, 'callable|null', 'onFulfilled');
        \bdk\Promise\Utils::assertType($onRejected, 'callable|null', 'onRejected');

        // Return self if there is no onRejected function.
        return $onRejected
            ? $this->addQueuedCallback($onRejected)
            : $this;
    }
}

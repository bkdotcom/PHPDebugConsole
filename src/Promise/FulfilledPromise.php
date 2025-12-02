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
 * A promise that has been fulfilled.
 *
 * Thening off of this promise will invoke the onFulfilled callback
 * immediately and ignore other callbacks.
 */
class FulfilledPromise extends Promise
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
        if (Is::thenable($value)) {
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
        \bdk\Promise\Utils::assertType($onFulfilled, 'callable|null', 'onFulfilled');
        \bdk\Promise\Utils::assertType($onRejected, 'callable|null', 'onRejected');

        // Return self if there is no onFulfilled function.
        return $onFulfilled
            ? $this->addQueuedCallback($onFulfilled)
            : $this;
    }
}

<?php

/**
 * @package   bdk/promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise;

use bdk\Promise\AbstractResolvedPromise;
use InvalidArgumentException;

/**
 * A promise that has been fulfilled.
 *
 * Thening off of this promise will invoke the onFulfilled callback
 * immediately and ignore other callbacks.
 */
class FulfilledPromise extends AbstractResolvedPromise
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
        parent::__construct(self::FULFILLED, $value);
    }
}

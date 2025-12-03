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
 * A promise that has been rejected.
 *
 * Thening off of this promise will invoke the onRejected callback
 * immediately and ignore other callbacks.
 */
class RejectedPromise extends AbstractResolvedPromise
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
        parent::__construct(self::REJECTED, $reason);
    }
}

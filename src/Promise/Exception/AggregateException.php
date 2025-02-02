<?php

/**
 * @package   bdk\promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise\Exception;

use bdk\Promise\Exception\RejectionException;

/**
 * Exception thrown when too many errors occur in the some() or any() methods.
 */
class AggregateException extends RejectionException
{
    /**
     * Constructor
     *
     * @param string $msg     Exception message
     * @param array  $reasons Reasons
     */
    public function __construct($msg, array $reasons)
    {
        parent::__construct(
            $reasons,
            \sprintf('%s; %d rejected promises', $msg, \count($reasons))
        );
    }
}

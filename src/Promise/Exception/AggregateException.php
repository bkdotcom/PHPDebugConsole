<?php

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

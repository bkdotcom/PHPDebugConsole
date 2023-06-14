<?php

namespace bdk\Promise\Exception;

use bdk\Promise\Exception\RejectionException;

/**
 * Exception that is set as the reason for a promise that has been cancelled.
 */
class CancellationException extends RejectionException
{
}

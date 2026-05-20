<?php

namespace bdk\Cache;

use LogicException;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * Thrown when invalid argument passed to SimpleCache method
 */
class InvalidArgumentException extends LogicException implements PsrInvalidArgumentException
{
}

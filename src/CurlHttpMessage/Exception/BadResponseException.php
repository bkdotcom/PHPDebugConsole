<?php

declare(strict_types=1);

namespace bdk\CurlHttpMessage\Exception;

use bdk\CurlHttpMessage\Exception\RequestException;

/**
 * Exception when an HTTP error occurs (4xx or 5xx error)
 */
class BadResponseException extends RequestException
{
}

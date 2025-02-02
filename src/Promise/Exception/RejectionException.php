<?php

/**
 * @package   bdk\promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise\Exception;

use JsonSerializable;
use RuntimeException;

/**
 * A special exception that is thrown when waiting on a rejected promise.
 *
 * The reason value is available via the getReason() method.
 */
class RejectionException extends RuntimeException
{
    /** @var mixed Rejection reason(s) */
    private $reason;

    /**
     * @param mixed  $reason      Rejection reason(s).
     * @param string $description Optional description
     */
    public function __construct($reason, $description = null)
    {
        $this->reason = $reason;

        $message = 'The promise was rejected';

        if ($description) {
            $message .= ' with reason: ' . $description;
        } elseif (
            \is_string($reason)
            || (\is_object($reason) && \method_exists($reason, '__toString'))
        ) {
            $message .= ' with reason: ' . $this->reason;
        } elseif ($reason instanceof JsonSerializable) {
            $message .= ' with reason: '
                . \json_encode($this->reason, JSON_PRETTY_PRINT);
        }

        parent::__construct($message);
    }

    /**
     * Returns the rejection reason(s).
     *
     * @return mixed
     */
    public function getReason()
    {
        return $this->reason;
    }
}

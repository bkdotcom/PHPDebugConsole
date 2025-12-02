<?php

/**
 * @package   bdk/promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise;

use bdk\Promise\PromiseInterface;

/**
 * State test methods
 */
final class Is
{
    /**
     * Returns true if a promise is pending.
     *
     * @param PromiseInterface $promise Promise to check
     *
     * @return bool
     */
    public static function pending(PromiseInterface $promise)
    {
        return $promise->getState() === PromiseInterface::PENDING;
    }

    /**
     * Returns true if a promise is fulfilled or rejected.
     *
     * @param PromiseInterface $promise Promise to check
     *
     * @return bool
     */
    public static function settled(PromiseInterface $promise)
    {
        return $promise->getState() !== PromiseInterface::PENDING;
    }

    /**
     * Returns true if a promise is fulfilled.
     *
     * @param PromiseInterface $promise Promise to check
     *
     * @return bool
     */
    public static function fulfilled(PromiseInterface $promise)
    {
        return $promise->getState() === PromiseInterface::FULFILLED;
    }

    /**
     * Returns true if a promise is rejected.
     *
     * @param PromiseInterface $promise Promise to check
     *
     * @return bool
     */
    public static function rejected(PromiseInterface $promise)
    {
        return $promise->getState() === PromiseInterface::REJECTED;
    }

    /**
     * Is the value an object with a 'then' method
     *
     * @param mixed $value Value to check
     *
     * @return bool
     */
    public static function thenable($value)
    {
        return \is_object($value) && \method_exists($value, 'then');
    }
}

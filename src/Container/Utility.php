<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.1
 */

namespace bdk\Container;

use bdk\Container;
use bdk\Container\ServiceProviderInterface;
use InvalidArgumentException;

/**
 * Container utilities
 */
class Utility
{
    /**
     * Get the container's raw values
     *
     * @param Container $container Container instance
     *
     * @return array
     */
    public static function getRawValues(Container $container)
    {
        $keys = $container->keys();
        $return = array();
        foreach ($keys as $key) {
            $return[$key] = $container->raw($key);
        }
        return $return;
    }

    /**
     * Get values from Container, ServiceProviderInterface, callable or plain array
     *
     * @param Container|ServiceProviderInterface|callable|array $val dependency definitions
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public static function toRawValues($val)
    {
        if ($val instanceof Container) {
            return self::getRawValues($val);
        }
        if ($val instanceof ServiceProviderInterface) {
            $container = new Container();
            $container->registerProvider($val);
            return self::getRawValues($container);
        }
        if (\is_callable($val)) {
            $container = new Container();
            \call_user_func($val, $container);
            return self::getRawValues($container);
        }
        if (\is_array($val)) {
            return $val;
        }
        throw new InvalidArgumentException(\sprintf(
            'toRawValues expects Container, ServiceProviderInterface, callable, or key->value array. %s provided',
            self::getDebugType($val)
        ));
    }

    /**
     * Assert that the identifier exists
     *
     * @param mixed $val Value to check
     *
     * @return void
     *
     * @throws InvalidArgumentException If the identifier is not defined
     */
    public static function assertInvokable($val)
    {
        if (\is_object($val) === false || \method_exists($val, '__invoke') === false) {
            throw new InvalidArgumentException(\sprintf(
                'Closure or invokable object expected.  %s provided',
                self::getDebugType($val)
            ));
        }
    }

    /**
     * Gets the type name of a variable in a way that is suitable for debugging
     *
     * @param mixed $value Value to inspect
     *
     * @return string
     */
    protected static function getDebugType($value)
    {
        return \is_object($value)
            ? \get_class($value)
            : \strtolower(\gettype($value));
    }
}

<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
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
        $msg = 'toRawValues expects Container, ServiceProviderInterface, callable, or key->value array. %s provided';
        $type = \is_object($val)
            ? \get_class($val)
            : \gettype($val);
        throw new InvalidArgumentException(\sprintf($msg, $type));
    }
}

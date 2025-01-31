<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Utility;

use ArrayAccess;
use bdk\Debug\Utility\Php;
use Countable;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Assertions and helpers for ArrayUtil
 */
trait ArrayUtilHelperTrait
{
    /**
     * Assert value is array or implements ArrayAccess
     *
     * @param mixed                       $value Value to test
     * @param array<array-key,string|int> $path  Path to value
     *
     * @return void
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     *
     * @psalm-assert array|ArrayAccess $value
     */
    private static function assertArrayAccess($value, array $path)
    {
        if (\is_array($value) || $value instanceof ArrayAccess) {
            return;
        }
        if ($path === []) {
            throw new InvalidArgumentException(\sprintf(
                'Array or ArrayAccess expected.  %s provided.',
                Php::getDebugType($value)
            ));
        }
        throw new UnexpectedValueException(\sprintf(
            '%s is not an array or does not implement ArrayAccess',
            \implode('.', $path)
        ));
    }

    /**
     * Assert value is array or implements ArrayAccess
     *
     * @param mixed                       $value Value to test
     * @param array<array-key,string|int> $path  Path to value
     *
     * @return void
     *
     * @throws UnexpectedValueException
     *
     * @psalm-assert array|Countable $value
     */
    private static function assertCountable($value, array $path)
    {
        if (\is_array($value) || $value instanceof Countable) {
            return;
        }
        throw new UnexpectedValueException(\sprintf(
            '%s (type of %s) is not an array or does not implement Countable',
            \implode('.', $path),
            Php::getDebugType($value)
        ));
    }

    /**
     * Compares 2 arrays
     *
     * @param array $array  Array to compare from
     * @param array $array2 Array to compare against
     *
     * @return array An array containing all the values from array that are not present in array2
     */
    private static function diffAssocRecursiveWalk(array $array, array $array2)
    {
        $diff = array();
        \array_walk(
            $array,
            /**
             * @param array-key $key
             */
            static function ($value, $key) use (&$diff, $array2) {
                if (\array_key_exists($key, $array2) === false) {
                    $diff[$key] = $value;
                    return;
                }
                if (\is_array($value) && \is_array($array2[$key])) {
                    $value = self::diffAssocRecursive($value, $array2[$key]);
                    if ($value) {
                        $diff[$key] = $value;
                    }
                    return;
                }
                if ($value !== $array2[$key]) {
                    $diff[$key] = $value;
                }
            }
        );
        return $diff;
    }

    /**
     * Remove values from array
     *
     * @param array $array  Array to modify
     * @param array $array2 Array values to remove
     *
     * @return array Array containing all the entries from array that are not present in array2
     */
    private static function diffStrictWalk(array $array, array $array2)
    {
        foreach ($array2 as $value) {
            $key = \array_search($value, $array, true);
            if ($key !== false) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     * Check that value is not an array
     *
     * @param mixed $value Value to test
     *
     * @return bool
     *
     * @psalm-assert-if-false array $value
     */
    private static function isNonMergeable($value)
    {
        return \is_array($value) === false || Php::isCallable($value, Php::IS_CALLABLE_ARRAY_ONLY);
    }

    /**
     * Is value a valid array key?
     *
     * @param mixed $val Value to test
     *
     * @return bool
     *
     * @psalm-assert-if-true array-key $val
     */
    private static function isValidKey($val)
    {
        $validTypes = ['string', 'int', 'float', 'bool', 'resource', 'null'];
        return \in_array(Php::getDebugType($val), $validTypes, true);
    }

    /**
     * Merge 2nd array into first
     *
     * @param array $arrayDef default array
     * @param array $array2   array 2
     *
     * @return array
     */
    private static function mergeDeepWalk(array $arrayDef, array $array2)
    {
        \array_walk(
            $array2,
            /**
             * @param array-key $key
             */
            static function ($value, $key) use (&$arrayDef) {
                if (self::isNonMergeable($value)) {
                    // not array or appears to be a callable
                    if (\is_int($key) === false) {
                        $arrayDef[$key] = $value;
                    } elseif (\in_array($value, $arrayDef, true) === false) {
                        // unique value -> append it
                        $arrayDef[] = $value;
                    }
                    return;
                }
                if (isset($arrayDef[$key]) === false || self::isNonMergeable($arrayDef[$key])) {
                    // default not set or can be overwritten without merge
                    $arrayDef[$key] = $value;
                    return;
                }
                // both values are arrays... merge em
                $arrayDef[$key] = static::mergeDeep($arrayDef[$key], $value);
            }
        );
        return $arrayDef;
    }

    /**
     * Cast path to array
     *
     * @param array|string|null $path path
     *
     * @return array<array-key,string|int>
     *
     * @throws InvalidArgumentException
     */
    private static function pathToArray($path)
    {
        if ($path === null) {
            return array();
        }
        if (\is_string($path)) {
            return \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        if (\is_array($path) === false) {
            throw new InvalidArgumentException(\sprintf(
                'Path must be string or list of string|int.  %s provided.',
                Php::getDebugType($path)
            ));
        }
        \array_walk($path, static function ($val) {
            if (\is_string($val) === false && \is_int($val) === false) {
                throw new InvalidArgumentException(\sprintf(
                    'Path array must consist only of string|int.  %s found.',
                    Php::getDebugType($val)
                ));
            }
        });
        /** @var array<array-key,string|int> */
        return $path;
    }

    /**
     * Handle special pathGet & pathSet keys
     *
     * Special keys
     *    handled:  __end__, __push__, __reset__
     *    not handled:  __count__, __pop__
     *
     * @param string|int        $key   path key to test
     * @param array<string|int> $path  the path
     * @param array|ArrayAccess $array the current array (or ArrayAccess)
     *
     * @return bool whether key was handled
     *
     * @throws UnexpectedValueException
     */
    private static function specialKey($key, array &$path, &$array)
    {
        if (\in_array($key, ['__end__', '__push__', '__reset__'], true) === false) {
            return false;
        }
        if (\is_array($array) === false) {
            throw new UnexpectedValueException(\sprintf(
                '%s can only be used on array value.  %s provided.',
                $key,
                Php::getDebugType($array)
            ));
        }
        switch ($key) {
            case '__end__':
                \end($array);
                $path[] = \key($array);
                return true;
            case '__push__':
                $array[] = array();
                $path[] = '__end__';
                return true;
            case '__reset__':
                \reset($array);
                $path[] = \key($array);
                return true;
        }
    }
}

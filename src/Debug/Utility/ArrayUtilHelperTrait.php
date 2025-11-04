<?php

/**
 * @package   bdk/debug
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
     * @param mixed                       $value  Value to test
     * @param array<array-key,string|int> $path   Path to value
     * @param string                      $method method doing the asserting
     *
     * @return void
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     *
     * @psalm-assert array|ArrayAccess $value
     */
    private static function assertArrayAccess($value, array $path, $method)
    {
        if (\is_array($value) || $value instanceof ArrayAccess) {
            return;
        }
        if ($path === []) {
            throw new InvalidArgumentException(\bdk\Debug\Utility::trans('exception.method-expects', array(
                'actual' => Php::getDebugType($value),
                'expect' => 'array or ArrayAccess',
                'method' => $method . '()',
            )));
        }
        throw new UnexpectedValueException(\bdk\Debug\Utility::trans('exception.method-expects-at', array(
            'actual' => Php::getDebugType($value),
            'expect' => 'array or ArrayAccess',
            'method' => $method . '()',
            'path' => \implode('.', $path),
        )));
    }

    /**
     * Assert value is array or implements ArrayAccess
     *
     * @param mixed                       $value  Value to test
     * @param array<array-key,string|int> $path   Path to value
     * @param string                      $method method doing the asserting
     *
     * @return void
     *
     * @throws UnexpectedValueException
     *
     * @psalm-assert array|Countable $value
     */
    private static function assertCountable($value, array $path, $method)
    {
        if (\is_array($value) || $value instanceof Countable) {
            return;
        }
        throw new UnexpectedValueException(\bdk\Debug\Utility::trans('exception.method-expects-at', array(
            'actual' => Php::getDebugType($value),
            'expect' => 'array or Countable',
            'method' => $method . '()',
            'path' => \implode('.', $path),
        )));
    }

    /**
     * Compares 2 arrays
     *
     * @param array $array  Array to compare from
     * @param array $array2 Array to compare against
     *
     * @return array An array containing all the values from array that are not present in array2
     */
    private static function diffDeepWalk(array $array, array $array2)
    {
        $diff = array();
        $allInt = true; // true if all kept keys are int
        $walkFunc = static function ($value, $key) use (&$allInt, &$diff, $array2) {
            $incl = false;
            if (\array_key_exists($key, $array2) === false) {
                $incl = true;
            } elseif (self::isMergeable($value) && self::isMergeable($array2[$key])) {
                $value = self::diffDeep($value, $array2[$key]);
                $incl = !empty($value);
            } elseif (\is_int($key)) {
                // integer key... keep value if not in array2 (or if in array2, but with string key / not a list)
                $foundIndex = \array_search($value, $array2, true);
                $incl = $foundIndex === false || \is_int($foundIndex) === false;
            } elseif ($value !== $array2[$key]) {
                // different value in array2
                $incl = true;
            }
            if ($incl) {
                $allInt = $allInt && \is_int($key);
                $diff[$key] = $value;
            }
        };
        \array_walk($array, $walkFunc);
        if ($allInt) {
            $diff = \array_values($diff);
        }
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
     * Check that value is an array (but not a "callable")
     *
     * @param mixed $value Value to test
     *
     * @return bool
     *
     * @psalm-assert-if-true array $value
     */
    private static function isMergeable($value)
    {
        return \is_array($value) && Php::isCallable($value, Php::IS_CALLABLE_ARRAY_ONLY) === false;
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
                if (self::isMergeable($value) === false) {
                    // not array or appears to be a callable
                    if (\is_int($key) === false) {
                        $arrayDef[$key] = $value;
                    } elseif (\in_array($value, $arrayDef, true) === false) {
                        // unique value -> append it
                        $arrayDef[] = $value;
                    }
                    return;
                }
                if (isset($arrayDef[$key]) === false || self::isMergeable($arrayDef[$key]) === false) {
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
        $frame = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        if (\is_array($path) === false) {
            throw new InvalidArgumentException(\bdk\Debug\Utility::trans('exception.method-expects-param', array(
                'actual' => Php::getDebugType($path),
                'expect' => 'string or list of string|int',
                'method' => $frame['class'] . '::' . $frame['function'] . '()',
                'param' => '$path',
            )));
        }
        \bdk\Debug\Utility\ArrayUtil::assertContainsOnly($path, 'string|int', 'path', $frame['class'] . '::' . $frame['function']);
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
     * @param string|int        $key    path key to test
     * @param array<string|int> $path   the path
     * @param array|ArrayAccess $array  the current array (or ArrayAccess)
     * @param string            $method calling method
     *
     * @return bool whether key was handled
     *
     * @throws UnexpectedValueException
     */
    private static function specialKey($key, array &$path, &$array, $method)
    {
        if (\in_array($key, ['__end__', '__push__', '__reset__'], true) === false) {
            return false;
        }
        if (\is_array($array) === false) {
            throw new UnexpectedValueException(\bdk\Debug\Utility::trans('exception.array-special-key', array(
                'actual' => Php::getDebugType($array),
                'key' => $key,
                'method' => $method . '()',
            )));
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

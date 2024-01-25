<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\Php;

/**
 * Array Utilities
 */
class ArrayUtil
{
    /**
     * "dereference" array
     * returns a copy of the array with references removed
     *
     * @param array $source source array
     * @param bool  $deep   (true) deep copy
     *
     * @return array
     */
    public static function copy(array $source, $deep = true)
    {
        $arr = array();
        foreach ($source as $key => $val) {
            if ($deep && \is_array($val)) {
                $arr[$key] = self::copy($val);
                continue;
            }
            $arr[$key] = $val;
        }
        return $arr;
    }

    /**
     * Recursively compare arrays
     *
     * Returns an array containing all the values from array that are not present in any of the other arrays.
     *
     * @param array $array     array to compare from
     * @param array ...$arrays arrays to compare against
     *
     * @return array
     */
    public static function diffAssocRecursive(array $array, $arrays)
    {
        $arrays = \func_get_args();
        \array_shift($arrays);
        while ($arrays) {
            $array2 = \array_shift($arrays);
            $array = self::diffAssocRecursiveHelper($array, $array2);
        }
        return $array;
    }

    /**
     * Is passed argument a simple array with all-integer keys in sequence from 0 to n?
     * empty array returns true
     *
     * @param mixed $val value to check
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public static function isList($val)
    {
        if (\is_array($val) === false) {
            return false;
        }
        $i = -1;
        // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable
        foreach ($val as $k => $v) {
            ++$i;
            if ($k !== $i) {
                return false;
            }
        }
        return true;
    }

    /**
     * Applies the callback to all leafs of the given array
     *
     * @param callable $callback Callable to be applied
     * @param array    $input    Input array
     *
     * @return array
     */
    public static function mapRecursive($callback, array $input)
    {
        return \array_map(static function ($val) use ($callback) {
            return \is_array($val)
                ? self::mapRecursive($callback, $val)
                : $callback($val);
        }, $input);
    }

    /**
     * Recursively merge arrays
     *
     * @param array $arrayDef  default array
     * @param array ...$array2 array to merge
     *
     * @return array
     */
    public static function mergeDeep(array $arrayDef, $array2)
    {
        $mergeArrays = \func_get_args();
        \array_shift($mergeArrays);
        while ($mergeArrays) {
            $array2 = \array_shift($mergeArrays);
            $arrayDef = static::mergeDeepWalk($arrayDef, $array2);
        }
        return $arrayDef;
    }

    /**
     * Get value from array (or obj with array access)
     *
     * @param array        $array   array to traverse
     * @param array|string $path    key path
     *                              path may contain special keys:
     *                                * __count__ : return count() (traversal will cease)
     *                                * __end__ : last value
     *                                * __pop__ : pop value
     *                                * __reset__ : first value
     * @param mixed        $default default value
     *
     * @return mixed
     */
    public static function pathGet(array &$array, $path, $default = null)
    {
        $path = \array_reverse(self::pathToArray($path));
        while ($path) {
            $key = \array_pop($path);
            $arrayAccess = \is_array($array) || $array instanceof \ArrayAccess;
            if (!$arrayAccess) {
                return $default;
            } elseif (isset($array[$key])) {
                $array = &$array[$key];
                continue;
            } elseif ($key === '__count__') {
                return \count($array);
            } elseif ($key === '__pop__') {
                $arrayNew = \array_pop($array);
                $array = &$arrayNew;
                continue;
            } elseif (self::specialKey($key, $path, $array)) {
                continue;
            }
            return $default;
        }
        return $array;
    }

    /**
     * Update/Set an array value via "path"
     *
     * @param array        $array array to edit
     * @param array|string $path  path may contain special keys:
     *                                 * __end__ : last value
     *                                 * __push__ : append value
     *                                 * __reset__ : first value
     * @param mixed        $val   value to set
     *                                 * __unset__ to unset
     *
     * @return void
     */
    public static function pathSet(array &$array, $path, $val)
    {
        $path = \array_reverse(self::pathToArray($path));
        while ($path) {
            $key = \array_pop($path);
            if (self::specialKey($key, $path, $array)) {
                continue;
            } elseif ($val === '__unset__' && empty($path)) {
                unset($array[$key]);
                return;
            } elseif (!isset($array[$key]) || !\is_array($array[$key])) {
                $array[$key] = array(); // initialize this level
            }
            $array = &$array[$key];
        }
        $array = $val;
    }

    /**
     * Searches array structure for value and returns the path to the first match
     *
     * @param mixed $value    value to search for (needle)
     * @param array $array    array structure to search (haystack)
     * @param bool  $inclKeys (false) whether to also match keys
     *
     * @return array|false Returns empty array if value not found
     */
    public static function searchRecursive($value, array $array, $inclKeys = false)
    {
        $key = \array_search($value, $array, true);
        if ($key !== false) {
            return array($key);
        }
        if ($inclKeys && \array_key_exists($value, $array)) {
            return array($value);
        }
        foreach ($array as $key => $val) {
            if (\is_array($val) === false) {
                continue;
            }
            $pathTest = self::searchRecursive($value, $val, $inclKeys);
            if ($pathTest) {
                return \array_merge(array($key), $pathTest);
            }
        }
        return false;
    }

    /**
     * Sort array, using `$order`
     * Keys will be preserved
     *
     * @param array      $array Array to sort
     * @param array|null $order values that define order / should come first
     * @param string     $what  ("value") or "key" - Whether to sort sort by value or key
     *
     * @return void
     */
    public static function sortWithOrder(array &$array, $order = array(), $what = 'value')
    {
        $callback = static function ($valA, $valB) use ($order) {
            $aPos = \array_search($valA, $order, true);
            $bPos = \array_search($valB, $order, true);
            if ($aPos === $bPos) {
                return \strnatcasecmp($valA, $valB);
            }
            if ($aPos === false) {   // $a is a dont care
                return 1;            //   $a > $b
            }
            if ($bPos === false) {   // $b is a dont care
                return -1;           //   $a < $b
            }
            return $aPos < $bPos
                ? -1
                : 1;
        };
        if (empty($order)) {
            $callback = 'strnatcasecmp';
        }
        $what === 'value'
            ? \uasort($array, $callback)
            : \uksort($array, $callback);
    }

    /**
     * Much like `array_splice` but for associative arrays
     *
     * @param array  $array       The input array
     * @param string $key         "Offset" key.
     * @param int    $length      How many values to remove
     * @param mixed  $replacement replacement array
     *
     * @return array removed values
     */
    public static function spliceAssoc(array &$array, $key, $length = null, $replacement = array())
    {
        $offset = \array_search($key, \array_keys($array), true);
        $count = \count($array);
        if ($offset === false) {
            // merge replacemnet onto end of array
            $offset = $count;
            $length = 0;
        }
        if ($length === null) {
            $length = $count - $offset;
        } elseif ($length < 0) {
            $length = $count + $length - $offset;
            $length = \max($length, 0);
        }
        $ret = \array_slice($array, $offset, $length);
        $array = \array_merge(
            \array_slice($array, 0, $offset, true),
            (array) $replacement,
            \array_slice($array, $offset + $length, null, true)
        );
        return $ret;
    }

    /**
     * Compares 2 arrays
     *
     * @param array $array  Array to compare from
     * @param array $array2 Array to compare against
     *
     * @return array An array containing all the values from array that are not present in array2
     */
    private static function diffAssocRecursiveHelper(array $array, array $array2)
    {
        $diff = array();
        \array_walk($array, static function ($value, $key) use (&$diff, $array2) {
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
        });
        return $diff;
    }

    /**
     * Check that value is not an array
     *
     * @param mixed $value Value to test
     *
     * @return bool
     */
    private static function isNonMergeable($value)
    {
        return \is_array($value) === false || Php::isCallable($value, Php::IS_CALLABLE_ARRAY_ONLY);
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
        \array_walk($array2, static function ($value, $key) use (&$arrayDef) {
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
        });
        return $arrayDef;
    }

    /**
     * Cast path to array
     *
     * @param array|string $path path
     *
     * @return array
     */
    private static function pathToArray($path)
    {
        return \is_array($path)
            ? $path
            : \array_filter(\preg_split('#[\./]#', (string) $path), 'strlen');
    }

    /**
     * Handle special pathGet & pathSet keys
     *
     * Special keys
     *    handled:  __end__, __push__, __reset__
     *    not handled:  __count__, __pop__
     *
     * @param string $key   path key to test
     * @param array  $path  the path
     * @param array  $array the current array
     *
     * @return bool whether key was handled
     */
    private static function specialKey($key, array &$path, array &$array)
    {
        if ($key === '__end__') {
            \end($array);
            $path[] = \key($array);
            return true;
        }
        if ($key === '__push__') {
            $array[] = array();
            $path[] = '__end__';
            return true;
        }
        if ($key === '__reset__') {
            \reset($array);
            $path[] = \key($array);
            return true;
        }
        return false;
    }
}

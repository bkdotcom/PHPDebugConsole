<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility;

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
    public static function copy($source, $deep = true)
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
     * Is passed argument a simple array with all-integer keys in sequence from 0 to n?
     * empty array returns true
     *
     * @param mixed $val value to check
     *
     * @return bool
     */
    public static function isList($val)
    {
        if (!\is_array($val)) {
            return false;
        }
        // iterate over keys more efficient than `$val === array_values($val)`
        $keys = \array_keys($val);
        foreach ($keys as $i => $key) {
            if ($key !== $i) {
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
    public static function mapRecursive($callback, $input)
    {
        $return = array();
        foreach ($input as $key => $val) {
            if (\is_array($val)) {
                $return[$key] = self::mapRecursive($callback, $val);
                continue;
            }
            $return[$key] = $callback($val);
        }
        return $return;
    }

    /**
     * Recursively merge arrays
     *
     * @param array $arrayDef   default array
     * @param array $array2,... array to merge
     *
     * @return array
     */
    public static function mergeDeep($arrayDef, $array2)
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
     * Get value from array
     *
     * @param array        $array   array to traverse
     * @param array|string $path    key path
     *                              path may contain special keys:
     *                                * __count__ : return count() (traversal will cease)
     *                                * __end__ : last value
     *                                * __reset__ : first value
     * @param mixed        $default default value
     *
     * @return mixed
     */
    public static function pathGet($array, $path, $default = null)
    {
        if (!\is_array($path)) {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        $path = \array_reverse($path);
        while ($path) {
            $key = \array_pop($path);
            $arrayAccess = \is_array($array) || $array instanceof \ArrayAccess;
            if (!$arrayAccess) {
                return $default;
            }
            if (isset($array[$key])) {
                $array = $array[$key];
                continue;
            }
            if ($key === '__count__') {
                return \count($array);
            }
            if ($key === '__end__') {
                \end($array);
                $path[] = \key($array);
                continue;
            }
            if ($key === '__reset__') {
                \reset($array);
                $path[] = \key($array);
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
     *
     * @return void
     */
    public static function pathSet(&$array, $path, $val)
    {
        if (!\is_array($path)) {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        $path = \array_reverse($path);
        $ref = &$array;
        while ($path) {
            $key = \array_pop($path);
            if ($key === '__push__') {
                $ref[] = null;
                $key = '__end__';
            }
            if ($key === '__end__') {
                \end($ref);
                $path[] = \key($ref);
                continue;
            }
            if ($key === '__reset__') {
                \reset($ref);
                $path[] = \key($ref);
                continue;
            }
            if (!isset($ref[$key]) || !\is_array($ref[$key])) {
                $ref[$key] = array(); // initialize this level
            }
            $ref = &$ref[$key];
        }
        $ref = $val;
    }

    /**
     * Merge 2nd array into first
     *
     * @param array $arrayDef default array
     * @param array $array2   array 2
     *
     * @return array
     */
    private static function mergeDeepWalk($arrayDef, $array2)
    {
        foreach ($array2 as $k2 => $v2) {
            if (!\is_array($v2) || Utility::isCallable($v2)) {
                // not array or appears to be a callable
                if (\is_int($k2) === false) {
                    $arrayDef[$k2] = $v2;
                    continue;
                }
                // append int-key'd values if not already in_array
                if (\in_array($v2, $arrayDef)) {
                    // already in array
                    continue;
                }
                // append it
                $arrayDef[] = $v2;
                continue;
            }
            if (!isset($arrayDef[$k2]) || !\is_array($arrayDef[$k2]) || Utility::isCallable($arrayDef[$k2])) {
                $arrayDef[$k2] = $v2;
                continue;
            }
            // both values are arrays... merge em
            $arrayDef[$k2] = static::mergeDeep($arrayDef[$k2], $v2);
        }
        return $arrayDef;
    }
}

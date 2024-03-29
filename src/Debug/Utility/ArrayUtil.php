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

use ArrayAccess;
use bdk\Debug\Utility\ArrayUtilHelperTrait;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Array Utilities
 */
class ArrayUtil
{
    use ArrayUtilHelperTrait;

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
        /** @var mixed $val */
        foreach ($source as $key => $val) {
            if ($deep && \is_array($val)) {
                $arr[$key] = self::copy($val);
                continue;
            }
            /** @var mixed */
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
     *
     * @throws InvalidArgumentException
     */
    public static function diffAssocRecursive(array $array, $arrays)
    {
        $arrays = \func_get_args();
        \array_shift($arrays);
        while ($arrays) {
            $array2 = \array_shift($arrays);
            if (\is_array($array2) === false) {
                throw new InvalidArgumentException('diffAssocRecursive: non-array value passed');
            }
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
     *
     * @psalm-assert-if-true list<mixed> $val
     */
    public static function isList($val)
    {
        if (\is_array($val) === false) {
            return false;
        }
        $i = -1;
        /** @var mixed $v */
        foreach ($val as $k => $v) { // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable
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
     *
     * @throws InvalidArgumentException
     */
    public static function mergeDeep(array $arrayDef, $array2)
    {
        $mergeArrays = \func_get_args();
        \array_shift($mergeArrays);
        while ($mergeArrays) {
            $array2 = \array_shift($mergeArrays);
            if (\is_array($array2) === false) {
                throw new InvalidArgumentException('mergeDeep: non-array value passed');
            }
            $arrayDef = static::mergeDeepWalk($arrayDef, $array2);
        }
        return $arrayDef;
    }

    /**
     * Get value from array (or obj with array access)
     *
     * @param array|ArrayAccess $array   array to traverse
     * @param array|string|null $path    key path
     *                              path may contain special keys:
     *                                * __count__ : return count() (traversal will cease)
     *                                * __end__ : last value
     *                                * __pop__ : pop value
     *                                * __reset__ : first value
     * @param mixed             $default default value
     *
     * @return mixed
     *
     * @throws UnexpectedValueException
     */
    public static function pathGet(&$array, $path, $default = null)
    {
        $path = \array_reverse(self::pathToArray($path));
        $curPath = array();
        while ($path) {
            self::assertArrayAccess($array, $curPath);
            $key = \array_pop($path);
            $curPath[] = $key;
            if (isset($array[$key])) {
                $array = &$array[$key];
                continue;
            } elseif ($key === '__count__') {
                self::assertCountable($array, \array_slice($curPath, 0, -1));
                return \count($array);
            } elseif ($key === '__pop__') {
                /** @var mixed */
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
     * @param array             $array array to edit
     * @param array|string|null $path  path may contain special keys:
     *                                 * __end__ : last value
     *                                 * __push__ : append value
     *                                 * __reset__ : first value
     * @param mixed             $val   value to set
     *                                 * __unset__ to unset
     *
     * @return void
     */
    public static function pathSet(array &$array, $path, $val)
    {
        $path = \array_reverse(self::pathToArray($path));
        while ($path && \is_array($array)) {
            $key = \array_pop($path);
            if (self::specialKey($key, $path, $array)) {
                continue;
            } elseif ($val === '__unset__' && empty($path)) {
                unset($array[$key]);
                /** @psalm-suppress ReferenceConstraintViolation */
                return;
            } elseif (!isset($array[$key]) || !\is_array($array[$key])) {
                $array[$key] = array(); // initialize this level
            }
            $array = &$array[$key];
        }
        /** @var mixed */
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
        if ($inclKeys && self::isValidKey($value) && \array_key_exists($value, $array)) {
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
        if ($order === null) {
            $order = array();
        }
        $callback = static function ($valA, $valB) use ($order) {
            $aPos = \array_search($valA, $order, true);
            $bPos = \array_search($valB, $order, true);
            if ($aPos === $bPos) {
                return \strnatcasecmp($valA, $valB);
            }
            if ($aPos === false) {   // $a is a don't care
                return 1;            //   $a > $b
            }
            if ($bPos === false) {   // $b is a don't care
                return -1;           //   $a < $b
            }
            return $aPos < $bPos
                ? -1
                : 1;
        };
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
            // merge replacement onto end of array
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
}

<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Method;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;

/**
 * Table helper methods
 */
class Table
{

    const SCALAR = "\x00scalar\x00";

    /**
     * Go through all the "rows" of array to determine what the keys are and their order
     *
     * @param array $rows array (or traversable abstraction)
     *
     * @return array
     */
    public static function colKeys($rows)
    {
        if (Abstracter::isAbstraction($rows, 'object')) {
            if (!$rows['traverseValues']) {
                return array(self::SCALAR);
            }
            $rows = $rows['traverseValues'];
        }
        if (!\is_array($rows)) {
            return array();
        }
        $colKeys = array();
        $curRowKeys = array();
        foreach ($rows as $row) {
            $curRowKeys = self::keys($row);
            if (empty($colKeys)) {
                $colKeys = $curRowKeys;
            } elseif ($curRowKeys !== $colKeys) {
                $colKeys = self::mergeKeys($curRowKeys, $colKeys);
            }
        }
        return $colKeys;
    }

    /**
     * Get values for passed keys
     *
     * Used by table method
     *
     * @param array $row     should be array or abstraction
     * @param array $keys    column keys
     * @param array $objInfo Will be populated with object info
     *                           if row is an object, $objInfo['row'] will be populated with
     *                               'className' & 'phpDoc'
     *                           if a value is an object being displayed as a string,
     *                               $objInfo['cols'][key] will be populated
     *
     * @return array
     */
    public static function keyValues($row, $keys, &$objInfo)
    {
        $objInfo = array(
            'row' => false,
            'cols' => array(),
        );
        if ($row instanceof Abstraction) {
            $row = self::keyValuesAbstraction($row, $objInfo);
        } elseif (\is_array($row) === false) {
            $row = array(self::SCALAR =>  $row);
        }
        $values = array();
        foreach ($keys as $key) {
            if (!\array_key_exists($key, $row)) {
                $values[$key] = Abstracter::UNDEFINED;
                continue;
            }
            $value = $row[$key];
            if ($value !== null) {
                // by setting to false :
                //    indicate that the column is not populated by objs of the same type
                //    if stringified abstraction, we'll set cols[key] below
                $objInfo['cols'][$key] = false;
            }
            if ($value instanceof Abstraction) {
                // just return the stringified / __toString value in a table
                if (isset($value['stringified'])) {
                    $objInfo['cols'][$key] = $value['className'];
                    $value = $value['stringified'];
                } elseif (isset($value['__toString']['returnValue'])) {
                    $objInfo['cols'][$key] = $value['className'];
                    $value = $value['__toString']['returnValue'];
                }
            }
            $values[$key] = $value;
        }
        return $values;
    }

    /**
     * Handle table() call
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    public function onLog(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'caption' => null,
            'columns' => array(),
            'sortable' => true,
            'totalCols' => array(),
        ), $logEntry['meta']);
        $argCount = \count($args);
        $data = null;
        for ($i = 0; $i < $argCount; $i++) {
            if (\is_array($args[$i])) {
                if ($data === null) {
                    $data = $args[$i];
                } elseif (!$meta['columns']) {
                    $meta['columns'] = $args[$i];
                }
            } elseif (\is_object($args[$i])) {
                // Traversable or other
                if ($data === null) {
                    $data = $args[$i];
                }
            } elseif (\is_string($args[$i]) && !$meta['caption']) {
                $meta['caption'] = $args[$i];
            }
            unset($args[$i]);
        }
        $logEntry['args'] = array($data);
        $logEntry['meta'] = $meta;
    }

    /**
     * Get the keys contained in value
     *
     * @param mixed $val scalar value or abstraction
     *
     * @return string[]
     */
    private static function keys($val)
    {
        if (Abstracter::isAbstraction($val)) {
            // abstraction
            if ($val['type'] === 'object') {
                if ($val['traverseValues']) {
                    // probably Traversable
                    return \array_keys($val['traverseValues']);
                }
                if ($val['stringified']) {
                    return array(self::SCALAR);
                }
                if (isset($val['methods']['__toString']['returnValue'])) {
                    return array(self::SCALAR);
                }
                $val = \array_filter($val['properties'], function ($prop) {
                    return $prop['visibility'] === 'public';
                });
                $keys = \array_keys($val);
                /*
                    Reflection doesn't return properties in any given order
                    so, we'll sort for consistency
                */
                \sort($keys, SORT_NATURAL | SORT_FLAG_CASE);
                return $keys;
            }
            // ie callable or resource
            return array(self::SCALAR);
        }
        return \is_array($val)
            ? \array_keys($val)
            : array(self::SCALAR);
    }

    /**
     * Get "object values" from abstraction
     *
     * @param Abstraction $row     [description]
     * @param array       $objInfo [description]
     *
     * @return array
     */
    private static function keyValuesAbstraction(Abstraction $row, &$objInfo)
    {
        if ($row['type'] !== 'object') {
            // resource & callable
            return array(self::SCALAR => $row);
        }
        $objInfo['row'] = array(
            'className' => $row['className'],
            'phpDoc' => $row['phpDoc'],
        );
        if ($row['className'] === 'Closure') {
            $objInfo['row'] = false;
            return array(self::SCALAR => $row);
        }
        $row = self::objectValues($row);
        if (\is_array($row) === false) {
            // ie stringified value
            $objInfo['row'] = false;
            $row = array(self::SCALAR => $row);
        }
        return $row;
    }

    /**
     * Merge current row's keys with merged keys
     *
     * @param array $curRowKeys current row's keys
     * @param array $colKeys    all col keys
     *
     * @return array
     */
    private static function mergeKeys($curRowKeys, $colKeys)
    {
        $newKeys = array();
        $count = \count($curRowKeys);
        for ($i = 0; $i < $count; $i++) {
            $curKey = $curRowKeys[$i];
            if ($colKeys && $curKey === $colKeys[0]) {
                \array_push($newKeys, $curKey);
                \array_shift($colKeys);
                continue;
            }
            $position = \array_search($curKey, $colKeys, true);
            if ($position !== false) {
                $segment = \array_splice($colKeys, 0, (int) $position + 1);
                \array_splice($newKeys, \count($newKeys), 0, $segment);
            } elseif (!\in_array($curKey, $newKeys, true)) {
                \array_push($newKeys, $curKey);
            }
        }
        // put on remaining colKeys
        \array_splice($newKeys, \count($newKeys), 0, $colKeys);
        return \array_unique($newKeys);
    }

    /**
     * Get object abstraction's values
     * if, object has a stringified or __toString value, it will be returned
     *
     * @param Abstraction $abs object abstraction
     *
     * @return array|string
     */
    private static function objectValues(Abstraction $abs)
    {
        if ($abs['traverseValues']) {
            // probably Traversable
            return $abs['traverseValues'];
        }
        if ($abs['stringified']) {
            return $abs['stringified'];
        }
        if (isset($abs['methods']['__toString']['returnValue'])) {
            return $abs['methods']['__toString']['returnValue'];
        }
        $values = $abs['properties'];
        foreach ($values as $k => $info) {
            if ($info['visibility'] !== 'public') {
                unset($values[$k]);
                continue;
            }
            $values[$k] = $info['value'];
        }
        return $values;
    }
}

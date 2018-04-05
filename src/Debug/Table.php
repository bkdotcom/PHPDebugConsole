<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.1.0
 */

namespace bdk\Debug;

/**
 * Table helper methods
 */
class Table
{

    /**
     * Go through all the "rows" of array to determine what the keys are and their order
     *
     * @param array $rows array (or traversable abstraction)
     *
     * @return array
     */
    public static function colKeys($rows)
    {
        if (!\is_array($rows)) {
            return array();
        }
        $lastKeys = array();
        $newKeys = array();
        $curKeys = array();
        foreach ($rows as $row) {
            $curKeys = self::keys($row);
            if (empty($lastKeys)) {
                $lastKeys = $curKeys;
            } elseif ($curKeys != $lastKeys) {
                $newKeys = array();
                $count = \count($curKeys);
                for ($i = 0; $i < $count; $i++) {
                    $curKey = $curKeys[$i];
                    if (!empty($lastKeys) && $curKey === $lastKeys[0]) {
                        \array_push($newKeys, $curKey);
                        \array_shift($lastKeys);
                    } elseif (false !== $position = \array_search($curKey, $lastKeys, true)) {
                        $segment = \array_splice($lastKeys, 0, $position+1);
                        \array_splice($newKeys, \count($newKeys), 0, $segment);
                    } elseif (!\in_array($curKey, $newKeys, true)) {
                        \array_push($newKeys, $curKey);
                    }
                }
                // put on remaining from lastKeys
                \array_splice($newKeys, \count($newKeys), 0, $lastKeys);
                $lastKeys = \array_unique($newKeys);
            }
        }
        return $lastKeys;
    }

    /**
     * Get values for passed keys
     *
     * Used by table method
     *
     * @param array $row     should be array or abstraction
     * @param array $keys    column keys
     * @param array $objInfo if row is an object, this will be populated with className and phpDoc
     *                         Otherwise, this will be false
     *
     * @return array
     */
    public static function keyValues($row, $keys, &$objInfo)
    {
        $objInfo = false;
        $rowIsAbstraction = Abstracter::isAbstraction($row);
        if ($rowIsAbstraction) {
            if ($row['type'] == 'object') {
                $objInfo = array(
                    'className' => $row['className'],
                    'phpDoc' => $row['phpDoc'],
                );
                $row = self::objectValues($row);
            } elseif ($row['type'] == 'resource') {
                $objInfo = array(
                    'className' => 'resource',
                    'phpDoc' => null,
                );
                $row = array('' => $row);
            } else {
                $row = array('' => $row);
            }
        }
        if (!\is_array($row)) {
            $row = array('' =>  $row);
        }
        // return self::keyValuesGetValues($row, $keys);
        $values = array();
        foreach ($keys as $key) {
            $value = \array_key_exists($key, $row)
                ? $row[$key]
                : Abstracter::UNDEFINED;
            if (Abstracter::isAbstraction($value)) {
                // just output the stringified / __toString value in a table
                if (isset($value['stringified'])) {
                    $value = $value['stringified'];
                } elseif (isset($value['__toString']['returnValue'])) {
                    $value = $value['__toString']['returnValue'];
                }
            }
            $values[$key] = $value;
        }
        return $values;
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
            if ($val['type'] == 'object') {
                if (\in_array('Traversable', $val['implements'])) {
                    $val = $val['traverseValues'];
                } elseif ($val['stringified']) {
                    $val = null;
                } elseif (isset($val['methods']['__toString']['returnValue'])) {
                    $val = null;
                } else {
                    $val = \array_filter($val['properties'], function ($prop) {
                        return $prop['visibility'] === 'public';
                    });
                }
            } else {
                $val = null;
            }
        }
        return \is_array($val)
            ? \array_keys($val)
            : array('');
    }

    /**
     * Get object abstraction's values
     * if, object has a stringified or __toString value, it will bereturned
     *
     * @param array $abs object abstraction
     *
     * @return array|string
     */
    private static function objectValues($abs)
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
        if ($abs['className'] === 'Closure') {
            return array('' => $abs);
        }
        $values = $abs['properties'];
        foreach ($values as $k => $info) {
            if ($info['visibility'] !== 'public') {
                unset($values[$k]);
            } else {
                $values[$k] = $info['value'];
            }
        }
        return $values;
    }
}

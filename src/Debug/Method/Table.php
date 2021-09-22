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

    private $debug;
    private $logEntry;
    private $meta = array();

    /**
     * Handle table() call
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    public function doTable(LogEntry $logEntry)
    {
        $this->logEntry = $logEntry;
        $this->debug = $logEntry->getSubject();

        $cfgRestore = array();
        if (isset($logEntry['meta']['cfg'])) {
            $cfgRestore = $this->debug->setCfg($logEntry['meta']['cfg']);
            $logEntry->setMeta('cfg', null);
        }

        $this->initLogEntry();
        $this->processRows();

        if ($cfgRestore) {
            $this->debug->setCfg($cfgRestore);
        }

        if (!$this->haveTableData()) {
            $logEntry['method'] = 'log';
            if ($this->meta['caption']) {
                \array_unshift($logEntry['args'], $this->meta['caption']);
            }
        }
        $this->setMeta();
    }

    /**
     * Go through all the "rows" of array to determine what the keys are and their order
     *
     * @param array $rows array (or traversable abstraction)
     *
     * @return array
     */
    private function colKeys($rows)
    {
        if ($this->debug->abstracter->isAbstraction($rows, Abstracter::TYPE_OBJECT)) {
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
            $curRowKeys = $this->keys($row);
            if (empty($colKeys)) {
                $colKeys = $curRowKeys;
            } elseif ($curRowKeys !== $colKeys) {
                $colKeys = self::mergeKeys($curRowKeys, $colKeys);
            }
        }
        return $colKeys;
    }

    /**
     * Do we have table data?
     *
     * @return bool
     */
    private function haveTableData()
    {
        return isset($this->logEntry['args'][0])
            && \is_array($this->logEntry['args'][0])
            && $this->logEntry['args'][0] !== array();
    }

    /**
     * Find the data, caption, & columns in logEntry arguments
     *
     * @return void
     */
    private function initLogEntry()
    {
        $args = $this->logEntry['args'];
        $argCount = \count($args);
        $other = Abstracter::UNDEFINED;
        $this->initMeta();
        $this->logEntry['args'] = array();
        for ($i = 0; $i < $argCount; $i++) {
            $isOther = $this->testArg($args[$i]);
            if ($isOther && $other === Abstracter::UNDEFINED) {
                $other = $args[$i];
            }
        }
        if ($this->logEntry['args'] === array() && $other !== Abstracter::UNDEFINED) {
            $this->logEntry['args'] = array($other);
        }
    }

    /**
     * Merge / initialize meta values
     *
     * @return void
     */
    private function initMeta()
    {
        /*
            columns, columnNames, & totalCols will be moved to
            tableInfo['columns'] structure
        */
        $this->meta = $this->debug->arrayUtil->mergeDeep(array(
            'caption' => null,
            'columns' => array(),
            'columnNames' => array(
                self::SCALAR => 'value',
            ),
            'inclContext' => false, // for trace tables
            'sortable' => true,
            'tableInfo' => array(
                'class' => null,
                'columns' => array(
                    /*
                    array(
                        key
                        class
                        total
                    )
                    */
                ),
                'haveObjRow' => false,
                'indexLabel' => null,
                'rows' => array(
                    /*
                    key => array(
                        'args'     (for traces)
                        'class'
                        'context'  (for traces)
                        'isScalar'
                        'key'      (alternate key to display)
                        'summary'
                    )
                    */
                ),
                'summary' => null, // if table is an obj... phpDoc summary
            ),
            'totalCols' => array(),
        ), $this->logEntry['meta']);
    }

    /**
     * Initialize this->meta['tableInfo']['columns']
     *
     * @return void
     */
    private function initTableInfoColumns()
    {
        $columns = array();
        $columnNames = $this->meta['columnNames'];
        $keys = $this->meta['columns'] ?: $this->colKeys($this->logEntry['args'][0]);
        foreach ($keys as $key) {
            $colInfo = array(
                'key' => isset($columnNames[$key])
                    ? $columnNames[$key]
                    : $key
            );
            if (\in_array($key, $this->meta['totalCols'])) {
                $colInfo['total'] = null;
            }
            $columns[$key] = $colInfo;
        }
        $this->meta['tableInfo']['columns'] = $columns;
    }

    /**
     * Get the keys contained in value
     *
     * @param mixed $val scalar value or abstraction
     *
     * @return string[]
     */
    private function keys($val)
    {
        if ($this->debug->abstracter->isAbstraction($val)) {
            // abstraction
            if ($val['type'] === Abstracter::TYPE_OBJECT) {
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
     * Get values for passed keys
     *
     * Used by table method
     *
     * @param array $row     should be array or abstraction
     * @param array $keys    column keys
     * @param array $rowInfo Will be populated with object info
     *                           if row is an object, $rowInfo['row'] will be populated with
     *                               'class' & 'summary'
     *                           if a value is an object being displayed as a string,
     *                               $rowInfo['classes'][key] will be populated with className
     *
     * @return array
     */
    private static function keyValues($row, $keys, &$rowInfo)
    {
        $rowInfo = array(
            'class' => null,
            'classes' => array(), // key => classname (or false if not stringified class)
            'isScalar' => false,
            'summary' => null,
        );
        if ($row instanceof Abstraction) {
            $row = self::keyValuesAbstraction($row, $rowInfo);
        } elseif (\is_array($row) === false) {
            $row = array(self::SCALAR => $row);
        }
        $values = array();
        foreach ($keys as $key) {
            $rowInfo['classes'][$key] = false;
            $value = \array_key_exists($key, $row)
                ? $row[$key]
                : Abstracter::UNDEFINED;
            if ($value instanceof Abstraction) {
                // just return the stringified / __toString value in a table
                if (isset($value['stringified'])) {
                    $rowInfo['classes'][$key] = $value['className'];
                    $value = $value['stringified'];
                } elseif (isset($value['__toString']['returnValue'])) {
                    $rowInfo['classes'][$key] = $value['className'];
                    $value = $value['__toString']['returnValue'];
                }
            }
            $values[$key] = $value;
        }
        if (\array_keys($values) === array(self::SCALAR)) {
            $rowInfo['isScalar'] = true;
        }
        return $values;
    }

    /**
     * Get "object values" from abstraction
     *
     * @param Abstraction $abs     Abstraction instance
     * @param array       $rowInfo row info
     *
     * @return array
     */
    private static function keyValuesAbstraction(Abstraction $abs, &$rowInfo)
    {
        if ($abs['type'] !== Abstracter::TYPE_OBJECT) {
            // resource & callable
            $rowInfo['isScalar'] = true;
            return array(self::SCALAR => $abs);
        }
        if ($abs['className'] === 'Closure') {
            $rowInfo['isScalar'] = true;
            return array(self::SCALAR => $abs);
        }
        $rowInfo['class'] = $abs['className'];
        $rowInfo['summary'] = $abs['phpDoc']['summary'];
        $row = self::objectValues($abs);
        if (\is_array($row) === false) {
            // ie stringified value
            $rowInfo['class'] = null;
            $rowInfo['isScalar'] = true;
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
        return \array_map(
            function ($info) {
                return $info['value'];
            },
            \array_filter($abs['properties'], function ($prop) {
                return $prop['visibility'] === 'public';
            })
        );
    }

    /**
     * non-array
     * empty array
     * array
     * object / traversable
     * ovject / traversable or objects / traversables
     *
     * @return void
     */
    private function processRows()
    {
        if (!isset($this->logEntry['args'][0])) {
            return;
        }
        $rows = $this->debug->abstracter->crate($this->logEntry['args'][0], 'table');
        if ($this->debug->abstracter->isAbstraction($rows, Abstracter::TYPE_OBJECT)) {
            $this->meta['tableInfo']['class'] = $rows['className'];
            $this->meta['tableInfo']['summary'] = $rows['phpDoc']['summary'];
            $rows = $rows['traverseValues']
                ? $rows['traverseValues']
                : \array_map(
                    function ($info) {
                        return $info['value'];
                    },
                    \array_filter($rows['properties'], function ($prop) {
                        return $prop['visibility'] === 'public';
                    })
                );
        }
        if (!\is_array($rows)) {
            return;
        }
        $this->logEntry['args'] = array($rows);
        $this->initTableInfoColumns();
        $columns = $this->meta['tableInfo']['columns'];
        $keys = \array_keys($columns);
        $inclContext = $this->meta['inclContext'];
        foreach ($rows as $rowKey => $row) {
            // row may be "scalar", array, Traversable, or object
            $rowInfo = array();
            $valsTemp = $this->keyValues($row, $keys, $rowInfo);
            if ($inclContext) {
                $rowInfo['args'] = $row['args'];
                $rowInfo['context'] = $row['context'];
            }
            $this->updateTableInfo($rowKey, $valsTemp, $rowInfo);
            $values = array();
            foreach ($valsTemp as $k => $v) {
                $kNew = $columns[$k]['key'];
                $values[$kNew] = $v;
            }
            $rows[$rowKey] = $values;
        }
        $this->logEntry['args'] = array($rows);
    }

    /**
     * Set tableInfo meta info
     *
     * @return void
     */
    private function setMeta()
    {
        $columns = array();
        foreach ($this->meta['tableInfo']['columns'] as $colInfo) {
            $columns[] = \array_filter($colInfo, 'strlen');
        }
        $this->meta['tableInfo']['columns'] = $columns;
        unset(
            $this->meta['columns'],
            $this->meta['columnNames'],
            $this->meta['totalCols']
        );
        if (!$this->meta['inclContext']) {
            unset($this->meta['inclContext']);
        }
        if (!$this->haveTableData()) {
            unset(
                $this->meta['caption'],
                $this->meta['inclContext'],
                $this->meta['sortable'],
                $this->meta['tableInfo']
            );
        }
        $this->logEntry['meta'] = $this->meta;
    }

    /**
     * Place argument as "data", "caption", "columns", or "other"
     *
     * @param mixed $val argument value
     *
     * @return bool whether to treat the val as "other"
     */
    private function testArg($val)
    {
        if (\is_array($val)) {
            if ($this->logEntry['args'] === array()) {
                $this->logEntry['args'] = array($val);
            } elseif (!$this->meta['columns']) {
                $this->meta['columns'] = $val;
            }
            return false;
        }
        if (\is_object($val)) {
            // Traversable or other
            if ($this->logEntry['args'] === array()) {
                $this->logEntry['args'] = array($val);
            }
            return false;
        }
        if (\is_string($val) && $this->meta['caption'] === null) {
            $this->meta['caption'] = $val;
            return false;
        }
        return true;
    }

    /**
     * Update collected table info
     *
     * @param int|string $rowKey    row's key/index
     * @param array      $rowValues row's values
     * @param array      $rowInfo   Row info
     *
     * @return void
     */
    private function updateTableInfo($rowKey, $rowValues, $rowInfo)
    {
        foreach ($this->meta['totalCols'] as $key) {
            $this->meta['tableInfo']['columns'][$key]['total'] += $rowValues[$key];
        }
        $this->meta['tableInfo']['haveObjRow'] = $this->meta['tableInfo']['haveObjRow'] || $rowInfo['class'];
        $classes = $rowInfo['classes'];
        unset($rowInfo['classes']);
        $rowInfo = \array_filter($rowInfo, function ($val) {
            return $val !== null && $val !== false;
        });
        if ($rowInfo) {
            $rowInfoExisting = isset($this->meta['tableInfo']['rows'][$rowKey])
                ? $this->meta['tableInfo']['rows'][$rowKey]
                : array();
            $this->meta['tableInfo']['rows'][$rowKey] = \array_merge($rowInfo, $rowInfoExisting);
        }
        foreach ($classes as $key => $class) {
            if (!isset($this->meta['tableInfo']['columns'][$key]['class'])) {
                $this->meta['tableInfo']['columns'][$key]['class'] = $class;
            }
            if ($this->meta['tableInfo']['columns'][$key]['class'] !== $class) {
                // column values not of the same type
                $this->meta['tableInfo']['columns'][$key]['class'] = false;
            }
        }
    }
}

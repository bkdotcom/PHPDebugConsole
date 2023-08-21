<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Utility\TableRow;

/**
 * Tablefy data.
 * Ensure all row fields are in the same order
 */
class Table
{
    private $debug;
    private $meta = array();
    private $rows = array();

    /**
     * Constructor
     *
     * @param mixed $rows  [description]
     * @param array $meta  Meta info / options
     * @param Debug $debug [description]
     */
    public function __construct($rows = array(), array $meta = array(), Debug $debug = null)
    {
        $this->debug = $debug ?: Debug::getInstance();
        $this->initMeta($meta);
        $this->processRows($rows);
        $this->setMeta();
    }

    /**
     * Get table rows
     *
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * Get meta info
     *
     * @return array
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * Do we have table data?
     *
     * @return bool
     */
    public function haveRows()
    {
        return \is_array($this->rows) && \count($this->rows) > 0;
    }

    /**
     * Go through all the "rows" of array to determine what the keys are and their order
     *
     * @param TableRow[] $rows array of TableRow instance
     *
     * @return array
     */
    private static function colKeys($rows)
    {
        if (\is_array($rows) === false) {
            return array();
        }
        $colKeys = array();
        foreach ($rows as $row) {
            if (!$row instanceof TableRow) {
                $row = new TableRow($row);
            }
            $curRowKeys = $row->keys();
            if ($curRowKeys !== $colKeys) {
                $colKeys = self::colKeysMerge($curRowKeys, $colKeys);
            }
        }
        return $colKeys;
    }

    /**
     * Merge current row's keys with merged keys
     *
     * @param array $curRowKeys current row's keys
     * @param array $colKeys    all col keys
     *
     * @return array
     */
    private static function colKeysMerge($curRowKeys, $colKeys)
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
            } elseif (\in_array($curKey, $newKeys, true) === false) {
                \array_push($newKeys, $curKey);
            }
        }
        // put on remaining colKeys
        \array_splice($newKeys, \count($newKeys), 0, $colKeys);
        return \array_unique($newKeys);
    }

    /**
     * Merge / initialize meta values
     *
     * @param array $meta Meta info / options
     *
     * @return void
     */
    private function initMeta(array $meta)
    {
        /*
            columns, columnNames, & totalCols will be moved to
            tableInfo['columns'] structure
        */
        $this->meta = $this->debug->arrayUtil->mergeDeep(array(
            'caption' => null,
            'columnNames' => array(
                TableRow::SCALAR => 'value',
            ),
            'columns' => array(),
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
        ), $meta);
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
        $keys = $this->meta['columns'] ?: self::colKeys($this->rows);
        foreach ($keys as $key) {
            $columns[$key] = array(
                'key' => isset($columnNames[$key])
                    ? $columnNames[$key]
                    : $key,
            );
        }
        foreach ($this->meta['totalCols'] as $i => $key) {
            if (isset($columns[$key]) === false) {
                unset($this->meta['totalCols'][$i]);
                continue;
            }
            $columns[$key]['total'] = null;
        }
        $this->meta['tableInfo']['columns'] = $columns;
    }

    /**
     * Reduce each row to the columns specified
     * Do this so we don't needlessly crate values that we won't output
     *
     * @param mixed $rows Table rows
     *
     * @return array
     */
    private function preCrate($rows)
    {
        if (\is_array($rows) === false || empty($this->meta['columns'])) {
            return $rows;
        }
        $colFlip = \array_flip($this->meta['columns']);
        foreach ($rows as $i => $row) {
            if (\is_array($row)) {
                $rows[$i] = \array_intersect_key($row, $colFlip);
            }
        }
        return $rows;
    }

    /**
     * non-array
     * empty array
     * array
     * object / traversable
     * ovject / traversable or objects / traversables
     *
     * @param mixed $rows Row data to process
     *
     * @return void
     */
    private function processRows($rows)
    {
        if ($rows === null) {
            return;
        }
        $rows = $this->processRowsGet($rows);
        if (\is_array($rows) === false) {
            return;
        }
        foreach ($rows as $rowKey => $row) {
            $rows[$rowKey] = new TableRow($row);
        }
        $this->rows = $rows;
        $this->initTableInfoColumns();
        foreach ($this->rows as $rowKey => $row) {
            $this->rows[$rowKey] = $this->processRow($row, $rowKey);
        }
    }

    /**
     * Get table rows
     *
     * @return array
     */
    private function processRowsGet($rows)
    {
        // $rows = $this->logEntry['args'][0];
        if ($this->meta['inclContext'] === false) {
            $rows = $this->preCrate($rows);
        }
        $rows = $this->debug->abstracter->crate($rows, 'table'); // $this->logEntry['method']
        if ($this->debug->abstracter->isAbstraction($rows, Abstracter::TYPE_OBJECT)) {
            $this->meta['tableInfo']['class'] = $rows['className'];
            $this->meta['tableInfo']['summary'] = $rows['phpDoc']['summary'];
            $rows = $rows['traverseValues']
                ? $rows['traverseValues']
                : \array_map(
                    static function ($info) {
                        return $info['value'];
                    },
                    \array_filter($rows['properties'], static function ($prop) {
                        return $prop['visibility'] === 'public';
                    })
                );
        }
        return $rows;
    }

    /**
     * Process table row
     *
     * @param TableRow   $row    TableRow instance
     * @param string|int $rowKey index of row
     *
     * @return array key => value
     */
    private function processRow(TableRow $row, $rowKey)
    {
        $columns = $this->meta['tableInfo']['columns'];
        $keys = \array_keys($columns);
        $rowInfo = array();
        $valsTemp = $row->keyValues($keys);
        $rowInfo = $row->getInfo();
        if ($this->meta['inclContext']) {
            $rowInfo['args'] = $row->getValue('args');
            $rowInfo['context'] = $row->getValue('context');
        }
        $this->updateTableInfo($rowKey, $valsTemp, $rowInfo);
        $values = array();
        foreach ($valsTemp as $k => $v) {
            $kNew = $columns[$k]['key'];
            $values[$kNew] = $v;
        }
        return $values;
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
        if (!$this->haveRows()) {
            unset(
                $this->meta['caption'],
                $this->meta['inclContext'],
                $this->meta['sortable'],
                $this->meta['tableInfo']
            );
        }
        // $this->logEntry['meta'] = $this->meta;
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
        $this->meta['tableInfo']['haveObjRow'] = $this->meta['tableInfo']['haveObjRow'] || $rowInfo['class'];
        foreach ($this->meta['totalCols'] as $key) {
            $this->meta['tableInfo']['columns'][$key]['total'] += $rowValues[$key];
        }
        foreach ($rowInfo['classes'] as $key => $class) {
            if (!isset($this->meta['tableInfo']['columns'][$key]['class'])) {
                $this->meta['tableInfo']['columns'][$key]['class'] = $class;
            } elseif ($this->meta['tableInfo']['columns'][$key]['class'] !== $class) {
                // column values not of the same type
                $this->meta['tableInfo']['columns'][$key]['class'] = false;
            }
        }
        $this->updateTableInfoRow($rowKey, $rowInfo);
    }

    /**
     * Merge rowInfo into tableInfo['rows'][$rowKey]
     *
     * @param int|string $rowKey  row's key/index
     * @param array      $rowInfo Row info
     *
     * @return void
     */
    private function updateTableInfoRow($rowKey, $rowInfo)
    {
        unset($rowInfo['classes']);
        $rowInfo = \array_filter($rowInfo, static function ($val) {
            return $val !== null && $val !== false;
        });
        if (!$rowInfo) {
            return;
        }
        // non-null/false values
        $rowInfoExisting = isset($this->meta['tableInfo']['rows'][$rowKey])
            ? $this->meta['tableInfo']['rows'][$rowKey]
            : array();
        $this->meta['tableInfo']['rows'][$rowKey] = \array_merge($rowInfoExisting, $rowInfo);
    }
}

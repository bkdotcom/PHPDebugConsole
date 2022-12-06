<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Method;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;

/**
 * Table helper methods
 */
class Table
{
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
                TableRow::SCALAR => 'value',
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
        $keys = $this->meta['columns'] ?: self::colKeys($this->logEntry['args'][0]);
        foreach ($keys as $key) {
            $colInfo = array(
                'key' => isset($columnNames[$key])
                    ? $columnNames[$key]
                    : $key
            );
            if (\in_array($key, $this->meta['totalCols'], true)) {
                $colInfo['total'] = null;
            }
            $columns[$key] = $colInfo;
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
     * @return void
     */
    private function processRows()
    {
        if (!isset($this->logEntry['args'][0])) {
            return;
        }
        $rows = $this->processRowsGet();
        if (\is_array($rows) === false) {
            return;
        }
        foreach ($rows as $rowKey => $row) {
            $rows[$rowKey] = new TableRow($row);
        }
        $this->logEntry['args'] = array($rows);
        $this->initTableInfoColumns();
        foreach ($rows as $rowKey => $row) {
            $rows[$rowKey] = $this->processRow($row, $rowKey);
        }
        $this->logEntry['args'] = array($rows);
    }

    /**
     * Get table rows
     *
     * @return array
     */
    private function processRowsGet()
    {
        $rows = $this->logEntry['args'][0];
        if ($this->meta['inclContext'] === false) {
            $rows = $this->preCrate($rows);
        }
        $rows = $this->debug->abstracter->crate($rows, $this->logEntry['method']);
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
            $this->testArgArray($val);
            return false;
        }
        if (\is_object($val)) {
            $this->testArgObject($val);
            return false;
        }
        if (\is_string($val) && $this->meta['caption'] === null) {
            $this->meta['caption'] = $val;
            return false;
        }
        return true;
    }

    /**
     * Should array argument be treated as table data or columns?
     *
     * @param array $val table() arg of type array
     *
     * @return void
     */
    private function testArgArray($val)
    {
        if ($this->logEntry['args'] === array()) {
            $this->logEntry['args'] = array($val);
        } elseif (!$this->meta['columns']) {
            $this->meta['columns'] = $val;
        }
    }

    /**
     * Should object argument be treated as table data?
     *
     * @param array $val table() arg of type object
     *
     * @return void
     */
    private function testArgObject($val)
    {
        // Traversable or other
        if ($this->logEntry['args'] === array()) {
            $this->logEntry['args'] = array($val);
        }
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

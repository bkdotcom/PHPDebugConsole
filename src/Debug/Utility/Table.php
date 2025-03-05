<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.1
 */

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Utility\TableRow;

/**
 * Tablefy data.
 * Ensure all row fields are in the same order
 *
 * @psalm-type meta = array{
 *   columns: list<array-key>,
 *   columnNames: array<array-key, string>,
 *   tableInfo: array{
 *     class: string|null,
 *     columns: array<array-key, array<string, string|numeric|false>>,
 *     rows: array<array-key, array<string, mixed>>,
 *     ...<string, mixed>,
 *   },
 *   totalCols: list<array-key>,
 *   ...<string, mixed>,
 * }
 */
class Table
{
    /** @var Debug */
    private $debug;
    /** @var meta */
    private $meta = array(
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
                    attribs
                    key
                    class
                    total
                    falseAs: ''
                    trueAs: ''
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
            'summary' => '', // if table is an obj... phpDoc summary
        ),
        'totalCols' => array(),
    );
    /** @var array<array-key,TableRow|array>  */
    private $rows = array();

    /**
     * Constructor
     *
     * @param mixed               $rows  Table data
     * @param array<string,mixed> $meta  Meta info / options
     * @param Debug|null          $debug Debug instance
     */
    public function __construct($rows = array(), array $meta = array(), $debug = null)
    {
        \bdk\Debug\Utility::assertType($debug, 'bdk\Debug');

        $this->debug = $debug ?: Debug::getInstance();
        $this->initMeta($meta);
        $this->processRows($rows);
        $this->setMeta();
    }

    /**
     * Go through all the "rows" of array to determine what the keys are and their order
     *
     * @param array[]|TableRow[]|mixed[] $rows Array rows
     *
     * @return list<array-key>
     */
    public static function colKeys(array $rows)
    {
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
     * Get table rows
     *
     * @return array<array-key,TableRow|array>
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * Get meta info
     *
     * @return meta
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
     * Merge current row's keys with merged keys
     *
     * @param list<array-key> $curRowKeys current row's keys
     * @param list<array-key> $colKeys    all col keys
     *
     * @return list<array-key>
     */
    private static function colKeysMerge(array $curRowKeys, array $colKeys)
    {
        /** @var list<array-key> */
        $newKeys = array();
        $count = \count($curRowKeys);
        for ($i = 0; $i < $count; $i++) {
            $curKey = $curRowKeys[$i];
            if ($colKeys && $curKey === $colKeys[0]) {
                /** @psalm-var list<array-key> $newKeys */
                $newKeys[] = $curKey;
                \array_shift($colKeys);
                continue;
            }
            $position = \array_search($curKey, $colKeys, true);
            if ($position !== false) {
                $segment = \array_splice($colKeys, 0, (int) $position + 1);
                /** @psalm-var list<array-key> $newKeys */
                \array_splice($newKeys, \count($newKeys), 0, $segment);
            } elseif (\in_array($curKey, $newKeys, true) === false) {
                /** @psalm-var list<array-key> $newKeys */
                $newKeys[] = $curKey;
            }
        }
        // put on remaining colKeys
        \array_splice($newKeys, \count($newKeys), 0, $colKeys);
        /** @psalm-var list<array-key> */
        return \array_values(\array_unique($newKeys));
    }

    /**
     * Merge / initialize meta values
     *
     * @param array<string,mixed> $meta Meta info / options
     *
     * @return void
     */
    private function initMeta(array $meta)
    {
        /*
            columns, columnNames, & totalCols will be moved to
            tableInfo['columns'] structure
        */
        /** @psalm-var meta */
        $this->meta = $this->debug->arrayUtil->mergeDeep($this->meta, $meta);
    }

    /**
     * Initialize this->meta['tableInfo']['columns']
     *
     * @return void
     */
    private function initTableInfoColumns()
    {
        $columnNames = $this->meta['columnNames'];
        $keys = $this->meta['columns'] ?: self::colKeys($this->rows);
        $columns = \array_fill_keys($keys, array());
        \array_walk($columns, function (&$column, $key) use ($columnNames) {
            $default = isset($this->meta['tableInfo']['columns'][$key])
                ? $this->meta['tableInfo']['columns'][$key]
                : array();
            $column = \array_merge($default, array(
                'key' => isset($columnNames[$key])
                    ? $columnNames[$key]
                    : $key,
            ));
        });
        foreach ($this->meta['totalCols'] as $i => $key) {
            if (isset($columns[$key]) === false) {
                unset($this->meta['totalCols'][$i]);
                continue;
            }
            $columns[$key]['total'] = null;
        }
        /**
         * @psalm-suppress MixedArrayAssignment
         * @psalm-suppress MixedPropertyTypeCoercion
         */
        $this->meta['tableInfo']['columns'] = $columns;
    }

    /**
     * Reduce each row to the columns specified
     * Do this so we don't needlessly crate values that we won't output
     *
     * @param mixed $rows Table rows
     *
     * @return mixed
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
        $this->rows = \array_map(static function ($row) {
            return new TableRow($row);
        }, $rows);
        $this->initTableInfoColumns();
        foreach ($this->rows as $rowKey => $row) {
            $this->rows[$rowKey] = $this->processRow($row, $rowKey);
        }
    }

    /**
     * Get table rows
     *
     * @param mixed $rows Row data to process
     *
     * @return mixed
     */
    private function processRowsGet($rows)
    {
        if ($this->meta['inclContext'] === false) {
            $rows = $this->preCrate($rows);
        }
        $rows = $this->debug->abstracter->crate($rows, 'table');
        if ($this->debug->abstracter->isAbstraction($rows, Type::TYPE_OBJECT)) {
            /**
             * @psalm-var array{
             *    classname: string,
             *    phpDoc: array{summary: string},
             *    properties: array<string, array<string,mixed>>,
             *    traverseValues?: array,
             * } $rows
             *
             *
             * @psalm-suppress MixedArrayAssignment
             * @psalm-suppress MixedPropertyTypeCoercion pslam bug tableInfo becomes mixed
             */
            $this->meta['tableInfo']['class'] = $rows['className'];
            /**
             * @psalm-suppress MixedArrayAssignment
             * @psalm-suppress MixedPropertyTypeCoercion pslam bug tableInfo becomes mixed
             * @psalm-suppress MixedArrayAccess
             */
            $this->meta['tableInfo']['summary'] = $rows['phpDoc']['summary'];
            /** @psalm-suppress MixedArgument */
            return $rows['traverseValues']
                ? $rows['traverseValues']
                : \array_map(
                    /**
                     * @param array{value:mixed,...<string,mixed>} $info
                     */
                    static function ($info) {
                        return $info['value'];
                    },
                    \array_filter(
                        $rows['properties'],
                        /**
                         * @param array<string,mixed> $prop
                         */
                        static function ($prop) {
                            return \in_array('public', (array) $prop['visibility'], true);
                        }
                    )
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
            $columns[] = \array_filter($colInfo, static function ($val) {
                return \is_array($val)
                    ? !empty($val)
                    : $val !== null && $val !== false;
            });
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
    private function updateTableInfo($rowKey, array $rowValues, array $rowInfo)
    {
        $this->meta['tableInfo']['haveObjRow'] = $this->meta['tableInfo']['haveObjRow'] || $rowInfo['class'];
        foreach ($this->meta['totalCols'] as $key) {
            /**
             * @psalm-suppress MixedPropertyTypeCoercion
             * @psalm-suppress MixedOperand
             * @psalm-suppress PossiblyFalseOperand
             */
            $this->meta['tableInfo']['columns'][$key]['total'] += $rowValues[$key];
        }
        /** @var array-key $key */
        foreach ($rowInfo['classes'] as $key => $class) {
            if (!isset($this->meta['tableInfo']['columns'][$key]['class'])) {
                /** @psalm-suppress MixedPropertyTypeCoercion */
                $this->meta['tableInfo']['columns'][$key]['class'] = $class;
            } elseif ($this->meta['tableInfo']['columns'][$key]['class'] !== $class) {
                // column values not of the same type
                /** @psalm-suppress MixedPropertyTypeCoercion */
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
    private function updateTableInfoRow($rowKey, array $rowInfo)
    {
        unset($rowInfo['classes']);
        $rowInfo = \array_filter($rowInfo, static function ($val) {
            return \in_array($val, [null, false, ''], true) === false;
        });
        if (!$rowInfo) {
            return;
        }
        // non-null/false values
        $rowInfoExisting = isset($this->meta['tableInfo']['rows'][$rowKey])
            ? $this->meta['tableInfo']['rows'][$rowKey]
            : array();
        /**
         * @psalm-suppress MixedArrayAssignment
         * @psalm-suppress MixedPropertyTypeCoercion
         */
        $this->meta['tableInfo']['rows'][$rowKey] = \array_merge($rowInfoExisting, $rowInfo);
    }
}

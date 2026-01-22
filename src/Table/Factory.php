<?php

namespace bdk\Table;

use bdk\Debug\Utility\ArrayUtil;
use bdk\Debug\Utility\Php;
use bdk\Debug\Utility\PhpType;
use bdk\Table\TableRow;

/**
 * Create Table structure via iterable (array or object)
 */
class Factory
{
    const KEY_CLASS_NAME = '___class_name';
    const KEY_INDEX = "\x00index\x00";
    const KEY_SCALAR = "\x00scalar\x00";
    const VAL_UNDEFINED = "\x00undefined\x00";

    /** @var array|object $data Data being processed */
    private $data;

    /** @var array<string, mixed> */
    private $options = array(
        'columnLabels' => array(
            self::KEY_CLASS_NAME => '',
            self::KEY_INDEX => '',
            self::KEY_SCALAR => 'value',
        ),
        'columnMeta' => array(
            self::KEY_INDEX => array(
                'attribs' => array(
                    'class' => ['t_key'],
                    'scope' => 'row',
                ),
                'tagName' => 'th',
            ),
        ), // key => meta array
        'columns' => [], // list of keys
        'getValInfo' => null, // callable to get type info
        'totalCols' => [],
    );

    /** @var array<string, mixed> */
    private $meta = array(
        'class' => null,
        'columns' => array(
            // array(
            //    'attribs' => array()
            //    'class' => null,
            //    'key' => int|string,
            //    'total' => null,
            // )
        ),
        'haveObjectRow' => false, // temporary (not store in table's meta)
    );

    /** @var array<string, mixed> */
    private $optionsDefault = array();

    /** @var Table */
    private $table;

    /**
     * Constructor
     *
     * @param array $options Default options
     */
    public function __construct(array $options = array())
    {
        $this->optionsDefault = \array_replace_recursive($this->options, $options);
    }

    /**
     * Create Table
     *
     * @param array|object $data    Data to populate table
     * @param array        $options options
     *
     * @return Table
     */
    public function create($data, array $options = array())
    {
        $this->data = $data;
        $this->table = new Table();
        $this->options = \array_replace_recursive($this->optionsDefault, $options);
        $this->initMeta();
        $this->preProcess();
        // data is now array of arrays,
        //  keys/cols not yet determined
        //  keys/cols not necessarily in consistent order
        $keys = $this->columnKeys();
        $this->initMeta($keys);
        $this->processRows($keys);
        $this->addHeader();
        $this->addFooter();
        $this->meta['columns'] = \array_map(static function ($columnMeta) {
            if (empty($columnMeta['class'])) {
                unset($columnMeta['class']);
            }
            unset($columnMeta['total']);
            return $columnMeta;
        }, $this->meta['columns']);
        $this->table->setMeta($this->meta);
        $this->data = [];
        return $this->table;
    }

    /**
     * Add header row to table
     *
     * @return void
     */
    private function addFooter()
    {
        if (empty($this->options['totalCols'])) {
            return;
        }
        $footerCells = array();
        foreach ($this->meta['columns'] as $columnMeta) {
            $key = $columnMeta['key'];
            $footerCells[] = \in_array($key, $this->options['totalCols'], true)
                ? new TableCell($columnMeta['total'])
                : (new TableCell())->setHtml('');
        }
        $this->table->setFooter(new TableRow($footerCells));
    }

    /**
     * Add footer row to table
     *
     * @return void
     */
    private function addHeader()
    {
        $headerCells = array();
        foreach ($this->meta['columns'] as $columnMeta) {
            $key = $columnMeta['key'];
            $label = isset($this->options['columnLabels'][$key])
                ? $this->options['columnLabels'][$key]
                : $key;
            $headerCells[] = new TableCell($label);
        }
        $this->table->setHeader(new TableRow($headerCells));
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
     * Return table's column keys
     *
     * @return list<array-key>
     */
    private function columnKeys()
    {
        $indexAndClassKeys = \array_filter([
            self::KEY_INDEX,
            $this->meta['haveObjectRow'] ? self::KEY_CLASS_NAME : null,
        ]);
        if ($this->options['columns']) {
            return \array_merge(
                $indexAndClassKeys,
                $this->options['columns']
            );
        }
        $colKeys = array();
        foreach ($this->data as $row) {
            $curRowKeys = \array_keys($row);
            if ($curRowKeys !== $colKeys) {
                $colKeys = self::colKeysMerge($curRowKeys, $colKeys);
            }
        }
        return $colKeys;
    }

    /**
     * Initialize temporary meta info
     *
     * @param array $keys column keys
     *
     * @return void
     */
    private function initMeta(array $keys = [])
    {
        if (empty($keys)) {
            $this->meta = array(
                'class' => null, // if table derived from object, store class name here
                'columns' => array(
                    // array(
                    //    'class' => null,
                    //    'key' => int|string,
                    //    'total' => null,
                    // )
                ),
                'haveObjectRow' => false,
            );
        }
        foreach ($keys as $key) {
            $columnMeta = isset($this->options['columnMeta'][$key]) && \is_array($this->options['columnMeta'][$key])
                ? $this->options['columnMeta'][$key]
                : array();
            $columnMeta = \array_merge(array(
                'class' => null, // if all values in column are objects of same class, store class name here
                'key' => $key,
                'total' => null, // temporary total value for column
            ), $columnMeta);
            \ksort($columnMeta);
            $this->meta['columns'][] = $columnMeta;
            if ($columnMeta['total'] !== null && !\in_array($key, $this->options['totalCols'], true)) {
                $this->options['totalCols'][] = $key;
            }
        }
    }

    /**
     * Get row values
     *
     * @param mixed      $row Row (object, array, or scalar value)
     * @param int|string $key Row's index/key
     *
     * @return array key->value array
     */
    private function getRowValues($row, $key)
    {
        $valInfo = $this->getValInfo($row, true);
        if ($valInfo['type'] === 'array') {
            return \array_replace(array(
                self::KEY_INDEX => $key,
            ), $row);
        }
        if ($valInfo['type'] === 'object' && $valInfo['iterable']) {
            $values = $this->options['columns']
                ? $this->getObjectValuesKeys($row, $this->options['columns'])
                : $this->getObjectValues($row);
            // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            return \array_replace(array(
                self::KEY_INDEX => $key,
                self::KEY_CLASS_NAME => $valInfo['className'],
            ), $values);
        }
        if ($valInfo['type'] === 'object') {
            // treat object as a single value
            // Closure, DateTime, UnitEnum
            // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            return array(
                self::KEY_INDEX => $key,
                self::KEY_CLASS_NAME => $valInfo['className'],
                self::KEY_SCALAR => $row,
            );
        }
        return array(
            self::KEY_INDEX => $key,
            self::KEY_SCALAR => $row,
        );
    }

    /**
     * Get object values as key->value array
     *
     * @param object $obj Object
     *
     * @return array
     */
    private function getObjectValues($obj)
    {
        $vals = array();
        foreach ($obj as $k => $v) {
            $vals[$k] = $v;
        }
        return $vals;
    }

    /**
     * Get object values as key->value array while specifying keys
     *
     * @param object $obj  Object
     * @param array  $keys Keys to retrieve
     *
     * @return array
     */
    private function getObjectValuesKeys($obj, $keys = [])
    {
        $valsAll = $this->getObjectValues($obj);
        \set_error_handler(static function ($type, $message) {
            throw new \Exception($message);
        });
        $vals = array();
        foreach ($keys as $key) {
            try {
                $vals[$key] = \array_key_exists($key, $valsAll)
                    ? $valsAll[$key]
                    : $obj->{$key};
            } catch (\Throwable $e) {
                $vals[$key] = self::VAL_UNDEFINED;
            }
        }
        \restore_error_handler();
        return $vals;
    }

    /**
     * Get value type
     *
     * @param mixed $value Value to get type of
     * @param bool  $isRow Does value represent a row
     *
     * @return array
     */
    private function getValInfo($value, $isRow = false)
    {
        if (\is_callable($this->options['getValInfo'])) {
            return \call_user_func($this->options['getValInfo'], $value);
        }
        $isObject = false;
        $type = PhpType::getDebugType($value, $isRow ? 0 : Php::ENUM_AS_OBJECT, $isObject);
        return array(
            'className' => $type,
            'iterable' => $isObject,
            'type' => $isObject
                ? 'object'
                : $type,
        );
    }

    /**
     * Convert data to array of arrays
     *
     * @return void
     */
    private function preProcess()
    {
        if (\is_object($this->data)) {
            $this->meta['class'] = \get_class($this->data);
            $this->data = $this->getObjectValues($this->data);
        }
        if (\is_array($this->data) === false) {
            // @todo store error?
            $this->data = [];
        }
        // $data is now array of unknowns (objects, arrays, or scalars)
        foreach ($this->data as $key => $row) {
            $values = $this->getRowValues($row, $key);
            $this->meta['haveObjectRow'] = $this->meta['haveObjectRow'] || isset($values[self::KEY_CLASS_NAME]);
            $this->data[$key] = $values;
        }
    }

    /**
     * Process data... add table rows
     *
     * @param array $keys column keys
     *
     * @return void
     */
    private function processRows(array $keys)
    {
        $defaultValues = \array_fill_keys($keys, self::VAL_UNDEFINED);
        foreach ($this->data as $row) {
            $values = \array_replace($defaultValues, \array_intersect_key($row, $defaultValues));
            $this->updateRowMeta($values);
            $row = new TableRow(\array_map(static function ($val) {
                return new TableCell($val);
            }, $values));
            $this->table->appendRow($row);
        }
    }

    /**
     * Collect column meta info
     *
     *  + Test if column values are all of same class
     *  + total values for columns that require it
     *
     * @param array $values Row key=>value array
     *
     * @return void
     */
    private function updateRowMeta(array $values)
    {
        $values = \array_values($values);
        \array_walk($values, function ($val, $i) {
            $columnMeta = $this->meta['columns'][$i];
            $columnClass = $columnMeta['class'];
            if ($columnClass === false || \in_array($val, [self::VAL_UNDEFINED, null], true)) {
                return;
            }
            $valInfo = $this->getValInfo($val, false);
            $this->meta['columns'][$i]['class'] = $valInfo['type'] === 'object' && \in_array($columnClass, [$valInfo['className'], null], true)
                ? $valInfo['className']
                : false;
        });
        $this->updateTotals($values);
    }

    /**
     * Update totals with current row's values
     *
     * @param array $values Row key=>value array
     *
     * @return void
     */
    private function updateTotals(array $values)
    {
        $keys = \array_column($this->meta['columns'], 'key'); // index to key mapping
        $indexes = \array_keys(\array_intersect($keys, $this->options['totalCols']));
        foreach ($indexes as $i) {
            $value = $values[$i];
            if (\is_numeric($value)) {
                $this->meta['columns'][$i]['total'] += $value;
            }
        }
    }
}

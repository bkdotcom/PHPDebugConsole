<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2026 Brad Kent
 * @since     3.6
 */

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\UnserializeLogBackwardsObject;

/**
 * "Normalize" log entries and values
 */
class UnserializeLogBackwards
{
    /** @var Debug */
    protected static $debug;

    /**
     * Update LogEntry with any necessary changes to work with current version of debug
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return LogEntry
     */
    public static function updateLogEntry(LogEntry $logEntry)
    {
        self::$debug = $logEntry->getSubject();
        $method = $logEntry['method'];
        if ($method === 'alert') {
            self::updateLogEntryAlert($logEntry);
        }
        if (\in_array($method, ['profileEnd', 'table', 'trace'], true) && !($logEntry['args'][0] instanceof Abstraction)) {
            self::updateLogEntryTabular($logEntry);
            return $logEntry;
        }
        self::updateLogEntryDefault($logEntry);
        return $logEntry;
    }

    /**
     * Walk / Iterate over array and update values as needed
     *
     * @param array $values Values to iterate over
     *
     * @return array
     */
    public static function updateValues(array $values)
    {
        return \array_map(static function ($val) {
            $isAbsArray = \is_array($val) && isset($val['debug']) && $val['debug'] === Abstracter::ABSTRACTION;
            if ($isAbsArray && $val['type'] === Type::TYPE_OBJECT) {
                unset($val['debug'], $val['type']);
                $valueStore = self::$debug->abstracter->abstractObject->definition->getValueStoreDefault();
                $val = new ObjectAbstraction($valueStore, $val);
            } elseif ($isAbsArray) {
                unset($val['debug']);
                $val = new Abstraction($val['type'], $val);
            }
            if ($val instanceof Abstraction) {
                return self::updateAbstraction($val);
            } elseif (\is_array($val)) {
                return self::updateValues($val);
            }
            return $val;
        }, $values);
    }

    /**
     * Update alert log entry (translate class meta to level)
     *
     * @param LogEntry $logEntry Log entry to update
     *
     * @return void
     */
    private static function updateLogEntryAlert(LogEntry $logEntry)
    {
        if ($logEntry->getMeta('class')) {
            $level = \strtr($logEntry->getMeta('class'), array(
                'danger' => 'error',
                'warning' => 'warn',
            ));
            $logEntry->setMeta('level', $level);
            $logEntry->setMeta('class', null);
            $logEntry->crate(); // removes the null meta value
        }
    }

    /**
     * Update log entry
     *
     * @param LogEntry $logEntry Log entry to update
     *
     * @return void
     */
    private static function updateLogEntryDefault(LogEntry $logEntry)
    {
        $logEntry['args'] = self::updateValues($logEntry['args']);
    }

    /**
     * Update table, trace, profile end logEntry
     *
     * @param LogEntry $logEntry Log entry to update
     *
     * @return void
     */
    private static function updateLogEntryTabular(LogEntry $logEntry)
    {
        $tableData = $logEntry['args'][0];
        $tableInfo = \array_merge(array(
            'columns' => array(),
            'rows' => array(),
        ), $logEntry->getMeta('tableInfo', array()));
        $columnKeys = \array_column($tableInfo['columns'], 'key');
        $logEntry->setMeta('tableInfo', null);
        if ($logEntry->getMeta('caption')) {
            $logEntry['args'][1] = $logEntry->getMeta('caption');
            $logEntry->setMeta('caption', null);
        }
        foreach ($tableData as $k => $row) {
            if (!empty($tableInfo['rows'][$k]['isScalar'])) {
                $tableData[$k] = array(
                    \bdk\Table\Factory::KEY_SCALAR => \reset($row),
                );
            } elseif (\count($columnKeys) === \count($row)) {
                $tableData[$k] = \array_combine($columnKeys, $row);
            }
        }
        $logEntry['args'][0] = $tableData;
        self::$debug->rootInstance->getPlugin('methodTable')->doTable($logEntry);
        $logEntry->crate();
    }

    /**
     * Update abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return Abstraction
     */
    private static function updateAbstraction(Abstraction $abs)
    {
        if ($abs->getValue('type') === Type::TYPE_OBJECT) {
            return UnserializeLogBackwardsObject::updateObjectAbstraction($abs, self::$debug);
        }
        $values = $abs->getValues();
        $values = \array_diff_assoc($values, array(
            'strlen' => null,
        ));
        $abs->setValues($values);
        return $abs;
    }
}

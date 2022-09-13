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

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;

/**
 * Base output plugin
 */
class Base extends AbstractComponent
{
    public $crateRaw = true;    // whether dump() should crate "raw" value
                                //   when processing log this is set to false
                                //   so not unecessarily re-crating arrays
    public $debug;
    protected $alertStyles = array(
        'common' => 'padding: 5px;
            line-height: 26px;
            font-size: 125%;
            font-weight: bold;',
        'error' => 'background-color: #ffbaba;
            border: 1px solid #d8000c;
            color: #d8000c;',
        'info' => 'background-color: #d9edf7;
            border: 1px solid #bce8f1;
            color: #31708f;',
        'success' => 'background-color: #dff0d8;
            border: 1px solid #d6e9c6;
            color: #3c763d;',
        'warn' => 'background-color: #fcf8e3;
            border: 1px solid #faebcc;
            color: #8a6d3b;',
    );
    protected $channelNameRoot;
    protected $substitution;
    protected $valDumper;
    private $tableInfo = array();

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->substitution = new Substitution($this);
        $this->valDumper = $this->getValDumper();
        $this->channelNameRoot = $this->debug->rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG);
    }

    /**
     * Process log entry
     *
     * Transmogrify log entry to chromelogger format
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void|string
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        if ($method === 'alert') {
            return $this->methodAlert($logEntry);
        }
        if (\in_array($method, array('group', 'groupCollapsed', 'groupEnd', 'groupSummary'), true)) {
            return $this->methodGroup($logEntry);
        }
        if (\in_array($method, array('profileEnd','table','trace'), true)) {
            return $this->methodTabular($logEntry);
        }
        return $this->methodDefault($logEntry);
    }

    /**
     * Cooerce value to string
     *
     * @param mixed $val  value
     * @param array $opts $options passed to dump
     *
     * @return string|array
     */
    public function substitutionAsString($val, $opts)
    {
        list($type, $typeMore) = $this->debug->abstracter->getType($val);
        if ($type === Abstracter::TYPE_ARRAY) {
            $count = \count($val);
            if ($count) {
                // replace with dummy array so browser console will display native Array(length)
                $val = \array_fill(0, $count, 0);
            }
            return $val;
        }
        if ($type === Abstracter::TYPE_OBJECT) {
            return (string) $val;   // __toString or className
        }
        $opts['type'] = $type;
        $opts['typeMore'] = $typeMore;
        return $this->valDumper->dump($val, $opts);
    }

    /**
     * Get value dumper
     *
     * @return \bdk\Debug\Dump\BaseValue
     */
    protected function getValDumper()
    {
        if (!$this->valDumper) {
            $this->valDumper = new BaseValue($this);
        }
        return $this->valDumper;
    }

    /**
     * Handle alert method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string|void
     */
    protected function methodAlert(LogEntry $logEntry)
    {
        $method = $logEntry->getMeta('level');
        if ($logEntry->containsSubstitutions()) {
            $logEntry['method'] = \in_array($method, array('info','success'), true)
                ? 'info'
                : 'log';
            $args = $this->substitution->process($logEntry['args']);
            foreach ($args as $i => $arg) {
                $args[$i] = $this->valDumper->dump($arg);
            }
            $logEntry['args'] = $args;
            return null;
        }
        $style = $this->alertStyles['common'] . ' ' . $this->alertStyles[$method];
        $methodMap = array(
            'error' => 'log', // Just use log method... Chrome adds backtrace to error(), which we don't want
            'info' => 'info',
            'success' => 'info',
            'warn' => 'log', // Just use log method... Chrome adds backtrace to warn(), which we don't want
        );
        $logEntry['method'] = $methodMap[$method];
        $logEntry['args'] = array(
            '%c' . $logEntry['args'][0],
            \preg_replace('/\n\s*/', ' ', $style),
        );
    }

    /**
     * Handle the "output" of most debug methods
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string|void
     */
    protected function methodDefault(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        if (\in_array($method, array('assert','clear','error','info','log','warn'), true)) {
            if ($logEntry->containsSubstitutions()) {
                $args = $this->substitution->process($args);
            }
        }
        foreach ($args as $i => $arg) {
            $args[$i] = $this->valDumper->dump($arg);
        }
        $logEntry['args'] = $args;
    }

    /**
     * Handle the "output" of group, groupCollapsed, & groupEnd
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string|void
     */
    protected function methodGroup(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        foreach ($args as $i => $arg) {
            $args[$i] = $this->valDumper->dump($arg);
        }
        $logEntry['args'] = $args;
    }

    /**
     * Normalize table data
     *
     * Ensures each row has all key/values and that they're in the same order
     * if any row is an object, each row will get a ___class_name value
     *
     * This builds table rows usable by ChromeLogger, Text, and Script
     *
     * undefinedAs values
     *   ChromeLogger: 'unset'
     *   Text:  'unset'
     *   Script: Abstracter::UNDEFINED
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string|null
     */
    protected function methodTabular(LogEntry $logEntry)
    {
        $logEntry['method'] = 'table';
        $forceArray = $logEntry->getMeta('forceArray', true);
        $undefinedAs = $logEntry->getMeta('undefinedAs', 'unset');
        $this->tableInfo = $logEntry->getMeta('tableInfo');
        $processRows = $undefinedAs !== Abstracter::UNDEFINED
            || $forceArray === false
            || $this->tableInfo['haveObjRow'];
        if (!$processRows) {
            return null;
        }
        if ($undefinedAs === 'null') {
            $undefinedAs = null;
        }
        $rows = $logEntry['args'][0];
        foreach ($rows as $rowKey => $row) {
            $rows[$rowKey] = $this->methodTabularRow($row, $rowKey, $forceArray, $undefinedAs);
        }
        $logEntry['args'] = array($rows);
        $this->tableInfo = array();
    }

    /**
     * Process table row
     *
     * @param array      $row         row
     * @param int|string $rowKey      row's key/index
     * @param bool       $forceArray  whether "scalar" rows should be wrapped in array
     * @param mixed      $undefinedAs how "undefined" should be represented
     *
     * @return array
     */
    private function methodTabularRow($row, $rowKey, $forceArray, $undefinedAs)
    {
        $rowInfo = isset($this->tableInfo['rows'][$rowKey])
            ? $this->tableInfo['rows'][$rowKey]
            : array();
        $rowInfo = \array_merge(
            array(
                'class' => null,
                'isScalar' => false,
            ),
            $rowInfo
        );
        if ($rowInfo['isScalar'] === true && $forceArray === false) {
            return \current($row);
        }
        $row = $this->methodTabularUndefinedVals($row, $undefinedAs);
        if ($rowInfo['class']) {
            $row = \array_merge(
                array('___class_name' => $rowInfo['class']),
                $row
            );
        }
        return $row;
    }

    /**
     * Set (or unset) any undefined values
     *
     * @param array       $row         row
     * @param string|null $undefinedAs value we should assign to undefined
     *
     * @return array
     */
    private function methodTabularUndefinedVals($row, $undefinedAs)
    {
        // handle undefined values
        foreach ($row as $k => $val) {
            if ($val !== Abstracter::UNDEFINED) {
                continue;
            }
            if ($undefinedAs === 'unset') {
                unset($row[$k]);
                continue;
            }
            $row[$k] = $undefinedAs;
        }
        return $row;
    }
}

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

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Component;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Base output plugin
 */
class Base extends Component
{

    public $crateRaw = true;    // whether dump() should crate "raw" value
                                //   when processing log this is set to false
                                //   so not unecessarily re-crating arrays
    public $debug;
    protected $channelNameRoot;
    protected $dumpOptions = array();
    protected $dumpOptStack = array();
    private $subInfo = array();
    private $subRegex;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->channelNameRoot = $this->debug->rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG);
        $logEntry = new LogEntry($debug, 'null');
        $this->subRegex = $logEntry->subRegex;
    }

    /**
     * Dump value
     *
     * @param mixed $val  value to dump
     * @param array $opts options & info for string values
     *
     * @return mixed
     */
    public function dump($val, $opts = array())
    {
        $opts = \array_merge(array(
            'addQuotes' => true,
            'sanitize' => true,     // only applies to html
            'type' => null,
            'typeMore' => null,
            'visualWhiteSpace' => true,
        ), $opts);
        if ($opts['type'] === null) {
            list($opts['type'], $opts['typeMore']) = $this->debug->abstracter->getType($val);
        }
        if ($opts['typeMore'] === Abstracter::TYPE_RAW) {
            if ($opts['type'] === Abstracter::TYPE_OBJECT || $this->crateRaw) {
                $val = $this->debug->abstracter->crate($val, 'dump');
            }
            $opts['typeMore'] = null;
        }
        $this->dumpOptStack[] = $opts;
        $method = 'dump' . \ucfirst($opts['type']);
        $return = $opts['typeMore'] === Abstracter::TYPE_ABSTRACTION
            ? $this->dumpAbstraction($val)
            : $this->{$method}($val);
        $this->dumpOptions = \array_pop($this->dumpOptStack);
        return $return;
    }

    /**
     * Get "option" of value being dumped
     *
     * @param string $what (optional) name of option to get (ie sanitize, type, typeMore)
     *
     * @return mixed
     */
    public function getDumpOpt($what = null)
    {
        $path = $what === null
            ? '__end__'
            : '__end__.' . $what;
        return $this->debug->arrayUtil->pathGet($this->dumpOptStack, $path);
    }

    /**
     * Extend me to format classname/constant, etc
     *
     * @param mixed $val classname or classname(::|->)name (method/property/const)
     *
     * @return string
     */
    public function markupIdentifier($val)
    {
        if ($val instanceof Abstraction) {
            $val = $val['value'];
            if (\is_array($val)) {
                $val = $val[0] . '::' . $val[1];
            }
        }
        return $val;
    }

    /**
     * Process log entry
     *
     * Transmogrify log entry to chromelogger format
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void|string
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        if ($method === 'alert') {
            return $this->methodAlert($logEntry);
        }
        if (\in_array($method, array('group', 'groupCollapsed', 'groupEnd', 'groupSummary'))) {
            return $this->methodGroup($logEntry);
        }
        if (\in_array($method, array('profileEnd','table','trace'))) {
            return $this->methodTabular($logEntry);
        }
        return $this->methodDefault($logEntry);
    }

    /**
     * Set "option" of value being dumped
     *
     * @param array|string $what name of value to set (or key/value array)
     * @param mixed        $val  value
     *
     * @return void
     */
    public function setDumpOpt($what, $val = null)
    {
        if (\is_array($what)) {
            $this->debug->arrayUtil->pathSet($this->dumpOptStack, '__end__', $what);
            return;
        }
        $this->debug->arrayUtil->pathSet(
            $this->dumpOptStack,
            '__end__.' . $what,
            $val
        );
    }

    /**
     * Is value a timestamp?
     *
     * @param mixed $val value to check
     *
     * @return string|false
     */
    protected function checkTimestamp($val)
    {
        $secs = 86400 * 90; // 90 days worth o seconds
        $tsNow = \time();
        if ($val > $tsNow - $secs && $val < $tsNow + $secs) {
            return \date('Y-m-d H:i:s T', $val);
        }
        return false;
    }

    /**
     * Dump an abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return string|null
     */
    protected function dumpAbstraction(Abstraction $abs)
    {
        $type = $abs['type'];
        $method = 'dump' . \ucfirst($type);
        $opts = $this->getDumpOpt();
        foreach (\array_keys($opts) as $k) {
            if ($abs[$k] !== null) {
                $opts[$k] = $abs[$k];
            }
        }
        if ($abs['options']) {
            $opts = \array_merge($opts, $abs['options']);
        }
        $opts['typeMore'] = $abs['typeMore'];
        $this->setDumpOpt($opts);
        if (\method_exists($this, $method) === false) {
            $event = $this->debug->publishBubbleEvent(Debug::EVENT_DUMP_CUSTOM, new Event(
                $abs,
                array(
                    'output' => $this,
                    'return' => '',
                    'typeMore' => $abs['typeMore'],
                )
            ));
            $this->setDumpOpt('typeMore', $event['typeMore']);
            return $event['return'];
        }
        $simpleTypes = array(
            Abstracter::TYPE_ARRAY,
            Abstracter::TYPE_BOOL,
            Abstracter::TYPE_FLOAT,
            Abstracter::TYPE_INT,
            Abstracter::TYPE_NULL,
            Abstracter::TYPE_STRING,
        );
        return \in_array($type, $simpleTypes)
            ? $this->{$method}($abs['value'], $abs)
            : $this->{$method}($abs);
    }

    /**
     * Dump array
     *
     * @param array $array array to dump
     *
     * @return array|string
     */
    protected function dumpArray($array)
    {
        foreach ($array as $key => $val) {
            $array[$key] = $this->dump($val);
        }
        return $array;
    }

    /**
     * Dump boolean
     *
     * @param bool $val boolean value
     *
     * @return bool|string
     */
    protected function dumpBool($val)
    {
        return $val;
    }

    /**
     * Dump callable
     *
     * @param Abstraction $abs array/callable abstraction
     *
     * @return string
     */
    protected function dumpCallable(Abstraction $abs)
    {
        return (!$abs['hideType'] ? 'callable: ' : '')
             . $this->markupIdentifier($abs);
    }

    /**
     * Dump constant
     *
     * @param Abstraction $abs constant abstraction
     *
     * @return string
     */
    protected function dumpConst(Abstraction $abs)
    {
        return $abs['name'];
    }

    /**
     * Dump float value
     *
     * @param float|int $val float value
     *
     * @return float|string
     */
    protected function dumpFloat($val)
    {
        $date = $this->checkTimestamp($val);
        return $date
            ? $val . ' (' . $date . ')'
            : $val;
    }

    /**
     * Dump integer value
     *
     * @param int $val integer value
     *
     * @return int|string
     */
    protected function dumpInt($val)
    {
        $val = $this->dumpFloat($val);
        return \is_string($val)
            ? $val
            : (int) $val;
    }

    /**
     * Dump non-inspected value (likely object)
     *
     * @return string
     */
    protected function dumpNotInspected()
    {
        return 'NOT INSPECTED';
    }

    /**
     * Dump null value
     *
     * @return null|string
     */
    protected function dumpNull()
    {
        return null;
    }

    /**
     * Dump object
     *
     * @param Abstraction $abs object abstraction
     *
     * @return string|array
     */
    protected function dumpObject(Abstraction $abs)
    {
        if ($abs['isRecursion']) {
            return '(object) ' . $abs['className'] . ' *RECURSION*';
        }
        if ($abs['isExcluded']) {
            return '(object) ' . $abs['className'] . ' NOT INSPECTED';
        }
        return array(
            '___class_name' => $abs['className'],
        ) + (array) $this->dumpProperties($abs);
    }

    /**
     * Return array of object properties (name->value)
     *
     * @param Abstraction $abs object abstraction
     *
     * @return array|string
     */
    protected function dumpProperties(Abstraction $abs)
    {
        $return = array();
        foreach ($abs['properties'] as $name => $info) {
            $vis = (array) $info['visibility'];
            foreach ($vis as $i => $v) {
                if (\in_array($v, array('magic','magic-read','magic-write'))) {
                    $vis[$i] = '✨ ' . $v;    // "sparkles": there is no magic-wand unicode char
                } elseif ($v === 'private' && $info['inheritedFrom']) {
                    $vis[$i] = '🔒 ' . $v;
                }
            }
            if ($info['debugInfoExcluded']) {
                $vis[] = 'excluded';
            }
            $name = '(' . \implode(' ', $vis) . ') ' . \str_replace('debug.', '', $name);
            $return[$name] = $this->dump($info['value']);
        }
        return $return;
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        return 'array *RECURSION*';
    }

    /**
     * Dump resource
     *
     * @param Abstraction $abs resource abstraction
     *
     * @return string
     */
    protected function dumpResource(Abstraction $abs)
    {
        return $abs['value'];
    }

    /**
     * Dump string
     *
     * @param string      $val string value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string
     */
    protected function dumpString($val, Abstraction $abs = null)
    {
        if (\is_numeric($val)) {
            $date = $this->checkTimestamp($val);
            return $date
                ? $val . ' (' . $date . ')'
                : $val;
        }
        if ($abs) {
            if ($abs['typeMore'] === Abstracter::TYPE_STRING_BINARY) {
                if (!$val) {
                    return 'Binary data not collected';
                }
            }
            $val = $this->debug->utf8->dump($val);
            $diff = $abs['strlen']
                ? $abs['strlen'] - \strlen($abs['value'])
                : 0;
            if ($diff) {
                $val .= '[' . $diff . ' more bytes (not logged)]';
            }
            return $val;
        }
        return $this->debug->utf8->dump($val);
    }

    /**
     * Dump undefined
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return Abstracter::UNDEFINED;
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
            $logEntry['method'] = \in_array($method, array('info','success'))
                ? 'info'
                : 'log';
            $args = $this->processSubstitutions($logEntry['args']);
            foreach ($args as $i => $arg) {
                $args[$i] = $this->dump($arg);
            }
            $logEntry['args'] = $args;
            return null;
        }
        $args = array('%c' . $logEntry['args'][0], '');
        $styleCommon = 'padding:5px; line-height:26px; font-size:125%; font-weight:bold;';
        switch ($method) {
            case 'error':
                // Just use log method... Chrome adds backtrace to error(), which we don't want
                $method = 'log';
                $args[1] = $styleCommon
                    . 'background-color: #ffbaba;'
                    . 'border: 1px solid #d8000c;'
                    . 'color: #d8000c;';
                break;
            case 'info':
                $args[1] = $styleCommon
                    . 'background-color: #d9edf7;'
                    . 'border: 1px solid #bce8f1;'
                    . 'color: #31708f;';
                break;
            case 'success':
                $method = 'info';
                $args[1] = $styleCommon
                    . 'background-color: #dff0d8;'
                    . 'border: 1px solid #d6e9c6;'
                    . 'color: #3c763d;';
                break;
            case 'warn':
                // Just use log method... Chrome adds backtrace to warn(), which we don't want
                $method = 'log';
                $args[1] = $styleCommon
                    . 'background-color: #fcf8e3;'
                    . 'border: 1px solid #faebcc;'
                    . 'color: #8a6d3b;';
                break;
        }
        $logEntry['method'] = $method;
        $logEntry['args'] = $args;
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
        if (\in_array($method, array('assert','clear','error','info','log','warn'))) {
            if ($logEntry->containsSubstitutions()) {
                $args = $this->processSubstitutions($args);
            }
        }
        foreach ($args as $i => $arg) {
            $args[$i] = $this->dump($arg);
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
            $args[$i] = $this->dump($arg);
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
        $tableInfo = $logEntry->getMeta('tableInfo');
        if ($undefinedAs !== Abstracter::UNDEFINED || $forceArray === false || $tableInfo['haveObjRow']) {
            $rows = $logEntry['args'][0];
            if ($undefinedAs === 'null') {
                $undefinedAs = null;
            }
            foreach ($rows as $rowKey => $row) {
                $rowInfo = isset($tableInfo['rows'][$rowKey])
                    ? $tableInfo['rows'][$rowKey]
                    : array();
                $rows[$rowKey] = $this->methodTabularRow($row, $forceArray, $undefinedAs, $rowInfo);
            }
            $logEntry['args'] = array($rows);
        }
        return null;
    }

    /**
     * Process table row
     *
     * @param array $row         row
     * @param bool  $forceArray  whether "scalar" rows should be wrapped in array
     * @param mixed $undefinedAs how "undefined" should be represented
     * @param array $rowInfo     row information (class, isScalar, etc)
     *
     * @return array
     */
    private function methodTabularRow($row, $forceArray, $undefinedAs, $rowInfo)
    {
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
        foreach ($row as $k => $val) {
            if ($val === Abstracter::UNDEFINED) {
                $row[$k] = $undefinedAs;
                if ($undefinedAs === 'unset') {
                    unset($row[$k]);
                }
            }
        }
        if ($rowInfo['class']) {
            $row = \array_merge(
                array('___class_name' => $rowInfo['class']),
                $row
            );
        }
        return $row;
    }

    /**
     * Handle the not-well documented substitutions
     *
     * @param array $args    arguments
     * @param array $options options
     *
     * @return array
     *
     * @see https://console.spec.whatwg.org/#formatter
     * @see https://developer.mozilla.org/en-US/docs/Web/API/console#Using_string_substitutions
     */
    protected function processSubstitutions($args, $options = array())
    {
        if (!\is_string($args[0])) {
            return $args;
        }
        $this->subInfo = array(
            'args' => $args,
            'index' => 0,
            'options' => \array_merge(array(
                'addQuotes' => false,
                'replace' => false, // perform substitution, or just prep?
                'sanitize' => true,
                'style' => false,   // ie support %c
            ), $options),
            'typeCounts' => \array_fill_keys(\str_split('coOdifs'), 0),
        );
        $string = \preg_replace_callback($this->subRegex, array($this, 'processSubsCallback'), $args[0]);
        $args = $this->subInfo['args'];
        if (!$this->subInfo['options']['style']) {
            $this->subInfo['typeCounts']['c'] = 0;
        }
        $hasSubs = \array_sum($this->subInfo['typeCounts']);
        if ($hasSubs && $this->subInfo['options']['replace']) {
            if ($this->subInfo['typeCounts']['c'] > 0) {
                $string .= '</span>';
            }
            $args = \array_values($args);
        }
        $args[0] = $string;
        return $args;
    }

    /**
     * Process string substitution regex callback
     *
     * @param string[] $matches regex matches array
     *
     * @return string|mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function processSubsCallback($matches)
    {
        $index = ++$this->subInfo['index'];
        $replace = $matches[0];
        if (!\array_key_exists($index, $this->subInfo['args'])) {
            return $replace;
        }
        $arg = $this->subInfo['args'][$index];
        $replacement = '';
        $type = \substr($replace, -1);
        if (\preg_match('/[difs]/', $type)) {
            if ($type === 's') {
                $arg = $this->substitutionAsString($arg, $this->subInfo['options']);
            }
            $replacement = $this->subReplacementDifs($arg, $replace);
        } elseif ($type === 'c' && $this->subInfo['options']['style']) {
            $replacement = $this->subReplacementC($arg);
        } elseif (\preg_match('/[oO]/', $type)) {
            $replacement = $this->dump($arg);
        }
        $this->subInfo['typeCounts'][$type] ++;
        if ($this->subInfo['options']['replace']) {
            unset($this->subInfo['args'][$index]);
            return $replacement;
        }
        $this->subInfo['args'][$index] = $arg;
        return $replace;
    }

    /**
     * c (css) arg replacement
     *
     * @param string $arg css string
     *
     * @return string
     */
    private function subReplacementC($arg)
    {
        $replacement = '';
        if ($this->subInfo['typeCounts']['c']) {
            // close prev
            $replacement = '</span>';
        }
        return $replacement . '<span' . $this->debug->html->buildAttribString(array(
            'style' => $arg,
        )) . '>';
    }

    /**
     * d,i,f,s arg replacement
     *
     * @param array|string $arg    replacement value
     * @param string       $format format (what's being replaced)
     *
     * @return array|string
     */
    private function subReplacementDifs($arg, $format)
    {
        $type = \substr($format, -1);
        if ($type === 'i') {
            $format = \substr_replace($format, 'd', -1, 1);
        }
        return \is_array($arg)
            ? $arg
            : \sprintf($format, $arg);
    }

    /**
     * Cooerce value to string
     *
     * @param mixed $val  value
     * @param array $opts $options passed to dump
     *
     * @return string|array
     */
    protected function substitutionAsString($val, $opts)
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
        return $this->dump($val, $opts);
    }
}

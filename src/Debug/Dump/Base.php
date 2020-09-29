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

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Component;
use bdk\Debug\LogEntry;
use bdk\Debug\Method\Table as MethodTable;
use bdk\PubSub\Event;

/**
 * Base output plugin
 */
class Base extends Component
{

    public $crateRaw = true;    // whether dump() should call crate "raw" value
                                //   when processing log this is set to false
                                //   so not unecessarily re-crating arrays
    public $debug;
    protected $channelNameRoot;
    protected $dumpType;
    protected $dumpTypeMore;
    protected $valOpts; // per-value options
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
        $this->subRegex = '/%'
        . '(?:'
        . '[coO]|'               // c: css, o: obj with max info, O: obj w generic info
        . '[+-]?'                // sign specifier
        . '(?:[ 0]|\'.{1})?'     // padding specifier
        . '-?'                   // alignment specifier
        . '\d*'                  // width specifier
        . '(?:\.\d+)?'           // precision specifier
        . '[difs]'
        . ')'
        . '/';
    }

    /**
     * Dump value
     *
     * @param mixed $val  value to dump
     * @param array $opts options for string values
     *
     * @return mixed
     */
    public function dump($val, $opts = array())
    {
        $this->valOpts = \array_merge(array(
            'addQuotes' => true,
            'sanitize' => true,     // only applies to html
            'visualWhiteSpace' => true,
        ), $opts);
        list($type, $typeMore) = $this->debug->abstracter->getType($val);
        if ($typeMore === 'raw') {
            if ($this->crateRaw) {
                $val = $this->debug->abstracter->crate($val, 'dump');
            }
            $typeMore = null;
        }
        $method = 'dump' . \ucfirst($type);
        $return = $typeMore === 'abstraction'
            ? $this->dumpAbstraction($val, $typeMore)
            : $this->{$method}($val);
        $this->dumpType = $type;
        $this->dumpTypeMore = $typeMore;
        return $return;
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
            return \date('Y-m-d H:i:s', $val);
        }
        return false;
    }

    /**
     * Do the logEntry arguments appear to have string substitutions
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return bool
     */
    protected function containsSubstitutions(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        if (\count($args) < 2 || \is_string($args[0]) === false) {
            return false;
        }
        return \preg_match($this->subRegex, $args[0]) === 1;
    }

    /**
     * Dump an abstraction
     *
     * @param Abstraction $abs      Abstraction instance
     * @param string|null $typeMore populated with "typeMore"
     *
     * @return string|null
     */
    protected function dumpAbstraction(Abstraction $abs, &$typeMore)
    {
        $type = $abs['type'];
        $method = 'dump' . \ucfirst($type);
        foreach (\array_keys($this->valOpts) as $k) {
            if ($abs[$k] !== null) {
                $this->valOpts[$k] = $abs[$k];
            }
        }
        if ($abs['options']) {
            $this->valOpts = \array_merge($this->valOpts, $abs['options']);
        }
        $typeMore = null;
        if (\method_exists($this, $method) === false) {
            $event = $this->debug->publishBubbleEvent(Debug::EVENT_DUMP_CUSTOM, new Event(
                $abs,
                array(
                    'output' => $this,
                    'return' => '',
                    'typeMore' => $abs['typeMore'],  // likely null
                )
            ));
            $typeMore = $event['typeMore'];
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
        if (\in_array($type, $simpleTypes)) {
            $typeMore = $abs['typeMore'];   // likely null
            return $this->{$method}($abs['value'], $abs);
        }
        return $this->{$method}($abs);
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
        $val = $this->debug->utf8->dump($val);
        if ($abs && $abs['strlen']) {
            $val .= '[' . ($abs['strlen'] - \strlen($val)) . ' more bytes (not logged)]';
        }
        return $val;
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
        $args = array('%c' . $logEntry['args'][0], '');
        $method = $logEntry->getMeta('level');
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
            if ($this->containsSubstitutions($logEntry)) {
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
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string|null
     */
    protected function methodTabular(LogEntry $logEntry)
    {
        $rows = $this->methodTableRows($logEntry);
        if (!$rows) {
            $logEntry['method'] = 'log';
            $caption = $logEntry->getMeta('caption');
            if ($caption) {
                \array_unshift($logEntry['args'], $caption);
            }
            return null;
        }
        $table = array();
        $classnames = array();
        $columns = $logEntry->getMeta('columns');
        $keys = $columns ?: $this->debug->methodTable->colKeys($rows);
        $undefinedAs = $logEntry->getMeta('undefinedAs', 'unset');
        $forceArray = $logEntry->getMeta('forceArray', true);
        $objInfo = array();
        foreach ($rows as $k => $row) {
            $values = $this->debug->methodTable->keyValues($row, $keys, $objInfo);
            $values = $this->methodTableCleanValues($values, array(
                'undefinedAs' => $undefinedAs,
                'forceArray' => $forceArray,
            ));
            if (\is_array($values)) {
                unset($values['__key']);
            }
            $table[$k] = $values;
            $classnames[$k] = $objInfo['row']
                ? $objInfo['row']['className']
                : '';
        }
        if (\array_filter($classnames)) {
            foreach ($classnames as $k => $classname) {
                $table[$k] = \array_merge(
                    array('___class_name' => $classname),
                    $table[$k]
                );
            }
        }
        $logEntry['method'] = 'table';
        $logEntry['args'] = array($table);
    }

    /**
     * Ready row value(s)
     *
     * @param array $values row values
     * @param array $opts   options
     *
     * @return array|mixed row values
     */
    private function methodTableCleanValues($values, $opts = array())
    {
        $opts = \array_merge(array(
            'undefinedAs' => 'unset',
            'forceArray' => true,
        ), $opts);
        $key = null;
        foreach ($values as $key => $val) {
            if ($val === Abstracter::UNDEFINED) {
                $val = $opts['undefinedAs'];
                if ($val === 'unset') {
                    unset($values[$key]);
                    continue;
                }
            }
            $values[$key] = $val;
        }
        if (\count($values) === 1 && $key === MethodTable::SCALAR) {
            $values = $opts['forceArray']
                ? array('value' => $values[$key])
                : $values[$key];
        }
        return $values;
    }

    /**
     * Get table rows
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array|false
     */
    private function methodTableRows(LogEntry $logEntry)
    {
        $rows = $logEntry['args'][0];
        $isObject = $this->debug->abstracter->isAbstraction($rows, Abstracter::TYPE_OBJECT);
        $asTable = \is_array($rows) && $rows || $isObject;
        if (!$asTable) {
            return false;
        }
        if ($isObject) {
            if ($rows['traverseValues']) {
                return $rows['traverseValues'];
            }
            return \array_map(
                function ($info) {
                    return $info['value'];
                },
                \array_filter($rows['properties'], function ($info) {
                    return $info['visibility'] === 'public';
                })
            );
        }
        return $rows;
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
        if (\strpos('difs', $type) !== false) {
            if ($type === 's') {
                $arg = $this->substitutionAsString($arg, $this->subInfo['options']);
            }
            $replacement = $this->subReplacementDifs($arg, $replace);
        } elseif ($type === 'c' && $this->subInfo['options']['style']) {
            $replacement = $this->subReplacementC($arg);
        } elseif (\strpos('oO', $type) !== false) {
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
        // function array dereferencing = php 5.4
        $type = $this->debug->abstracter->getType($val)[0];
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
        return $this->dump($val, $opts);
    }
}

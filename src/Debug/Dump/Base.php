<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\Component;
use bdk\Debug\ConfigurableInterface;
use bdk\Debug\LogEntry;
use bdk\Debug\MethodTable;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\PubSub\Event;

/**
 * Base output plugin
 */
class Base extends Component implements ConfigurableInterface
{

    public $debug;
    protected $argStringOpts;   // per-argument string options
    protected $dumpType;
    protected $dumpTypeMore;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->channelNameRoot = $this->debug->rootInstance->getCfg('channelName');
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
        $optsDefault = array(
            'addQuotes' => true,
            'sanitize' => true,     // only applies to html
            'visualWhiteSpace' => true,
        );
        if (\is_bool($opts)) {
            $keys = \array_keys($optsDefault);
            $this->argStringOpts = \array_fill_keys($keys, $opts);
        } else {
            $this->argStringOpts = \array_merge($optsDefault, $opts);
        }
        $typeMore = null;
        list($type, $typeMore) = $this->debug->abstracter->getType($val);
        if ($typeMore == 'raw') {
            $val = $this->debug->abstracter->getAbstraction($val);
            $typeMore = null;
        }
        $method = 'dump' . \ucfirst($type);
        if ($typeMore === 'abstraction') {
            foreach (\array_keys($this->argStringOpts) as $k) {
                if ($val[$k] !== null) {
                    $this->argStringOpts[$k] = $val[$k];
                }
            }
            if (!\method_exists($this, $method)) {
                $event = $this->debug->internal->publishBubbleEvent('debug.dumpCustom', new Event(
                    $val,
                    array(
                        'output' => $this,
                        'return' => '',
                        'typeMore' => null,
                    )
                ));
                $return = $event['return'];
                $typeMore = $event['typeMore'];
            } elseif (\in_array($type, array('string','bool','float','int','null'))) {
                $return = $this->{$method}($val['value']);
            } else {
                $return = $this->{$method}($val);
            }
            $typeMore = null;
        } else {
            $return = $this->{$method}($val);
        }
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
        if ($method == 'alert') {
            $this->methodAlert($logEntry);
        } elseif (\in_array($method, array('group', 'groupCollapsed', 'groupEnd'))) {
            $this->methodGroup($logEntry);
        } elseif (\in_array($method, array('profileEnd','table','trace'))) {
            $this->methodTabular($logEntry);
        } else {
            $this->methodDefault($logEntry);
        }
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
     * Dump array
     *
     * @param array $array array to dump
     *
     * @return array
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
     * @param boolean $val boolean value
     *
     * @return boolean
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
     * @param float $val float value
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
     * @param integer $val integer value
     *
     * @return integer|string
     */
    protected function dumpInt($val)
    {
        return $this->dumpFloat($val);
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
     * @return null
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
            $return = '(object) ' . $abs['className'] . ' *RECURSION*';
        } elseif ($abs['isExcluded']) {
            $return = '(object) ' . $abs['className'] . ' NOT INSPECTED';
        } else {
            $return = array(
                '___class_name' => $abs['className'],
            ) + $this->dumpProperties($abs);
        }
        return $return;
    }

    /**
     * Return array of object properties (name->value)
     *
     * @param Abstraction $abs object abstraction
     *
     * @return array
     */
    protected function dumpProperties(Abstraction $abs)
    {
        $return = array();
        foreach ($abs['properties'] as $name => $info) {
            $vis = (array) $info['visibility'];
            foreach ($vis as $i => $v) {
                if (\in_array($v, array('magic','magic-read','magic-write'))) {
                    $vis[$i] = '✨ ' . $v;    // "sparkles": there is no magic-wand unicode char
                } elseif ($v == 'private' && $info['inheritedFrom']) {
                    $vis[$i] = '🔒 ' . $v;
                }
            }
            if ($info['debugInfoExcluded']) {
                $vis[] = 'excluded';
            }
            $name = '(' . \implode(' ', $vis) . ') ' . $name;
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
     * @param string $val string value
     *
     * @return string
     */
    protected function dumpString($val)
    {
        if (\is_numeric($val)) {
            $date = $this->checkTimestamp($val);
            return $date
                ? $val . ' (' . $date . ')'
                : $val;
        } else {
            return $this->debug->utf8->dump($val);
        }
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
     * @return void
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
     * @return void
     */
    protected function methodDefault(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        if (\in_array($method, array('assert','clear','error','info','log','warn'))) {
            if (\count($args) > 1 && \is_string($args[0])) {
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
     * @return void
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
     * @return void
     */
    protected function methodTabular(LogEntry $logEntry)
    {
        $rows = $logEntry['args'][0];
        $columns = $logEntry->getMeta('columns');
        $asTable = \is_array($rows) && $rows || $this->debug->abstracter->isAbstraction($rows, 'object');
        if (!$asTable) {
            $logEntry['method'] = 'log';
            $caption = $logEntry->getMeta('caption');
            if ($caption) {
                \array_unshift($logEntry['args'], $caption);
            }
            return;
        }
        $table = array();
        $classnames = array();
        if ($this->debug->abstracter->isAbstraction($rows, 'object')) {
            if ($rows['traverseValues']) {
                $rows = $rows['traverseValues'];
            } else {
                $rows = \array_map(
                    function ($info) {
                        return $info['value'];
                    },
                    \array_filter($rows['properties'], function ($info) {
                        return !\in_array($info['visibility'], array('private', 'protected'));
                    })
                );
            }
        }
        $keys = $columns ?: $this->debug->methodTable->colKeys($rows);
        $undefinedAs = $logEntry->getMeta('undefinedAs', 'unset');
        $forceArray = $logEntry->getMeta('forceArray', true);
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
     * @return row values
     */
    private function methodTableCleanValues($values, $opts = array())
    {
        $opts = \array_merge(array(
            'undefinedAs' => 'unset',
            'forceArray' => true,
        ), $opts);
        foreach ($values as $k => $val) {
            if ($val === Abstracter::UNDEFINED) {
                if ($opts['undefinedAs'] === 'unset') {
                    unset($values[$k]);
                } else {
                    $values[$k] = $opts['undefinedAs'];
                }
            } else {
                $values[$k] = $val;
            }
        }
        if (\count($values) == 1 && $k == MethodTable::SCALAR) {
            $values = $opts['forceArray']
                ? array('value' => $values[$k])
                : $values[$k];
        }
        return $values;
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
        $index = 0;
        $typeCounts = \array_fill_keys(\str_split('coOdifs'), 0);
        $options = \array_merge(array(
            'addQuotes' => false,
            'replace' => false, // perform substitution, or just prep?
            'sanitize' => true,
            'style' => false,   // ie support %c
        ), $options);
        $subRegex = '/%'
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
        $args[0] = \preg_replace_callback($subRegex, function ($matches) use (
            &$args,
            &$hasSubs,
            &$index,
            $options,
            &$typeCounts
        ) {
            $index++;
            $replace = $matches[0];
            if (!\array_key_exists($index, $args)) {
                return $replace;
            }
            $arg = $args[$index];
            $replacement = '';
            $type = \substr($replace, -1);
            if (\strpos('difs', $type) !== false) {
                $format = $replace;
                if ($type == 'i') {
                    $format = \substr_replace($format, 'd', -1, 1);
                } elseif ($type === 's') {
                    $arg = $this->substitutionAsString($arg, $options);
                }
                $replacement = \is_array($arg)
                    ? $arg
                    : \sprintf($format, $arg);
            } elseif ($type === 'c' && $options['style']) {
                if ($typeCounts['c']) {
                    // close prev
                    $replacement = '</span>';
                }
                $replacement .= '<span' . $this->debug->utilities->buildAttribString(array(
                    'style' => $arg,
                )) . '>';
            } elseif (\strpos('oO', $type) !== false) {
                $replacement = $this->dump($arg);
            }
            $typeCounts[$type] ++;
            if ($options['replace']) {
                unset($args[$index]);
                return $replacement;
            } else {
                $args[$index] = $arg;
            }
            return $replace;
        }, $args[0]);
        if (!$options['style']) {
            $typeCounts['c'] = 0;
        }
        $hasSubs = \array_sum($typeCounts);
        if ($hasSubs && $options['replace']) {
            if ($typeCounts['c']) {
                $args[0] .= '</span>';
            }
            $args = \array_values($args);
        }
        return $args;
    }

    /**
     * Cooerce value to string
     *
     * @param mixed $val  value
     * @param array $opts $options passed to dump
     *
     * @return string
     */
    protected function substitutionAsString($val, $opts)
    {
        // function array dereferencing = php 5.4
        $type = $this->debug->abstracter->getType($val)[0];
        if ($type == 'array') {
            $count = \count($val);
            if ($count) {
                // replace with dummy array so browser console will display native Array(length)
                $val = \array_fill(0, $count, 0);
            }
        } elseif ($type == 'object') {
            $toStr = AbstractObject::toString($val);
            $val = $toStr ?: $val['className'];
        } else {
            $val = $this->dump($val, $opts);
        }
        return $val;
    }
}

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
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\LogEntry;

/**
 * Base output plugin
 */
class TextAnsi extends Text
{

    const ESCAPE_RESET = "\x00escapeReset\x00";

    protected $ansiCfg = array(
        'ansi' => 'default',    // default | true | false  (STDOUT & STDERR streams will default to true)
        'escapeCodes' => array(
            'excluded' => "\e[38;5;9m",     // red
            'false' => "\e[91m",            // red
            'keyword' => "\e[38;5;45m",     // blue
            'arrayKey' => "\e[38;5;83m",    // yellow
            'maxlen' => "\e[30;48;5;41m",   // light green background
            'muted' => "\e[38;5;250m",      // dark grey
            'numeric' => "\e[96m",          // blue
            'operator' => "\e[38;5;130m",   // green
            'punct' => "\e[38;5;245m",      // grey  (brackets)
            'property' => "\e[38;5;83m",    // yellow
            'quote' => "\e[38;5;250m",      // grey
            'true' => "\e[32m",             // green
            'recursion' => "\e[38;5;196m",  // red
        ),
        'escapeCodesLevels' => array(
            'error' => "\e[38;5;88;48;5;203;1;4m",
            'info' => "\e[38;5;55;48;5;159;1;4m",
            'success' => "\e[38;5;22;48;5;121;1;4m",
            'warn' => "\e[38;5;1;48;5;230;1;4m",
        ),
        'escapeCodesMethods' => array(
            'error' => "\e[38;5;9m",
            'info' => "\e[38;5;159m",
            'warn' => "\e[38;5;148m",
        ),
        'glue' => array(
            'multiple' => "\e[38;5;245m, \x00escapeReset\x00",
            'equal' => " \e[38;5;245m=\x00escapeReset\x00 ",
        ),
        'stream' => 'php://stderr',   // filepath/uri/resource
    );
    protected $escapeReset = "\e[0m";

    /**
     * Constructor
     *
     * @param Debug $debug Debug Instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg = $debug->arrayUtil->mergeDeep($this->cfg, $this->ansiCfg);
    }

    /**
     * Add ansi escape sequences for classname type strings
     *
     * @param mixed $val classname or classname(::|->)name (method/property/const)
     *
     * @return string
     */
    public function markupIdentifier($val)
    {
        $classname = '';
        $operator = '::';
        $identifier = '';
        $regex = '/^(.+)(::|->)(.+)$/';
        if ($val instanceof Abstraction) {
            $val = $val['value'];
        }
        $classname = $val;
        $matches = array();
        if (\is_array($val)) {
            list($classname, $identifier) = $val;
        } elseif (\preg_match($regex, $val, $matches)) {
            $classname = $matches[1];
            $operator = $matches[2];
            $identifier = $matches[3];
        }
        $operator = $this->cfg['escapeCodes']['operator'] . $operator . $this->escapeReset;
        if ($classname) {
            $idx = \strrpos($classname, '\\');
            $classname = $idx
                ? $this->cfg['escapeCodes']['muted'] . \substr($classname, 0, $idx + 1) . $this->escapeReset
                    . "\e[1m" . \substr($classname, $idx + 1) . "\e[22m"
                : "\e[1m" . $classname . "\e[22m";
        }
        if ($identifier) {
            $identifier = "\e[1m" . $identifier . "\e[22m";
        }
        $parts = \array_filter(array($classname, $identifier), 'strlen');
        return \implode($operator, $parts);
    }

    /**
     * Return log entry as text
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return string
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $escapeCode = '';
        if ($method === 'alert') {
            $level = $logEntry->getMeta('level');
            $escapeCode = $this->cfg['escapeCodesLevels'][$level];
        } elseif (isset($this->cfg['escapeCodesMethods'][$method])) {
            $escapeCode = $this->cfg['escapeCodesMethods'][$method];
        } elseif ($method === 'groupSummary' || $logEntry->getMeta('closesSummary')) {
            $escapeCode = "\e[2m";
        }
        $this->escapeReset = $escapeCode ?: "\e[0m";
        $str = parent::processLogEntry($logEntry);
        $str = \str_replace(self::ESCAPE_RESET, $this->escapeReset, $str);
        if ($str && $escapeCode) {
            $strIndent = \str_repeat('    ', $this->depth);
            $str = \preg_replace('#^(' . $strIndent . ')(.+)$#m', '$1' . $escapeCode . '$2' . "\e[0m", $str);
        }
        return $str;
    }

    /**
     * Dump array as text
     *
     * @param array $array Array to display
     *
     * @return string
     */
    protected function dumpArray($array)
    {
        $isNested = $this->valDepth > 0;
        $this->valDepth++;
        $escapeCodes = $this->cfg['escapeCodes'];
        $str = $escapeCodes['keyword'] . 'array' . $escapeCodes['punct'] . '(' . $this->escapeReset . "\n";
        foreach ($array as $k => $v) {
            $escapeKey = \is_int($k)
                ? $escapeCodes['numeric']
                : $escapeCodes['arrayKey'];
            $str .= '    '
                . $escapeCodes['punct'] . '[' . $escapeKey . $k . $escapeCodes['punct'] . ']'
                . $escapeCodes['operator'] . ' => ' . $this->escapeReset
                . $this->dump($v)
                . "\n";
        }
        $str .= $this->cfg['escapeCodes']['punct'] . ')' . $this->escapeReset;
        if (!$array) {
            $str = \str_replace("\n", '', $str);
        } elseif ($isNested) {
            $str = \str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump boolean
     *
     * @param bool $val boolean value
     *
     * @return string
     */
    protected function dumpBool($val)
    {
        return $val
            ? $this->cfg['escapeCodes']['true'] . 'true' . $this->escapeReset
            : $this->cfg['escapeCodes']['false'] . 'false' . $this->escapeReset;
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
        if ($val === Abstracter::TYPE_FLOAT_INF) {
            $val = 'INF';
        } elseif ($val === Abstracter::TYPE_FLOAT_NAN) {
            $val = 'NaN';
        }
        $val = $this->cfg['escapeCodes']['numeric'] . $val . $this->escapeReset;
        return $date
            ? '📅 ' . $val . ' ' . $this->cfg['escapeCodes']['muted'] . '(' . $date . ')' . $this->escapeReset
            : $val;
    }

    /**
     * Dump null value
     *
     * @return string
     */
    protected function dumpNull()
    {
        return $this->cfg['escapeCodes']['muted'] . 'null' . $this->escapeReset;
    }

    /**
     * Dump object as text
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObject(Abstraction $abs)
    {
        $escapeCodes = $this->cfg['escapeCodes'];
        if ($abs['isRecursion']) {
            return $escapeCodes['excluded'] . '*RECURSION*' . $this->escapeReset;
        }
        if ($abs['isExcluded']) {
            return $escapeCodes['excluded'] . 'NOT INSPECTED' . $this->escapeReset;
        }
        $isNested = $this->valDepth > 0;
        $this->valDepth++;
        $str = $this->markupIdentifier($abs['className']) . "\n"
            . $this->dumpObjectProperties($abs)
            . $this->dumpObjectMethods($abs);
        $str = \trim($str);
        if ($isNested) {
            $str = \str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump object methods as text
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string html
     */
    protected function dumpObjectMethods(Abstraction $abs)
    {
        $collectMethods = $abs['cfgFlags'] & AbstractObject::COLLECT_METHODS;
        $outputMethods = $abs['cfgFlags'] & AbstractObject::OUTPUT_METHODS;
        if (!$collectMethods || !$outputMethods) {
            return '';
        }
        $str = '';
        $counts = array(
            'public' => 0,
            'protected' => 0,
            'private' => 0,
            'magic' => 0,
        );
        foreach ($abs['methods'] as $info) {
            $counts[ $info['visibility'] ] ++;
        }
        foreach ($counts as $vis => $count) {
            if ($count > 0) {
                $str .= '    ' . $vis
                    . $this->cfg['escapeCodes']['punct'] . ':' . $this->escapeReset . ' '
                    . $this->cfg['escapeCodes']['numeric'] . $count
                    . $this->escapeReset . "\n";
            }
        }
        $header = $str
            ? "\e[4mMethods:\e[24m"
            : 'Methods: none!';
        return '  ' . $header . "\n" . $str;
    }

    /**
     * Dump object properties as text with ANSI escape codes
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObjectProperties(Abstraction $abs)
    {
        $str = '';
        if (isset($abs['methods']['__get'])) {
            $str .= '    ' . $this->cfg['escapeCodes']['muted']
                . '✨ This object has a __get() method'
                . $this->escapeReset
                . "\n";
        }
        foreach ($abs['properties'] as $name => $info) {
            $vis = $this->dumpPropVis($info);
            $str .= '    ' . $this->cfg['escapeCodes']['muted'] . '(' . $vis . ')' . $this->escapeReset
                . ' ' . $this->cfg['escapeCodes']['property'] . $name . $this->escapeReset
                . ($info['debugInfoExcluded']
                    ? ''
                    : ' '
                        . $this->cfg['escapeCodes']['operator'] . '=' . $this->escapeReset . ' '
                        . $this->dump($info['value'])
                ) . "\n";
        }
        $header = $str
            ? "\e[4mProperties:\e[24m"
            : 'Properties: none!';
        return '  ' . $header . "\n" . $str;
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        return $this->cfg['escapeCodes']['keyword'] . 'array '
            . $this->cfg['escapeCodes']['recursion'] . '*RECURSION*'
            . $this->escapeReset;
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
        $addQuotes = $this->getDumpOpt('addQuotes');
        $escapeCodes = $this->cfg['escapeCodes'];
        if (\is_numeric($val)) {
            $date = $this->checkTimestamp($val);
            $val = $escapeCodes['numeric'] . $val;
            if ($addQuotes) {
                $val = $escapeCodes['quote'] . '"'
                    . $val
                    . $escapeCodes['quote'] . '"';
            }
            $val .= $this->escapeReset;
            return $date
                ? '📅 ' . $val . ' ' . $escapeCodes['muted'] . '(' . $date . ')' . $this->escapeReset
                : $val;
        }
        $ansiQuote = $escapeCodes['quote'] . '"' . $this->escapeReset;
        $val = $this->debug->utf8->dump($val);
        if ($addQuotes) {
            $val = $ansiQuote . $val . $ansiQuote;
        }
        $diff = $abs && $abs['strlen']
            ? $abs['strlen'] - \strlen($abs['value'])
            : 0;
        if ($diff) {
            $val .= $escapeCodes['maxlen']
                . '[' . $diff . ' more bytes (not logged)]'
                . $this->escapeReset;
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
        return "\e[2mundefined\e[22m";
    }
}

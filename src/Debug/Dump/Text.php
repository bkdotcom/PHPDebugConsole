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

use bdk\Debug\LogEntry;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;

/**
 * Base output plugin
 */
class Text extends Base
{

    protected $depth = 0;   // for keeping track of indentation
    protected $cfg = array(
        'prefixes' => array(
            'error' => '⦻ ',
            'info' => 'ℹ ',
            'log' => '',
            'warn' => '⚠ ',
            'assert' => '≠ ',
            'clear' => '⌦ ',
            'count' => '✚ ',
            'countReset' => '✚ ',
            'time' => '⏱ ',
            'timeLog' => '⏱ ',
            'group' => '▸ ',
            'groupCollapsed' => '▸ ',
        ),
        'glue' => array(
            'multiple' => ', ',
            'equal' => ' = ',
        ),
    );

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
        $strIndent = \str_repeat('    ', $this->depth);
        $str = '';
        if ($method == 'alert') {
            $str = $this->methodAlert($logEntry);
        } elseif (\in_array($method, array('group','groupCollapsed'))) {
            $this->depth ++;
            $str = $this->methodGroup($logEntry);
        } elseif ($method == 'groupEnd' && $this->depth > 0) {
            if ($logEntry->getMeta('closesSummary')) {
                $str = '=======';
            } else {
                $this->depth --;
            }
        } elseif ($method == 'groupSummary') {
            $str = '=======';
        } elseif (\in_array($method, array('profileEnd','table','trace'))) {
            $str = $this->methodTabular($logEntry);
        } else {
            $str = $this->methodDefault($logEntry);
        }
        $str = \rtrim($str);
        if ($str) {
            $prefix = isset($this->cfg['prefixes'][$method])
                ? $this->cfg['prefixes'][$method]
                : '';
            $str = $prefix . $str;
            $str = $strIndent . \str_replace("\n", "\n" . $strIndent, $str) . "\n";
        }
        return $str;
    }

    /**
     * Convert all arguments to text and join them together.
     *
     * @param array $args arguments
     *
     * @return string
     */
    protected function buildArgString($args)
    {
        $numArgs = \count($args);
        foreach ($args as $i => $v) {
            $args[$i] = $this->dump($v, array(
                'addQuotes' => $i !== 0,
                'visualWhiteSpace' => $i !== 0,
            ));
            $this->valDepth = 0;
        }
        $glue = $this->cfg['glue']['multiple'];
        $glueAfterFirst = true;
        if ($numArgs && \is_string($args[0])) {
            if (\preg_match('/[=:] ?$/', $args[0])) {
                // first arg ends with "=" or ":"
                $glueAfterFirst = false;
                $args[0] = \rtrim($args[0]) . ' ';
            } elseif (\count($args) == 2) {
                $glue = $this->cfg['glue']['equal'];
            }
        }
        if (!$glueAfterFirst) {
            return $args[0] . \implode($glue, \array_slice($args, 1));
        } else {
            return \implode($glue, $args);
        }
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
        $array = parent::dumpArray($array);
        $str = \trim(\print_r($array, true));
        $str = \preg_replace('#^Array\n\(#', 'array(', $str);
        $str = \preg_replace('#^array\s*\(\s+\)#', 'array()', $str); // single-lineify empty array
        if ($isNested) {
            $str = \str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump boolean
     *
     * @param boolean $val boolean value
     *
     * @return string
     */
    protected function dumpBool($val)
    {
        return $val ? 'true' : 'false';
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
            ? '📅 ' . $val . ' (' . $date . ')'
            : $val;
    }

    /**
     * Dump object methods as text
     *
     * @param Abstraction $abs object "abstraction"
     *
     * @return string html
     */
    protected function dumpMethods(Abstraction $abs)
    {
        $collectMethods = $abs['flags'] & AbstractObject::COLLECT_METHODS;
        $outputMethods = $abs['flags'] & AbstractObject::OUTPUT_METHODS;
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
            if ($count) {
                $str .= '    ' . $vis . ': ' . $count . "\n";
            }
        }
        $header = $str
            ? 'Methods:'
            : 'Methods: none!';
        return '  ' . $header . "\n" . $str;
    }

    /**
     * Dump null value
     *
     * @return string
     */
    protected function dumpNull()
    {
        return 'null';
    }

    /**
     * Dump object as text
     *
     * @param Abstraction $abs object "abstraction"
     *
     * @return string
     */
    protected function dumpObject(Abstraction $abs)
    {
        $isNested = $this->valDepth > 0;
        $this->valDepth++;
        if ($abs['isRecursion']) {
            $str = $abs['className'] . ' *RECURSION*';
        } elseif ($abs['isExcluded']) {
            $str = $abs['className'] . ' NOT INSPECTED';
        } else {
            $str = $abs['className'] . "\n";
            $str .= $this->dumpProperties($abs);
            $str .= $this->dumpMethods($abs);
        }
        $str = \trim($str);
        if ($isNested) {
            $str = \str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump object properties as text
     *
     * @param Abstraction $abs object abstraction
     *
     * @return string
     */
    protected function dumpProperties(Abstraction $abs)
    {
        $str = '';
        $propHeader = '';
        if (isset($abs['methods']['__get'])) {
            $str .= '    ✨ This object has a __get() method' . "\n";
        }
        foreach ($abs['properties'] as $name => $info) {
            $vis = (array) $info['visibility'];
            foreach ($vis as $i => $v) {
                if (\in_array($v, array('magic','magic-read','magic-write'))) {
                    $vis[$i] = '✨ ' . $v;    // "sparkles" there is no magic-wand unicode char
                } elseif ($v == 'private' && $info['inheritedFrom']) {
                    $vis[$i] = '🔒 ' . $v;
                }
            }
            $vis = \implode(' ', $vis);
            $str .= $info['debugInfoExcluded']
                ? '    (' . $vis . ' excluded) ' . $name . "\n"
                : '    (' . $vis . ') ' . $name . ' = ' . $this->dump($info['value']) . "\n";
        }
        $propHeader = $str
            ? 'Properties:'
            : 'Properties: none!';
        return '  ' . $propHeader . "\n" . $str;
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
            $val = '"' . $val . '"';
            return $date
                ? '📅 ' . $val . ' (' . $date . ')'
                : $val;
        } else {
            $val = $this->debug->utf8->dump($val);
            if ($this->argStringOpts['addQuotes']) {
                $val = '"' . $val . '"';
            }
            return $val;
        }
    }

    /**
     * Dump undefined
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return 'undefined';
    }

    /**
     * Build Alert
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return string
     */
    protected function methodAlert(LogEntry $logEntry)
    {
        $level = $logEntry->getMeta('level');
        $levelToMethod = array(
            'error' => 'error',
            'info' => 'info',
            'success' => 'info',
            'warn' => 'warn',
        );
        $prefix = $this->cfg['prefixes'][$levelToMethod[$level]];
        $prefix = '[Alert ' . $prefix . $level . '] ';
        $wrap = array('》','《');
        return $wrap[0] . $prefix . $logEntry['args'][0] . $wrap[1];
    }

    /**
     * Build output for default/standard methods
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function methodDefault(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        if (\count($args) > 1 && \is_string($args[0])) {
            $args = $this->processSubstitutions($args, array(
                'replace' => true,
                'style' => false,
            ));
        }
        return $this->buildArgString($args);
    }

    /**
     * Build group start
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function methodGroup(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'argsAsParams' => true,
            'boldLabel' => true,
            'isFuncName' => false,
            'level' => null,
        ), $logEntry['meta']);
        $label = \array_shift($args);
        if ($meta['isFuncName']) {
            $label = $this->markupIdentifier($label);
        }
        foreach ($args as $k => $v) {
            $args[$k] = $this->dump($v);
        }
        $str = '';
        $argStr = \implode(', ', $args);
        if (!$argStr) {
            $str = $label;
        } elseif ($meta['argsAsParams']) {
            $str = $label . '(' . $argStr . ')';
        } else {
            $str = $label . ': ' . $argStr;
        }
        return $str;
    }

    /**
     * Build output for profile(End), table, & trace methods
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function methodTabular(LogEntry $logEntry)
    {
        $meta = $logEntry['meta'];
        $logEntry->setMeta('forceArray', false);
        parent::methodTabular($logEntry);
        if ($logEntry['method'] == 'table' && $meta['caption']) {
            \array_unshift($logEntry['args'], $meta['caption']);
        }
        return $this->buildArgString($logEntry['args']);
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
            $val = 'array(' . $count . ')';
        } elseif ($type == 'object') {
            $toStr = AbstractObject::toString($val);
            $val = $toStr ?: $val['className'];
        } else {
            $val = $this->dump($val, $opts);
        }
        return $val;
    }
}

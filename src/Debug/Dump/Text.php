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

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\LogEntry;

/**
 * Base output plugin
 */
class Text extends Base
{

    protected $depth = 0;   // for keeping track of indentation
    protected $cfg = array(
        'prefixes' => array(
            'assert' => '≠ ',
            'clear' => '⌦ ',
            'count' => '✚ ',
            'countReset' => '✚ ',
            'error' => '⦻ ',
            'group' => '▸ ',
            'groupCollapsed' => '▸ ',
            'info' => 'ℹ ',
            'log' => '',
            'time' => '⏱ ',
            'timeLog' => '⏱ ',
            'warn' => '⚠ ',
        ),
        'glue' => array(
            'equal' => ' = ',
            'multiple' => ', ',
        ),
    );
    protected $valDepth = 0;

    /**
     * Return log entry as text
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return string
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $str = parent::processLogEntry($logEntry);
        $str = \rtrim($str);
        if ($str) {
            $method = $logEntry['method'];
            $prefix = isset($this->cfg['prefixes'][$method])
                ? $this->cfg['prefixes'][$method]
                : '';
            $strIndent = \str_repeat('    ', $this->depth);
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
            } elseif (\count($args) === 2) {
                $glue = $this->cfg['glue']['equal'];
            }
        }
        if (!$glueAfterFirst) {
            return $args[0] . \implode($glue, \array_slice($args, 1));
        }
        return \implode($glue, $args);
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
     * @param bool $val boolean value
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
            if ($count > 0) {
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
        if ($abs['isRecursion']) {
            return $abs['className'] . ' *RECURSION*';
        }
        if ($abs['isExcluded']) {
            return $abs['className'] . ' NOT INSPECTED';
        }
        $isNested = $this->valDepth > 0;
        $this->valDepth++;
        $str = $abs['className'] . "\n"
            . $this->dumpProperties($abs)
            . $this->dumpMethods($abs);
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
            $name = \str_replace('debug.', '', $name);
            $vis = (array) $info['visibility'];
            foreach ($vis as $i => $v) {
                if (\in_array($v, array('magic','magic-read','magic-write'))) {
                    $vis[$i] = '✨ ' . $v;    // "sparkles" there is no magic-wand unicode char
                } elseif ($v === 'private' && $info['inheritedFrom']) {
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
     * @param string      $val string value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string
     */
    protected function dumpString($val, Abstraction $abs = null)
    {
        if (\is_numeric($val)) {
            $date = $this->checkTimestamp($val);
            $val = '"' . $val . '"';
            return $date
                ? '📅 ' . $val . ' (' . $date . ')'
                : $val;
        }
        $val = $this->debug->utf8->dump($val);
        if ($this->valOpts['addQuotes']) {
            $val = '"' . $val . '"';
        }
        if ($abs && $abs['strlen']) {
            $val .= '[' . ($abs['strlen'] - \strlen($abs['value'])) . ' more bytes (not logged)]';
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
        $args = $logEntry['args'];
        if ($logEntry->containsSubstitutions()) {
            $args = $this->processSubstitutions($args, array(
                'replace' => true,
                'style' => false,
            ));
            $args[0] = $this->dump($args[0], array(
                'addQuotes' => false,
            ));
        }
        return $wrap[0] . $prefix . $args[0] . $wrap[1];
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
        if ($logEntry->containsSubstitutions()) {
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
        $method = $logEntry['method'];
        if ($method === 'groupEnd') {
            if ($logEntry->getMeta('closesSummary')) {
                return '=======';
            }
            if ($this->depth > 0) {
                $this->depth --;
            }
            return;
        }
        if ($method === 'groupSummary') {
            return '=======';
        }
        $this->depth++;
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
        $argStr = \implode(', ', $args);
        if (!$argStr) {
            return $label;
        }
        if ($meta['argsAsParams']) {
            return $label . '(' . $argStr . ')';
        }
        return $label . ': ' . $argStr;
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
        if ($meta['caption']) {
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
        if ($type === Abstracter::TYPE_ARRAY) {
            $count = \count($val);
            return 'array(' . $count . ')';
        }
        if ($type === Abstracter::TYPE_OBJECT) {
            return (string) $val;   // __toString or className
        }
        return $this->dump($val, $opts);
    }
}

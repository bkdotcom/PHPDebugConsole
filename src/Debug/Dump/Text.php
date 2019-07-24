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
            $this->depth --;
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
            $str = $prefix.$str;
            $str = $strIndent.\str_replace("\n", "\n".$strIndent, $str)."\n";
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
        if ($numArgs == 1 && \is_string($args[0])) {
            $args[0] = \strip_tags($args[0]);
        }
        foreach ($args as $k => $v) {
            if ($k > 0 || !\is_string($v)) {
                $args[$k] = $this->dump($v);
            }
            $this->valDepth = 0;
        }
        $glue = ', ';
        $glueAfterFirst = true;
        if ($numArgs && \is_string($args[0])) {
            if (\preg_match('/[=:] ?$/', $args[0])) {
                // first arg ends with "=" or ":"
                $glueAfterFirst = false;
                $args[0] = \rtrim($args[0]).' ';
            } elseif (\count($args) == 2) {
                $glue = ' = ';
            }
        }
        if (!$glueAfterFirst) {
            return $args[0].\implode($glue, \array_slice($args, 1));
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
            ? '📅 '.$val.' ('.$date.')'
            : $val;
    }

    /**
     * Dump object methods as text
     *
     * @param array $methods methods as returned from getMethods
     *
     * @return string html
     */
    protected function dumpMethods($methods)
    {
        $str = '';
        if (!empty($methods)) {
            $counts = array(
                'public' => 0,
                'protected' => 0,
                'private' => 0,
                'magic' => 0,
            );
            foreach ($methods as $info) {
                $counts[ $info['visibility'] ] ++;
            }
            $str .= '  Methods:'."\n";
            foreach ($counts as $vis => $count) {
                if ($count) {
                    $str .= '    '.$vis.': '.$count."\n";
                }
            }
        } else {
            $str .= '  Methods: none!'."\n";
        }
        return $str;
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
            $str = '(object) '.$abs['className'].' *RECURSION*';
        } elseif ($abs['isExcluded']) {
            $str = '(object) '.$abs['className'].' NOT INSPECTED';
        } else {
            $str = '(object) '.$abs['className']."\n";
            $str .= $this->dumpProperties($abs);
            if ($abs['collectMethods'] && $this->debug->getCfg('outputMethods')) {
                $str .= $this->dumpMethods($abs['methods']);
            }
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
            $str .= '    ✨ This object has a __get() method'."\n";
        }
        foreach ($abs['properties'] as $name => $info) {
            $vis = (array) $info['visibility'];
            foreach ($vis as $i => $v) {
                if (\in_array($v, array('magic','magic-read','magic-write'))) {
                    $vis[$i] = '✨ '.$v;    // "sparkles" there is no magic-wand unicode char
                } elseif ($v == 'private' && $info['inheritedFrom']) {
                    $vis[$i] = '🔒 '.$v;
                }
            }
            $vis = \implode(' ', $vis);
            $str .= $info['debugInfoExcluded']
                ? '    ('.$vis.' excluded) '.$name."\n"
                : '    ('.$vis.') '.$name.' = '.$this->dump($info['value'])."\n";
        }
        $propHeader = $str
            ? 'Properties:'
            : 'Properties: none!';
        return '  '.$propHeader."\n".$str;
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
                ? '📅 "'.$val.'" ('.$date.')'
                : '"'.$val.'"';
        } else {
            return '"'.$this->debug->utf8->dump($val).'"';
        }
    }

    /**
     * Dump undefined
     *
     * @return null
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
            'danger' => 'error',
            'info' => 'info',
            'success' => 'info',
            'warning' => 'warn',
        );
        $prefix = $this->cfg['prefixes'][$levelToMethod[$level]];
        $prefix = '[Alert '.$prefix.$level.'] ';
        $wrap = array('》','《');
        return $wrap[0].$prefix.$logEntry['args'][0].$wrap[1];
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
            $hasSubs = false;
            $args = $this->processSubstitutions($args, $hasSubs);
            if ($hasSubs) {
                $args = array( \implode('', $args) );
            }
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
            $str = $label.'('.$argStr.')';
        } else {
            $str = $label.': '.$argStr;
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
        /*
        $args = $logEntry['args'];
        $asTable = \is_array($args[0]) && (bool) $args[0] || $this->debug->abstracter->isAbstraction($args[0], 'object');
        if ($asTable) {
            // $args = array($this->methodTable($args[0], $meta['columns']));
        }
        if ($meta['caption']) {
            \array_unshift($args, $meta['caption']);
        }
        return $this->buildArgString($args);
        */
        // $args = $logEntry['args'];
        $logEntry->setMeta('forceArray', false);
        parent::methodTabular($logEntry);
        if ($logEntry['method'] == 'table' && $meta['caption']) {
            \array_unshift($logEntry['args'], $meta['caption']);
        }
        return $this->buildArgString($logEntry['args']);
    }
}

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

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Dump\TextValue;
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

    /**
     * Return log entry as text
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $str = parent::processLogEntry($logEntry);
        $str = \rtrim($str ?: '');
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
     * Cooerce value to string
     *
     * @param mixed $val  value
     * @param array $opts $options passed to dump
     *
     * @return string
     */
    public function substitutionAsString($val, $opts)
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
        return $this->valDumper->dump($val, $opts);
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
        foreach ($args as $i => $v) {
            list($type, $typeMore) = $this->debug->abstracter->getType($v);
            $typeMore2 = $typeMore === Abstracter::TYPE_ABSTRACTION
                ? $v['typeMore']
                : $typeMore;
            $isNumericString = $type === Abstracter::TYPE_STRING
                && \in_array($typeMore2, array(Abstracter::TYPE_STRING_NUMERIC, Abstracter::TYPE_TIMESTAMP), true);
            $args[$i] = $this->valDumper->dump($v, array(
                'addQuotes' => $i !== 0 || $isNumericString,
                'type' => $type,
                'typeMore' => $typeMore,
                'visualWhiteSpace' => $i !== 0,
            ));
            $this->valDumper->setValDepth(0);
        }
        return $this->buildArgStringGlue($args);
    }

    /**
     * Implode/"glue" the arguments together
     *
     * @param array $args dumped arguments
     *
     * @return string
     */
    private function buildArgStringGlue($args)
    {
        $glue = $this->cfg['glue']['multiple'];
        $glueAfterFirst = true;
        $numArgs = \count($args);
        if ($numArgs > 0 && \is_string($args[0])) {
            if (\preg_match('/[=:] ?$/', $args[0])) {
                // first arg ends with "=" or ":"
                $glueAfterFirst = false;
                $args[0] = \rtrim($args[0]) . ' ';
            } elseif ($numArgs === 2) {
                $glue = $this->cfg['glue']['equal'];
            }
        }
        return $glueAfterFirst
            ? \implode($glue, $args)
            : $args[0] . \implode($glue, \array_slice($args, 1));
    }

    /**
     * Get value dumper
     *
     * @return \bdk\Debug\Dump\BaseValue
     */
    protected function getValDumper()
    {
        if (!$this->valDumper) {
            $this->valDumper = new TextValue($this);
        }
        return $this->valDumper;
    }

    /**
     * Build Alert
     *
     * @param LogEntry $logEntry LogEntry instance
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
            $args = $this->substitution->process($args, array(
                'replace' => true,
                'style' => false,
            ));
            $args[0] = $this->valDumper->dump($args[0], array(
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
            $args = $this->substitution->process($args, array(
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
            return '';
        }
        if ($method === 'groupSummary') {
            return '=======';
        }
        $this->depth++;
        return $this->methodGroupBuildOutput($logEntry);
    }

    /**
     * Build group arguments
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    private function methodGroupBuildOutput(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'argsAsParams' => true,
            'isFuncName' => false,
        ), $logEntry['meta']);
        $label = \array_shift($args);
        if ($meta['isFuncName']) {
            $label = $this->valDumper->markupIdentifier($label, true);
        }
        foreach ($args as $k => $v) {
            $args[$k] = $this->valDumper->dump($v);
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
}

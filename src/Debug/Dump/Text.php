<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Text\Value;
use bdk\Debug\LogEntry;

/**
 * Output log entries as text
 */
class Text extends Base
{
    /** @var int */
    protected $depth = 0;   // for keeping track of indentation

    /** @var array<string,mixed> */
    protected $cfg = array(
        'glue' => array(
            'equal' => ' = ',
            'multiple' => ', ',
        ),
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
     * Coerce value to string
     *
     * @param mixed $val  value
     * @param array $opts $options passed to dump
     *
     * @return string
     */
    public function substitutionAsString($val, $opts)
    {
        $type = $this->debug->abstracter->type->getType($val)[0];
        if ($type === Type::TYPE_ARRAY) {
            $count = \count($val);
            return 'array(' . $count . ')';
        }
        if ($type === Type::TYPE_OBJECT) {
            return (string) $val;   // __toString or className
        }
        return $this->valDumper->dump($val, $opts);
    }

    /**
     * Convert all arguments to text and join them together.
     *
     * @param array $args arguments
     * @param array $meta meta values
     *
     * @return string
     */
    protected function buildArgString($args, array $meta = array())
    {
        if (\count($args) === 0) {
            return '';
        }

        $meta = \array_merge(array(
            'glue' => null,
        ), $meta);

        list($glue, $glueAfterFirst) = $this->getArgGlue($args, $meta['glue']);

        if ($glueAfterFirst === false && \is_string($args[0])) {
            // first arg is not glued / don't trim Abstractions
            $args[0] = \rtrim($args[0]) . ' ';
        }

        $args = $this->buildArgStringArgs($args);
        return $glueAfterFirst
            ? \implode($glue, $args)
            : $args[0] . \implode($glue, \array_slice($args, 1));
    }

    /**
     * Return array of dumped arguments
     *
     * @param array $args arguments
     *
     * @return array
     */
    private function buildArgStringArgs(array $args)
    {
        return \array_map(function ($arg, $i) {
            list($type, $typeMore) = $this->debug->abstracter->type->getType($arg);
            $isNumericString = $type === Type::TYPE_STRING
                && \in_array($typeMore, [Type::TYPE_STRING_NUMERIC, Type::TYPE_TIMESTAMP], true);
            $dumped = $this->valDumper->dump($arg, array(
                'addQuotes' => $i !== 0 || $isNumericString || $type !== Type::TYPE_STRING,
                // 'sanitize' => $i === 0
                    // ? $meta['sanitizeFirst']
                    // : $meta['sanitize'],
                'type' => $type,
                'typeMore' => $typeMore,
                // 'visualWhiteSpace' => $i !== 0 || $type !== Type::TYPE_STRING,
            ));
            $this->valDumper->setValDepth(0);
            return $dumped;
        }, $args, \array_keys($args));
    }

    /**
     * Get argument "glue" and whether to glue after first arg
     *
     * @param array       $args     arguments
     * @param string|null $metaGlue glue specified in meta values
     *
     * @return [string, bool] glue, glueAfterFirst
     */
    private function getArgGlue(array $args, $metaGlue)
    {
        $glueDefault = $this->cfg['glue']['multiple'];
        $glueAfterFirst = true;
        $firstArgIsString = $this->debug->abstracter->type->getType($args[0])[0] === Type::TYPE_STRING;
        if ($firstArgIsString === false) {
            return [$glueDefault, $glueAfterFirst];
        }
        if (\preg_match('/[=:] ?$/', $args[0])) {
            // first arg ends with "=" or ":"
            $glueAfterFirst = false;
        } elseif (\count($args) === 2) {
            $glueDefault = $this->cfg['glue']['equal'];
        }
        $glue = $metaGlue ?: $glueDefault;
        return [$glue, $glueAfterFirst];
    }

    /**
     * Get value dumper
     *
     * @return \bdk\Debug\Dump\BaseValue
     */
    protected function getValDumper()
    {
        if (!$this->valDumper) {
            $this->valDumper = new Value($this);
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
        $wrap = array('》', '《');
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
        return $this->buildArgString($args, $logEntry['meta']);
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
                $this->depth--;
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
        $label = $meta['isFuncName']
            ? $this->valDumper->markupIdentifier($label, 'method')
            : $this->valDumper->dump($label, array('addQuotes' => false));
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
        return $this->buildArgString($logEntry['args'], $meta);
    }
}

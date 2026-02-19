<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Base\Value;
use bdk\Debug\Dump\Substitution;
use bdk\Debug\LogEntry;

/**
 * Base output plugin
 *
 * @property-read Value $valDumper
 */
class Base extends AbstractComponent
{
    /** @var bool */
    public $crateRaw = true;    // whether dump() should crate "raw" value
                                //   when processing log this is set to false
                                //   so not unnecessarily re-crating arrays

    /** @var Debug */
    public $debug;

    /**
     * Used to style console.log alerts
     *
     * @var array<string,string>
     */
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

    /** @var array<string,mixed> */
    protected $cfg = array(
        // ChromeLogger: 'unset'
        // FirePhp: null
        // Script: Abstracter::UNDEFINED
        'undefinedAs' => 'unset',
    );

    /** @var string */
    protected $channelKeyRoot;

    /** @var list<string> */
    protected $readOnly = [
        'valDumper',
    ];

    /** @var Substitution */
    protected $substitution;

    /** @var Value */
    protected $valDumper;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->substitution = new Substitution($this);
        $this->valDumper = $this->initValDumper();
        $this->channelKeyRoot = $this->debug->rootInstance->getCfg('channelKey', Debug::CONFIG_DEBUG);
    }

    /**
     * Process log entry
     *
     * Transmogrify log entry to chromeLogger format
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void|string
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $methodBuild = 'method' . \ucfirst($method);
        $meta = $logEntry->getMeta();
        $this->valDumper->optionStackPush($meta);
        if (\method_exists($this, $methodBuild)) {
            return $this->{$methodBuild}($logEntry);
        }
        if (\in_array($method, ['group', 'groupCollapsed', 'groupEnd', 'groupSummary'], true)) {
            return $this->methodGroup($logEntry);
        }
        if (\in_array($method, ['profileEnd', 'table', 'trace'], true)) {
            return $this->methodTabular($logEntry);
        }
        $return = $this->methodDefault($logEntry);
        $this->valDumper->optionStackPop();
        return $return;
    }

    /**
     * Coerce value to string
     *
     * @param mixed $val  value
     * @param array $opts $options passed to dump
     *
     * @return string|array
     */
    public function substitutionAsString($val, $opts)
    {
        list($type, $typeMore) = $this->debug->abstracter->type->getType($val);
        if ($type === Type::TYPE_ARRAY) {
            $count = \count($val);
            if ($count) {
                // replace with dummy array so browser console will display native Array(length)
                $val = \array_fill(0, $count, 0);
            }
            return $val;
        }
        if ($type === Type::TYPE_OBJECT) {
            return (string) $val;   // __toString or className
        }
        $opts['type'] = $type;
        $opts['typeMore'] = $typeMore;
        return $this->valDumper->dump($val, $opts);
    }

    /**
     * Get value dumper
     *
     * @return Value
     */
    protected function initValDumper()
    {
        $valDumper = new Value($this);
        $valDumper->setCfg($this->cfg);
        return $valDumper;
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
            $logEntry['method'] = \in_array($method, ['info', 'success'], true)
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
        $logEntry['args'] = [
            '%c' . $logEntry['args'][0],
            \preg_replace('/\n\s*/', ' ', $style),
        ];
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
        if (\in_array($method, ['assert', 'clear', 'error', 'info', 'log', 'warn'], true)) {
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
     * Handle the "output" of tabular methods: profileEnd, table, trace
     *
     * This builds table rows usable by ChromeLogger, FirePhp, Text, and Script
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    protected function methodTabular(LogEntry $logEntry)
    {
        $logEntry['method'] = 'table';
        // copy caption to meta so that script route can access it
        $logEntry->setMeta('caption', $logEntry['args'][0]['caption']);
        $this->methodDefault($logEntry);
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg(array $cfg = array(), array $prev = array())
    {
        $this->valDumper->setCfg($cfg);
    }
}

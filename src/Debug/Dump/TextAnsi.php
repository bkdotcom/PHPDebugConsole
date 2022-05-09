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

use bdk\Debug;
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

    /**
     * Constructor
     *
     * @param Debug $debug Debug Instance
     */
    public function __construct(Debug $debug)
    {
        $this->cfg = $debug->arrayUtil->mergeDeep($this->cfg, $this->ansiCfg);
        parent::__construct($debug);
    }

    /**
     * Return log entry as text
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $escapeCode = $this->logEntryEscapeCode($logEntry);
        $escapeReset = $escapeCode ?: "\e[0m";
        $this->valDumper->escapeReset = $escapeReset;
        $str = parent::processLogEntry($logEntry);
        $str = \str_replace(self::ESCAPE_RESET, $escapeReset, $str);
        if ($escapeCode) {
            $strIndent = \str_repeat('    ', $this->depth);
            $str = \preg_replace('#^(' . $strIndent . ')(.+)$#m', '$1' . $escapeCode . '$2' . "\e[0m", $str);
        }
        return $str;
    }

    /**
     * Get value dumper
     *
     * @return \bdk\Debug\Dump\BaseValue
     */
    protected function getValDumper()
    {
        if (!$this->valDumper) {
            $this->valDumper = new TextAnsiValue($this);
            $this->valDumper->setCfg($this->cfg);
        }
        return $this->valDumper;
    }

    /**
     * Get the ansi escape code for the logEntry's method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string ansi escape sequence
     */
    private function logEntryEscapeCode(LogEntry $logEntry)
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
        return $escapeCode;
    }
}

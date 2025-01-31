<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\Debug\Utility\Table as TableProcessor;
use bdk\PubSub\SubscriberInterface;

/**
 * Table method
 */
class Table implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var LogEntry */
    private $logEntry;

    /** @var string[] */
    protected $methods = [
        'table',
        'doTable',
    ];

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
    }

    /**
     * Output an array or object as a table
     *
     * Accepts array of arrays or array of objects
     *
     * Parameters:
     *   1st encountered array (or traversable) is the data
     *   2nd encountered array (optional) specifies columns to output
     *   1st encountered string is a label/caption
     *
     * @param mixed ...$arg traversable, [option array], [caption] in no particular order
     *
     * @return Debug
     *
     * @since 2.0 properly handles array of objects (objects can implement Traversable)
     * @since 2.1 properly handles Traversable as param
     */
    public function table()
    {
        if ($this->debug->getCfg('collect', Debug::CONFIG_DEBUG) === false) {
            return $this->debug;
        }
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            \func_get_args()
        );
        $this->doTable($logEntry);
        $this->debug->log($logEntry);
        return $this->debug;
    }

    /**
     * Handle table() call
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    public function doTable(LogEntry $logEntry)
    {
        $this->debug = $logEntry->getSubject();

        $cfg = $logEntry->getMeta('cfg', array());
        $cfgRestore = array();
        $maxDepth = isset($cfg['maxDepth'])
            ? $cfg['maxDepth']
            : $this->debug->getCfg('maxDepth');
        if (\in_array($maxDepth, [1, 2], true)) {
            $cfg['maxDepth'] = 3;
        }
        if ($cfg) {
            $cfgRestore = $this->debug->setCfg($cfg);
            $logEntry->setMeta('cfg', null);
        }

        $this->doTableLogEntry($logEntry);

        if ($cfgRestore) {
            $this->debug->setCfg($cfgRestore, Debug::CONFIG_NO_RETURN);
        }
    }

    /**
     * Process table log entry
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    private function doTableLogEntry(LogEntry $logEntry)
    {
        $this->initLogEntry($logEntry);
        $table = new TableProcessor(
            isset($logEntry['args'][0])
                ? $logEntry['args'][0]
                : null,
            $logEntry['meta'],
            $this->debug
        );

        if ($table->haveRows()) {
            $logEntry['args'] = [$table->getRows()];
            $logEntry['meta'] = $table->getMeta();
            return;
        }

        // no data...  create log method logEntry instead
        $logEntry['method'] = 'log';
        if ($logEntry->getMeta('caption')) {
            \array_unshift($logEntry['args'], $logEntry->getMeta('caption'));
        } elseif (\count($logEntry['args']) === 0) {
            $logEntry['args'] = ['No arguments passed to table()'];
        }
        $logEntry['meta'] = $table->getMeta();
    }

    /**
     * Find the data, caption, & columns in logEntry arguments
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    private function initLogEntry(LogEntry $logEntry)
    {
        $this->logEntry = $logEntry;
        $args = $this->logEntry['args'];
        $argCount = \count($args);
        $other = Abstracter::UNDEFINED;
        $logEntry['args'] = [];
        for ($i = 0; $i < $argCount; $i++) {
            $isOther = $this->testArg($args[$i]);
            if ($isOther && $other === Abstracter::UNDEFINED) {
                $other = $args[$i];
            }
        }
        if ($logEntry['args'] === [] && $other !== Abstracter::UNDEFINED) {
            $logEntry['args'] = [$other];
        }
    }

    /**
     * Place argument as "data", "caption", "columns", or "other"
     *
     * @param mixed $val argument value
     *
     * @return bool whether to treat the val as "other"
     */
    private function testArg($val)
    {
        if (\is_array($val)) {
            $this->testArgArray($val);
            return false;
        }
        if (\is_object($val)) {
            $this->testArgObject($val);
            return false;
        }
        if (\is_string($val) && $this->logEntry->getMeta('caption') === null) {
            $this->logEntry->setMeta('caption', $val);
            return false;
        }
        return true;
    }

    /**
     * Should array argument be treated as table data or columns?
     *
     * @param array $val table() arg of type array
     *
     * @return void
     */
    private function testArgArray($val)
    {
        if ($this->logEntry['args'] === []) {
            $this->logEntry['args'] = [$val];
        } elseif (!$this->logEntry->getMeta('columns')) {
            $this->logEntry->setMeta('columns', $val);
        }
    }

    /**
     * Should object argument be treated as table data?
     *
     * @param array $val table() arg of type object
     *
     * @return void
     */
    private function testArgObject($val)
    {
        // Traversable or other
        if ($this->logEntry['args'] === []) {
            $this->logEntry['args'] = [$val];
        }
    }
}

<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\Debug\Method\Table as TableProcessor;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Table method
 */
class Table implements SubscriberInterface
{
    use CustomMethodTrait;

    private $logEntry;

    protected $methods = array(
        'table',
        'doTable',
    );

    /**
     * Constructor
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
     * @param mixed $arg,... traversable, [option array], [caption] in no particular order
     *
     * @return $this
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
        $maxDepth = $this->debug->getCfg('maxDepth');
        if (\in_array($maxDepth, array(1, 2), true)) {
            $cfg['maxDepth'] = 3;
        }
        if ($cfg) {
            $cfgRestore = $this->debug->setCfg($cfg);
            $logEntry->setMeta('cfg', null);
        }

        $this->initLogEntry($logEntry);

        $table = new TableProcessor(
            isset($logEntry['args'][0])
                ? $logEntry['args'][0]
                : null,
            $logEntry['meta'],
            $this->debug
        );

        if ($cfgRestore) {
            $this->debug->setCfg($cfgRestore);
        }

        if ($table->haveRows()) {
            $logEntry['args'] = array($table->getRows());
            $logEntry['meta'] = $table->getMeta();
            return;
        }

        $logEntry['method'] = 'log';
        if ($logEntry->getMeta('caption')) {
            \array_unshift($logEntry['args'], $logEntry->getMeta('caption'));
        } elseif (\count($logEntry['args']) === 0) {
            $logEntry['args'] = array('No arguments passed to table()');
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
        $logEntry['args'] = array();
        for ($i = 0; $i < $argCount; $i++) {
            $isOther = $this->testArg($args[$i]);
            if ($isOther && $other === Abstracter::UNDEFINED) {
                $other = $args[$i];
            }
        }
        if ($logEntry['args'] === array() && $other !== Abstracter::UNDEFINED) {
            $logEntry['args'] = array($other);
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
        if ($this->logEntry['args'] === array()) {
            $this->logEntry['args'] = array($val);
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
        if ($this->logEntry['args'] === array()) {
            $this->logEntry['args'] = array($val);
        }
    }
}

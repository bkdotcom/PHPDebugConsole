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

namespace bdk\Debug\Method;

use bdk\Backtrace;
use bdk\Debug;
use bdk\Debug\LogEntry;

/**
 * Helper methods
 */
class Helper
{
    private $debug;

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Does alert contain substitutions
     *
     * @param array $args alert arguments
     *
     * @return bool
     */
    public function alertHasSubstitutions(array $args)
    {
        /*
            Create a temporary LogEntry so we can test if we passed substitutions
        */
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            $args
        );
        $levelsAllowed = array('danger','error','info','success','warn','warning');
        return $logEntry->containsSubstitutions()
            && \array_key_exists(1, $args)
            && \in_array($args[1], $levelsAllowed, true) === false;
    }

    /**
     * Set alert()'s alert level'\
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function alertLevel(LogEntry $logEntry)
    {
        $level = $logEntry->getMeta('level');
        $levelsAllowed = array('danger','error','info','success','warn','warning');
        // Continue to allow bootstrap "levels"
        $levelTrans = array(
            'danger' => 'error',
            'warning' => 'warn',
        );
        if (isset($levelTrans[$level])) {
            $level = $levelTrans[$level];
        } elseif (\in_array($level, $levelsAllowed, true) === false) {
            $level = 'error';
        }
        $logEntry->setMeta('level', $level);
    }

    /**
     * Handle error & warn methods
     *
     * @param string $method "error" or "warn"
     * @param array  $args   arguments passed to error or warn
     *
     * @return void
     */
    public function doError($method, $args)
    {
        $logEntry = new LogEntry(
            $this->debug,
            $method,
            $args,
            array(
                'detectFiles' => true,
                'uncollapse' => true,
            )
        );
        // file & line meta may already be set (ie coming via errorHandler)
        // file & line may also be defined as null
        $default = "\x00default\x00";
        if ($logEntry->getMeta('file', $default) === $default) {
            $callerInfo = $this->debug->backtrace->getCallerInfo();
            $logEntry->setMeta(array(
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
            ));
        }
        $this->debug->log($logEntry);
    }

    /**
     * Handle trace()
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function doTrace(LogEntry $logEntry)
    {
        $caption = $logEntry->getMeta('caption');
        if (\is_string($caption) === false) {
            $this->debug->warn(\sprintf(
                'trace caption should be a string.  %s provided',
                \is_object($caption)
                    ? \get_class($caption)
                    : \gettype($caption)
            ));
            $logEntry->setMeta('caption', 'trace');
        }
        // Get trace and include args if we're including context
        $inclContext = $logEntry->getMeta('inclContext');
        $inclArgs = $logEntry->getMeta('inclArgs');
        $backtrace = isset($logEntry['meta']['trace'])
            ? $logEntry['meta']['trace']
            : $this->debug->backtrace->get($inclArgs ? Backtrace::INCL_ARGS : 0);
        $logEntry->setMeta('trace', null);
        if ($backtrace && $inclContext) {
            $backtrace = $this->debug->backtrace->addContext($backtrace);
            $this->debug->addPlugin($this->debug->pluginHighlight);
        }
        $logEntry['args'] = array($backtrace);
        $this->debug->methodTable->doTable($logEntry);
        $this->debug->log($logEntry);
    }
}

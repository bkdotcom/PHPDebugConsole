<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\ErrorHandler\Error;
use bdk\PubSub\SubscriberInterface;

/**
 * Basic methods
 */
class Basic implements SubscriberInterface
{
    use CustomMethodTrait;

    protected $methods = array(
        'assert',
        'error',
        'info',
        'log',
        'warn',
    );

    /**
     * If first argument evaluates `false`, log the remaining paramaters
     *
     * Supports styling & substitutions
     *
     * @param bool  $assertion Any boolean expression. If the assertion is false, the message is logged
     * @param mixed $msg,...   (optional) variable num of values to output if assertion fails
     *                           if none provided, will use calling file & line num
     *
     * @return $this
     */
    public function assert($assertion, $msg = null)
    {
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            \func_get_args()
        );
        $args = $logEntry['args'];
        $assertion = \array_shift($args);
        if ($assertion) {
            return $this->debug;
        }
        if (!$args) {
            // add default message
            $callerInfo = $this->debug->backtrace->getCallerInfo();
            $args = array(
                'Assertion failed:',
                \sprintf('%s (line %s)', $callerInfo['file'], $callerInfo['line']),
            );
            $logEntry->setMeta('detectFiles', true);
        }
        $logEntry['args'] = $args;
        $this->appendLog($logEntry);
        return $this->debug;
    }

    /**
     * Log an error message.
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return $this
     */
    public function error()
    {
        $this->doError(__FUNCTION__, \func_get_args());
        return $this->debug;
    }

    /**
     * Log some informative information
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return $this
     */
    public function info()
    {
        $this->appendLog(new LogEntry(
            $this->debug,
            __FUNCTION__,
            \func_get_args()
        ));
        return $this->debug;
    }

    /**
     * Log general information
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return $this
     */
    public function log()
    {
        $args = \func_get_args();
        if (\count($args) === 1) {
            if ($args[0] instanceof LogEntry) {
                $this->appendLog($args[0]);
                return $this;
            }
            if ($args[0] instanceof Error) {
                $this->debug->rootInstance->getPlugin('internalEvents')->onError($args[0]);
                return $this;
            }
        }
        $this->appendLog(new LogEntry(
            $this->debug,
            __FUNCTION__,
            $args
        ));
        return $this->debug;
    }

    /**
     * Log a warning
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return $this
     */
    public function warn()
    {
        $this->doError(__FUNCTION__, \func_get_args());
        return $this->debug;
    }

    /**
     * Store the arguments
     * if collect is false -> does nothing
     * otherwise:
     *   + abstracts values
     *   + publishes Debug::EVENT_LOG event
     *   + appends log (if event propagation not stopped)
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return bool whether or not entry got appended
     */
    protected function appendLog(LogEntry $logEntry)
    {
        if (!$this->debug->getCfg('collect', Debug::CONFIG_DEBUG) && !$logEntry['forcePublish']) {
            return false;
        }
        $cfg = $logEntry->getMeta('cfg');
        $cfgRestore = array();
        if ($cfg) {
            $cfgRestore = $this->debug->setCfg($cfg);
            $logEntry->setMeta('cfg', null);
        }
        $logEntry->crate();
        $this->debug->publishBubbleEvent(Debug::EVENT_LOG, $logEntry);
        if ($cfgRestore) {
            $this->debug->setCfg($cfgRestore, Debug::CONFIG_NO_RETURN);
        }
        if ($logEntry['appendLog']) {
            $this->debug->data->appendLog($logEntry);
            return true;
        }
        return false;
    }

    /**
     * Handle error & warn methods
     *
     * @param string $method "error" or "warn"
     * @param array  $args   arguments passed to error or warn
     *
     * @return void
     */
    protected function doError($method, $args)
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
        // file & line meta may -already be set (ie coming via errorHandler)
        // file & line may also be defined as null
        $default = "\x00default\x00";
        if ($logEntry->getMeta('file', $default) === $default) {
            $callerInfo = $this->debug->backtrace->getCallerInfo();
            $logEntry->setMeta(array(
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
            ));
        }
        $this->appendLog($logEntry);
    }
}

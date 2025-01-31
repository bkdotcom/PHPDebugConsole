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
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Basic methods
 */
class Basic implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var resource|null */
    private $cliOutputStream = null;
    /** @var bool */
    private $isCli = false;

    /** @var string[] */
    protected $methods = [
        'assert',
        'error',
        'info',
        'log',
        'varDump',
        'warn',
    ];

    /**
     * If first argument evaluates `false`, log the remaining parameters
     *
     * Supports styling & substitutions
     *
     * @param bool  $assertion Any boolean expression. If the assertion is false, the message is logged
     * @param mixed ...$msg    (optional) variable num of values to output if assertion fails
     *                           if none provided, will use calling file & line num
     *
     * @return Debug
     *
     * 2.0 Default message used if none passed
     * 2.3 Support for substitution & formatting
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
            $fileAndLine = \sprintf('%s (line %s, eval\'d line %s)', $callerInfo['file'], $callerInfo['line'], $callerInfo['evalLine']);
            $fileAndLine = \str_replace(', eval\'d line )', ')', $fileAndLine);
            $args = [
                'Assertion failed:',
                $fileAndLine,
            ];
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
     * @param mixed ...$arg message / values
     *
     * @return Debug
     *
     * @since 3.0 first param now gets `htmlspecialchar()`'d by default
     *            use `meta('sanitizeFirst', false)` to allow html
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
     * @param mixed ...$arg message / values
     *
     * @return Debug
     *
     * @since 3.0 first param now gets `htmlspecialchar()`'d by default
     *            use `meta('sanitizeFirst', false)` to allow html
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
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
        );
    }

    /**
     * Log general information
     *
     * Supports styling & substitutions
     *
     * @param mixed ...$arg. message / values
     *
     * @return Debug
     *
     * @since 3.0 first param now gets `htmlspecialchar()`'d by default
     *            use `meta('sanitizeFirst', false)` to allow html
     */
    public function log()
    {
        $args = \func_get_args();
        if (\count($args) === 1) {
            if ($args[0] instanceof LogEntry) {
                $this->appendLog($args[0]);
                return $this->debug;
            }
            if ($args[0] instanceof Error) {
                $this->debug->rootInstance->getPlugin('internalEvents')->onError($args[0]);
                return $this->debug;
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
     * Debug::EVENT_BOOTSTRAP subscriber
     *
     * @param Event $event Debug::EVENT_BOOTSTRAP Event instance
     *
     * @return void
     */
    public function onBootstrap(Event $event)
    {
        $debug = $event->getSubject();
        $this->isCli = $debug->isCli(false); // are we a cli app?  (disregard PSR7 ServerRequest obj)
        if ($this->isCli) {
            $this->cliOutputStream = STDERR;
        }
    }

    /**
     * Dump values to output
     *
     * Similar to php's `var_dump()`.  Dump values immediately
     *
     * @param mixed ...$arg. message / values
     *
     * @return void
     *
     * @since 3.1
     */
    public function varDump()
    {
        $isCli = $this->isCli;
        $dumper = $this->debug->getDump($isCli ? 'textAnsi' : 'text');
        $args = \array_map(static function ($val) use ($dumper, $isCli) {
            $new = $dumper->valDumper->dump($val);
            if ($isCli) {
                $dumper->valDumper->escapeReset = "\e[0m";
            }
            $dumper->valDumper->setValDepth(0);
            return $new;
        }, \func_get_args());
        $glue = \func_num_args() > 2
            ? ', '
            : ' = ';
        $outStr = \implode($glue, $args);
        if ($isCli) {
            \fwrite($this->cliOutputStream, $outStr . "\n");
            return;
        }
        echo '<pre style="margin:.25em;">' . \htmlspecialchars($outStr) . '</pre>' . "\n";
    }

    /**
     * Log a warning
     *
     * Supports styling & substitutions
     *
     * @param mixed ...$arg message / values
     *
     * @return Debug
     *
     * @since 3.0 first param now gets `htmlspecialchar()`'d by default
     *            use `meta('sanitizeFirst', false)` to allow html
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
        $default = "\x00default\x00";
        $logEntry = new LogEntry(
            $this->debug,
            $method,
            $args,
            array(
                'detectFiles' => true,
                'evalLine' => null,
                'file' => $default,
                'line' => null,
                'uncollapse' => true,
            )
        );
        // file & line meta may -already be set (ie coming via errorHandler)
        // file & line may also be defined as null
        if ($logEntry->getMeta('file', $default) === $default) {
            $callerInfo = $this->debug->backtrace->getCallerInfo();
            $logEntry->setMeta(array(
                'evalLine' => $callerInfo['evalLine'],
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
            ));
        }
        $this->appendLog($logEntry);
    }
}

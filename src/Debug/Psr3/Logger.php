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

namespace bdk\Debug\Psr3;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Psr3\MethodSignatureCompatTrait;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * PSR-3 Logger implementation
 */
class Logger extends AbstractLogger
{
    // define the log method with the appropriate method signature
    use MethodSignatureCompatTrait;

    public $debug;

    protected $cfg = array(
        'levelMap' => array(
            LogLevel::EMERGENCY => 'error',
            LogLevel::ALERT => 'alert',
            LogLevel::CRITICAL => 'error',
            LogLevel::ERROR => 'error',
            LogLevel::WARNING => 'warn',
            LogLevel::NOTICE => 'warn',
            LogLevel::INFO => 'info',
            LogLevel::DEBUG => 'log',
        ),
    );

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::getInstance();
        }
        $this->debug = $debug;
        $this->cfg = $debug->arrayUtil->mergeDeep(
            $this->cfg,
            $debug->getCfg('psr3', Debug::CONFIG_DEBUG) ?: array()
        );
        $debug->backtrace->addInternalClass(array(
            'Monolog\\Logger',
            'Psr\\Log\\AbstractLogger',
        ));
    }

    /**
     * Check if level is valid
     *
     * @param string $level debug level
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertValidLevel($level)
    {
        if (\in_array($level, $this->validLevels(), true) === false) {
            throw new InvalidArgumentException(\sprintf(
                '"%s" is not a valid level',
                $level
            ));
        }
    }

    /**
     * Checkc if table data was passed in context and convert logEntry to table
     *
     * @param LogEntry $logEntry LogEntry instance
     * @param array    $context  Context values
     *
     * @return void
     */
    private function checkTableContext(LogEntry $logEntry, $context)
    {
        if (
            \in_array($logEntry['method'], array('info','log'), true)
            && isset($context['table'])
            && \is_array($context['table'])
        ) {
            /*
                context['table'] is table data
                context may contain other meta values
            */
            $caption = $logEntry['args'][0];
            $logEntry['args'] = array($context['table']);
            $logEntry['method'] = 'table';
            $logEntry->setMeta('caption', $caption);
            $meta = \array_intersect_key($context, \array_flip(array(
                'caption',
                'columns',
                'sortable',
                'totalCols',
            )));
            $logEntry->setMeta($meta);
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed              $level   debug, info, notice, warning, error, critical, alert, emergency
     * @param string|\Stringable $message message
     * @param array              $context array
     *
     * @return void
     * @throws InvalidArgumentException If invalid level.
     */
    protected function doLog($level, $message, array $context = array())
    {
        $this->assertValidLevel($level);
        /*
            Check if logging exception
        */
        if ($this->handleException($level, $context)) {
            return;
        }
        /*
            Lets create a LogEntry obj to pass around
        */
        $logEntry = new LogEntry(
            $this->debug,
            $this->cfg['levelMap'][$level],
            array(
                (string) $message,
                $context,
            ),
            array(
                'psr3level' => $level,
            )
        );
        $this->setMeta($logEntry);
        $this->setArgs($logEntry);
        $args = $logEntry['args'];
        $args[] = $this->debug->meta($logEntry['meta']);
        \call_user_func_array(
            array($logEntry->getSubject(), $logEntry['method']),
            $args
        );
    }

    /**
     * Handle as exception if Error or Exception attached to contexxt
     *
     * @param string $level   Psr3 log level
     * @param array  $context log entry context
     *
     * @return bool whether handled as exception
     */
    protected function handleException($level, $context)
    {
        if (!isset($context['exception'])) {
            return false;
        }
        if (!$this->debug->php->isThrowable($context['exception'])) {
            return false;
        }
        $fatalLevels = array(
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
        );
        if (\in_array($level, $fatalLevels, true) === false) {
            return false;
        }
        $exception = $context['exception'];
        $method = $this->cfg['levelMap'][$level];
        $this->debug->{$method}($exception);
        return true;
    }

    /**
     * Get list of valid levels
     *
     * @return array list of levels
     */
    protected function validLevels()
    {
        return array(
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        );
    }

    /**
     * Interpolates message and context.
     * Switches to table if context['table'] is set
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function setArgs(LogEntry $logEntry)
    {
        list($message, $context) = $logEntry['args'];
        $placeholders = array();
        $args = array(
            $this->debug->stringUtil->interpolate($message, $context, $placeholders),
        );
        if (\is_array($context)) {
            // remove interpolated values from context
            $context = \array_diff_key($context, \array_flip($placeholders));
            $this->checkTableContext($logEntry, $context);
            if ($logEntry['method'] === 'table') {
                return;
            }
        }
        if ($context) {
            $args[] = $context;
            $logEntry->setMeta('glue', ', ');
        }
        $logEntry['args'] = $args;
    }

    /**
     * Extract potential meta values from context array
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function setMeta(LogEntry $logEntry)
    {
        list($message, $context) = $logEntry['args'];
        $meta = \array_intersect_key($context, \array_flip(array('channel','file','line')));
        // remove meta from context
        $context = \array_diff_key($context, $meta);
        $logEntry->setMeta($meta);
        $logEntry['args'] = array(
            $message,
            $context,
        );
    }
}

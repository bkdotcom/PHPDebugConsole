<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Psr3;

use bdk\Debug;
use bdk\Debug\LogEntry;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * PSR-3 Logger implementation
 */
class Logger extends AbstractLogger
{

    public $debug;

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
            $debug = Debug::_getInstance();
        }
        $this->debug = $debug;
        $debug->backtrace->addInternalClass(array(
            'Monolog\\Logger',
            'Psr\\Log\\AbstractLogger',
        ));
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed         $level   debug, info, notice, warning, error, critical, alert, emergency
     * @param string|object $message message
     * @param array         $context array
     *
     * @return void
     * @throws InvalidArgumentException If invalid level.
     */
    public function log($level, $message, array $context = array())
    {
        if ($this->isValidLevel($level) === false) {
            throw new InvalidArgumentException();
        }
        $levelMap = array(
            LogLevel::EMERGENCY => 'error',
            LogLevel::ALERT => 'alert',
            LogLevel::CRITICAL => 'error',
            LogLevel::ERROR => 'error',
            LogLevel::WARNING => 'warn',
            LogLevel::NOTICE => 'warn',
            LogLevel::INFO => 'info',
            LogLevel::DEBUG => 'log',
        );
        /*
            Lets create a LogEntry obj to pass arround
        */
        $logEntry = new LogEntry(
            $this->debug,
            $levelMap[$level],
            array(
                $message,
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
     * Interpolates context values into the message placeholders.
     *
     * @param string|object $message message (string, or obj with __toString)
     * @param array         $context optional array of key/values
     *                                    interpolated values get removed
     *
     * @return string
     * @throws \RuntimeException if non-stringable objecct provided
     */
    protected function interpolate($message, array &$context = array())
    {
        // build a replacement array with braces around the context keys
        if (\is_object($message)) {
            if (\method_exists($message, '__toString') === false) {
                throw new \RuntimeException(__METHOD__ . ': ' . \get_class($message) . 'is not stringable');
            }
            $message = (string) $message;
        }
        $matches = array();
        \preg_match_all('/\{([a-z0-9_.]+)\}/', $message, $matches);
        $placeholders = \array_unique($matches[1]);
        $replace = array();
        foreach ($placeholders as $key) {
            if (!isset($context[$key])) {
                continue;
            }
            $val = $context[$key];
            if (\is_array($val)) {
                continue;
            }
            if (!\is_object($val) || \method_exists($val, '__toString')) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        $context = \array_diff_key($context, \array_flip($placeholders));
        return \strtr((string) $message, $replace);
    }

    /**
     * Check if level is valid
     *
     * @param string $level debug level
     *
     * @return bool
     */
    protected function isValidLevel($level)
    {
        return \in_array($level, $this->validLevels());
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
        $args = array(
            $this->interpolate($message, $context)
        );
        if (\in_array($logEntry['method'], array('info','log'))) {
            if (isset($context['table']) && \is_array($context['table'])) {
                /*
                    context['table'] is table data
                    context may contain other meta values
                */
                $args = array($context['table']);
                $logEntry['method'] = 'table';
                $logEntry->setMeta('caption', $message);
                $meta = \array_intersect_key($context, \array_flip(array(
                    'columns',
                    'sortable',
                    'totalCols',
                )));
                $logEntry->setMeta($meta);
                $context = null;
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
        $haveException = isset($context['exception'])
            && ($context['exception'] instanceof \Exception
                || PHP_VERSION_ID >= 70000 && $context['exception'] instanceof \Throwable);
        $meta = \array_intersect_key($context, \array_flip(array('channel','file','line')));
        // remove meta from context
        $context = \array_diff_key($context, $meta);
        if ($haveException) {
            $exception = $context['exception'];
            $meta = \array_merge(array(
                'backtrace' => $this->debug->backtrace->get($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ), $meta);
        }
        $logEntry->setMeta($meta);
        $logEntry['args'] = array(
            $message,
            $context,
        );
    }
}

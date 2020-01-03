<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
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
     */
    public function __construct(Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::_getInstance();
        }
        $this->debug = $debug;
        \bdk\Debug\Utilities::addCallerBreaker('class', array(
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
        if (!$this->isValidLevel($level)) {
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
        $method = $levelMap[$level];
        $str = $this->interpolate($message, $context);
        $meta = $this->getMeta($level, $context);
        $newContext = array();
        if (\in_array($method, array('info','log'))) {
            foreach ($context as $key => $value) {
                if ($key === 'table' && \is_array($value)) {
                    $method = 'table';
                    $metaMerge = \array_intersect_key($context, \array_flip(array(
                        'columns',
                        'sortable',
                        'totalCols',
                    )));
                    $meta = \array_merge($meta, $metaMerge);
                    $newContext = $value;
                    break;
                }
            }
        }
        $this->debug->{$method}($str, $newContext ?: $context, $meta);
    }

    /**
     * Extract potential meta values from $context
     *
     * @param string $level   log level
     * @param array  $context context array
     *                          meta values get removed
     *
     * @return array meta
     */
    protected function getMeta($level, &$context)
    {
        $haveException = isset($context['exception'])
            && ($context['exception'] instanceof \Exception
                || PHP_VERSION_ID >= 70000 && $context['exception'] instanceof \Throwable);
        $metaVals = \array_intersect_key($context, \array_flip(array('file','line')));
        $metaVals['psr3level'] = $level;
        // remove meta from context
        $context = \array_diff_key($context, $metaVals);
        if ($haveException) {
            $exception = $context['exception'];
            $metaVals = \array_merge(array(
                'backtrace' => $this->debug->errorHandler->backtrace($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ), $metaVals);
        }
        if (!$context) {
            $context = $this->debug->meta();
        }
        return $this->debug->meta($metaVals);
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param string|object $message message (string, or obj with __toString)
     * @param array         $context optional array of key/values
     *                                    interpolated values get removed
     *
     * @return string
     */
    protected function interpolate($message, array &$context = array())
    {
        // build a replacement array with braces around the context keys
        \preg_match_all('/\{([a-z0-9_.]+)\}/', (string) $message, $matches);
        $placeholders = \array_unique($matches[1]);
        $replace = array();
        foreach ($placeholders as $key) {
            if (!isset($context[$key])) {
                continue;
            }
            $val = $context[$key];
            if (!\is_array($val) && (!\is_object($val) || \method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        $context = \array_diff_key($context, \array_flip($placeholders));
        if (!$context) {
            $context = $this->debug->meta();
        }
        return \strtr((string) $message, $replace);
    }

    /**
     * Check if level is valid
     *
     * @param string $level debug level
     *
     * @return boolean
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
}

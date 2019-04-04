<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug;

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
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string        $level   debug, info, notice, warning, error, critical, alert, emergency
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
        $str = $this->interpolate($message, $context);
        $meta = $this->getMeta($level, $context);
        if (\in_array($level, array('emergency','critical','error'))) {
            $this->debug->error($str, $context, $meta);
        } elseif (\in_array($level, array('warning','notice'))) {
            $this->debug->warn($str, $context, $meta);
        } elseif ($level == 'alert') {
            $this->debug->alert($str);
        } elseif ($level == 'info') {
            $this->debug->info($str, $context);
        } else {
            $this->debug->log($str, $context);
        }
    }

    /**
     * Exctract potential meta values from $context
     *
     * @param string $level   log level
     * @param array  $context context array
     *                          meta values get removed
     *
     * @return array meta
     */
    protected function getMeta($level, &$context)
    {
        $haveException = isset($context['exception']) &&
            ($context['exception'] instanceof \Exception
                || PHP_VERSION_ID >= 70000 && $context['exception'] instanceof \Throwable);
        $isError = \in_array($level, array('emergency','critical','error','warning','notice'));
        $metaVals = \array_intersect_key($context, \array_flip(array('file','line')));
        $metaVals['psr3level'] = $level;
        $context = \array_diff_key($context, $metaVals);
        if ($haveException) {
            $exception = $context['exception'];
            $metaVals = \array_merge(array(
                'backtrace' => $this->debug->errorHandler->backtrace($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ), $metaVals);
        } elseif ($isError && \count($metaVals) < 2) {
            $callerInfo = $this->debug->utilities->getCallerInfo(1);
            $metaVals = \array_merge(array(
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
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
     * @param string $message message
     * @param array  $context optional array of key/values
     *                                    interpolated values get removed
     *
     * @return string
     */
    protected function interpolate($message, array &$context = array())
    {
        // build a replacement array with braces around the context keys
        \preg_match_all('/\{([a-z0-9_.]+)\}/', $message, $matches);
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
        return \strtr($message, $replace);
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

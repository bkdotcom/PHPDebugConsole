<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Time methods
 */
class Time implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var string[] */
    protected $methods = [
        'time',
        'timeEnd',
        'timeGet',
        'timeLog',
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
     * Start a timer identified by label
     *
     * ## Label passed
     *  * if doesn't exist: starts timer
     *  * if does exist: unpauses (does not reset)
     *
     * ## Label not passed
     *  * timer will be added to a no-label stack
     *
     * Does not append log (unless duration is passed).
     *
     * Use `timeEnd` or `timeGet` to get time
     *
     * @param string $label    unique label
     * @param float  $duration (optional) duration (in seconds).  Use this param to log a duration obtained externally.
     *
     * @return \bdk\Debug
     *
     * @since 3.0 duration argument added
     */
    public function time($label = null, $duration = null)
    {
        $logEntry = $this->timeLogEntry(
            __FUNCTION__,
            \func_get_args(),
            $this->debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__)
        );
        $args = $logEntry['args'];
        $floats = \array_filter($args, static function ($val) {
            return \is_float($val);
        });
        $label = \array_values(\array_diff_key($args, $floats))[0];
        if ($floats) {
            $duration = \reset($floats);
            $logEntry['args'] = [$label];
            $this->appendLogEntry($duration, $logEntry);
            return $this->debug;
        }
        $this->debug->stopWatch->start($label);
        return $this->debug;
    }

    /**
     * Behaves like a stopwatch.. logs and (optionally) returns running time
     *
     *    If label is passed, timer is "paused" (not ended/cleared)
     *    If label is not passed, timer is removed from timer stack
     *
     * Meta options
     *    precision: 4 (how many decimal places)
     *    silent: (false) only return / don't log
     *    template: '%label: %time'
     *    unit: ('auto'), 'sec', 'ms', or 'us'
     *
     * @param string      $label  (optional) unique label
     * @param bool        $log    (true) log it, or return only
     *                              if passed, takes precedence over silent meta val
     * @param bool|'auto' $return ('auto') whether to return the value (vs returning $this))
     *                              'auto' : !$log
     *
     * @return \bdk\Debug|float|false The duration (in sec).
     *
     * @psalm-return ($return is true ? float|false : \bdk\Debug)
     *
     * @since 3.0 returnOrTemplate & precision arguments removed.
     *            New meta options: precision, silent, template, & unit
     */
    public function timeEnd($label = null, $log = true, $return = 'auto')
    {
        $logEntry = $this->timeLogEntry(
            __FUNCTION__,
            \func_get_args(),
            $this->debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__)
        );
        $debug = $logEntry->getSubject();
        $label = $logEntry['args'][0];
        $elapsed = $debug->stopWatch->stop($label);
        $this->appendLogEntry($elapsed, $logEntry);
        return $logEntry['meta']['return']
            ? $elapsed
            : $debug;
    }

    /**
     * Log/get the running time without stopping/pausing the timer
     *
     * Meta options
     *    precision: 4 (how many decimal places)
     *    silent: (false) only return / don't log
     *    template: '%label: %time'
     *    unit: ('auto'), 'sec', 'ms', or 'us'
     *
     * This method does not have a web console API equivalent
     *
     * @param string      $label  (optional) unique label
     * @param bool        $log    (true) log it
     * @param bool|'auto' $return ('auto') whether to return the value (vs returning $this))
     *                              'auto' : !$log
     *
     * @return \bdk\Debug|float|false The duration (in sec).  `false` if specified label does not exist
     *
     * @psalm-return ($return is true ? float|false : \bdk\Debug)
     *
     * @since 3.0 returnOrTemplate & precision arguments removed.
     *            New meta options: precision, silent, template, & unit
     */
    public function timeGet($label = null, $log = true, $return = 'auto')
    {
        $logEntry = $this->timeLogEntry(
            __FUNCTION__,
            \func_get_args(),
            $this->debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__)
        );
        $debug = $logEntry->getSubject();
        $label = $logEntry['args'][0];
        $elapsed = $debug->stopWatch->get($label, $label);
        $this->appendLogEntry($elapsed, $logEntry);
        return $logEntry['meta']['return']
            ? $elapsed
            : $debug;
    }

    /**
     * Logs the current value of a timer that was previously started via `time()`
     *
     * also logs additional arguments
     *
     * @param string $label  (optional) unique label
     * @param mixed  ...$arg (optional) additional values to be logged with time
     *
     * @return \bdk\Debug
     *
     * @since 2.3
     */
    public function timeLog($label = null, $args = null)
    {
        $logEntry = $this->timeLogEntry(
            __FUNCTION__,
            \func_get_args(),
            $this->debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__)
        );
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        $label = $args[0];
        $elapsed = $this->debug->stopWatch->get($label, $label);
        if ($elapsed === false) {
            $this->appendNotFound($logEntry);
            return $this->debug;
        }
        $elapsed = $this->debug->utility->formatDuration($elapsed, $meta['unit'], $meta['precision']);
        $args[0] = $label . ': ';
        \array_splice($args, 1, 0, $elapsed);
        $logEntry['args'] = $args;
        $logEntry['meta'] = \array_diff_key($meta, \array_flip(['precision', 'silent', 'template', 'unit']));
        return $this->debug->log($logEntry);
    }

    /**
     * Log timeEnd() and timeGet()
     *
     * @param float|false $elapsed  elapsed time in seconds
     * @param LogEntry    $logEntry LogEntry instance
     *
     * @return void
     */
    protected function appendLogEntry($elapsed, LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $meta = $logEntry['meta'];
        if ($meta['silent']) {
            return;
        }
        if ($elapsed === false) {
            $this->appendNotFound($logEntry);
            return;
        }
        $label = isset($logEntry['args'][0])
            ? $logEntry['args'][0]
            : 'time';
        $str = \strtr($meta['template'], array(
            '%label' => $label,
            '%time' => $debug->utility->formatDuration($elapsed, $meta['unit'], $meta['precision']),
        ));
        $debug->log(new LogEntry(
            $debug,
            'time',
            [$str],
            \array_diff_key($meta, \array_flip(['precision', 'silent', 'template', 'unit']))
        ));
    }

    /**
     * Append a "label does not exist" log entry
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    protected function appendNotFound(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $label = $logEntry['args'][0];
        $meta = $logEntry['meta'];
        if ($meta['silent']) {
            return;
        }
        $debug->log(new LogEntry(
            $debug,
            $logEntry['method'],
            ['Timer \'' . $label . '\' does not exist'],
            \array_diff_key($meta, \array_flip(['precision', 'silent', 'unit']))
        ));
    }

    /**
     * Create LogEntry used by most time methods
     *
     * @param string $method      'timeEnd' or 'timeGet'
     * @param array  $args        arguments passed to method
     * @param array  $defaultArgs default argument values
     *
     * @return LogEntry
     */
    protected function timeLogEntry($method, array $args, array $defaultArgs)
    {
        $logEntry = new LogEntry(
            $this->debug,
            $method,
            $args,
            array(
                'precision' => 4,
                'silent' => false,
                'template' => '%label: %time',
                'unit' => 'auto',
            ),
            $defaultArgs,
            \array_intersect(\array_keys($defaultArgs), ['return']) // convert return to meta if it's an arg
        );
        if ($logEntry['numArgs'] === 1 && \is_bool($logEntry['args'][0])) {
            // first and only arg is bool..  treat as 'log' param
            $logEntry['args'][1] = $logEntry['args'][0];
            $logEntry['args'][0] = null;
        }
        if (isset($defaultArgs['log'])) {
            $logEntry->setMeta('silent', !$logEntry['args'][1] || $logEntry->getMeta('silent'));
            unset($logEntry['args'][1]);
        }
        if ($logEntry->getMeta('return') === 'auto') {
            $logEntry->setMeta('return', $logEntry->getMeta('silent'));
        }
        return $logEntry;
    }
}

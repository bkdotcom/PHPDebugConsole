<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Method;

use bdk\Debug\LogEntry;

/**
 * Time methods
 */
class Time
{
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
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function doTime(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $args = $logEntry['args'];
        $logEntry['meta'] = \array_merge(array(
            // these meta values are used if duration is passed
            'precision' => 4,
            'silent' => false,
            'template' => '%label: %time',
            'unit' => 'auto',
        ), $logEntry['meta']);
        $floats = \array_filter($args, function ($val) {
            return \is_float($val);
        });
        $args = \array_values(\array_diff_key($args, $floats));
        $label = $args[0];
        if ($floats) {
            $duration = \reset($floats);
            $logEntry['args'] = array($label);
            $this->appendLogEntry($duration, $logEntry);
            return;
        }
        $debug->stopWatch->start($label);
    }

    /**
     * Handle debug's timeEnd method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return float|false The duration (in sec).
     */
    public function timeEnd(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $label = $logEntry['args'][0];
        $elapsed = $debug->stopWatch->stop($label);
        $this->appendLogEntry($elapsed, $logEntry);
        return $elapsed;
    }

    /**
     * Handle debug's timeGet method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return float|false The duration (in sec).  `false` if specified label does not exist
     */
    public function timeGet(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $label = $logEntry['args'][0];
        $elapsed = $debug->stopWatch->get($label);
        if ($elapsed === false) {
            if ($logEntry->getMeta('silent') === false) {
                $debug->log(new LogEntry(
                    $debug,
                    __FUNCTION__,
                    array('Timer \'' . $label . '\' does not exist'),
                    $logEntry['meta']
                ));
            }
            return false;
        }
        $this->appendLogEntry($elapsed, $logEntry);
        return $elapsed;
    }

    /**
     * Handle debug's timeLog method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function timeLog(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        $label = $args[0];
        $elapsed = $debug->stopWatch->get($label);
        if ($elapsed === false) {
            $debug->log(new LogEntry(
                $debug,
                __FUNCTION__,
                array('Timer \'' . $label . '\' does not exist'),
                \array_diff_key($meta, \array_flip(array('precision','unit')))
            ));
            return;
        }
        $elapsed = $debug->utility->formatDuration(
            $elapsed,
            $meta['unit'],
            $meta['precision']
        );
        $args[0] = $label . ': ';
        \array_splice($args, 1, 0, $elapsed);
        $logEntry['args'] = $args;
        $logEntry['meta'] = \array_diff_key($meta, \array_flip(array('precision','unit')));
        $debug->log($logEntry);
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
        $label = isset($logEntry['args'][0])
            ? $logEntry['args'][0]
            : 'time';
        $str = $elapsed === false
            ? 'Timer \'' . $label . '\' does not exist'
            : \strtr($meta['template'], array(
                '%label' => $label,
                '%time' => $debug->utility->formatDuration($elapsed, $meta['unit'], $meta['precision']),
            ));
        $debug->log(new LogEntry(
            $debug,
            'time',
            array($str),
            \array_diff_key($meta, \array_flip(array('precision','silent','template','unit')))
        ));
    }
}

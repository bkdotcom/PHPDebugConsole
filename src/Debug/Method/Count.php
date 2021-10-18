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

use bdk\Debug;
use bdk\Debug\LogEntry;

/**
 * Count methods
 */
class Count
{

    private $counts = array();

    /**
     * Handle debug's count method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return int
     */
    public function doCount(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $args = $logEntry['args'];
        list($label, $flags) = \array_slice(\array_replace(array(null, 0), $args), 0, 2);
        // label may be ommitted and only flags passed as a single argument
        //   (excluding potential meta argument)
        if (\count($args) === 1 && \is_int($args[0])) {
            $label = null;
            $flags = $args[0];
        }
        $dataLabel = (string) $label;
        if ($label === null) {
            // determine dataLabel from calling file & line
            $callerInfo = $debug->backtrace->getCallerInfo();
            $logEntry['meta'] = \array_merge(array(
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
            ), $logEntry['meta']);
            $label = 'count';
            $dataLabel = $logEntry['meta']['file'] . ': ' . $logEntry['meta']['line'];
        }
        if (!isset($this->counts[$dataLabel])) {
            $this->counts[$dataLabel] = 0;
        }
        if (!($flags & Debug::COUNT_NO_INC)) {
            $this->counts[$dataLabel]++;
        }
        $count = $this->counts[$dataLabel];
        if (!($flags & Debug::COUNT_NO_OUT)) {
            $logEntry['args'] = array(
                (string) $label,
                $count,
            );
            $debug->log($logEntry);
        }
        return $count;
    }

    /**
     * Handle debug's countReset
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function countReset(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        list($label, $flags) = \array_slice(\array_replace(array('default', 0), $args), 0, 2);
        // label may be ommitted and only flags passed as a single argument
        //   (excluding potential meta argument)
        if (\count($args) === 1 && \is_int($args[0])) {
            $label = 'default';
            $flags = $args[0];
        }
        $logEntry['args'] = array('Counter \'' . $label . '\' doesn\'t exist.');
        if (isset($this->counts[$label])) {
            $this->counts[$label] = 0;
            $logEntry['args'] = array(
                (string) $label,
                0,
            );
        }
        if (!($flags & Debug::COUNT_NO_OUT)) {
            $debug = $logEntry->getSubject();
            $debug->log($logEntry);
        }
    }
}

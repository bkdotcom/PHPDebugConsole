<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Alert method
 */
class Alert implements SubscriberInterface
{
    use CustomMethodTrait;

    protected $levelsAllowed = array('danger', 'error', 'info', 'success', 'warn', 'warning');

    protected $methods = array(
        'alert',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Display an alert at the top of the log
     *
     * Can use styling & substitutions.
     * If using substitutions, will need to pass `$level` & `$dismissible` as meta values
     *
     * @param string $message     message to be displayed
     * @param string $level       (error), info, success, warn
     *                               "danger" and "warning" are still accepted, however deprecated
     * @param bool   $dismissible (false) Whether to display a close icon/button
     *
     * @return $this
     */
    public function alert($message, $level = 'error', $dismissible = false)
    {
        $args = \func_get_args();
        $hasSubstitutions = $this->hasSubstitutions($args);
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            $args,
            array(
                'dismissible' => false,
                'level' => 'error',
            ),
            $hasSubstitutions
                ? array()
                : $this->debug->getMethodDefaultArgs(__METHOD__),
            array('level', 'dismissible')
        );
        $logEntry['args'] = \array_values($logEntry['args']);
        $this->level($logEntry);
        $this->debug->data->set('logDest', 'alerts');
        $this->debug->log($logEntry);
        $this->debug->data->set('logDest', 'auto');
        return $this->debug;
    }

    /**
     * Does alert contain substitutions
     *
     * @param array $args alert arguments
     *
     * @return bool
     */
    private function hasSubstitutions(array $args)
    {
        /*
            Create a temporary LogEntry so we can test if we passed substitutions
        */
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            $args
        );
        return $logEntry->containsSubstitutions()
            && \array_key_exists(1, $args)
            && \in_array($args[1], $this->levelsAllowed, true) === false;
    }

    /**
     * Set alert()'s alert level'\
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function level(LogEntry $logEntry)
    {
        $level = $logEntry->getMeta('level');
        // Continue to allow bootstrap "levels"
        $levelTrans = array(
            'danger' => 'error',
            'warning' => 'warn',
        );
        if (isset($levelTrans[$level])) {
            $level = $levelTrans[$level];
        } elseif (\in_array($level, $this->levelsAllowed, true) === false) {
            $level = 'error';
        }
        $logEntry->setMeta('level', $level);
    }
}

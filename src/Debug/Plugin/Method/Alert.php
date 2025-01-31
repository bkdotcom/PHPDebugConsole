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

use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Alert method
 */
class Alert implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var array<int,string> */
    protected $levelsAllowed = ['danger', 'error', 'info', 'success', 'warn', 'warning'];

    /** @var string[] */
    protected $methods = [
        'alert',
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
     * Display an alert at the top of the log
     *
     * Two method signatures:
     *   * `alert(...$args)`
     *   * `alert(string $message, string $level = 'error', bool $dismissible = false)`
     *
     * Can use styling & substitutions.
     * If using substitutions or passing arbitrary arguments, will need to pass `$level` & `$dismissible` as meta values
     *
     * @param string $message     message to be displayed
     * @param string $level       (error), info, success, warn
     *                               "danger" and "warning" are still accepted, however deprecated
     * @param bool   $dismissible (false) Whether to display a close icon/button
     *
     * @return \bdk\Debug
     *
     * @since 2.0
     * @since 3.0 danger & warning levels replaced with error & warn
     * @since 3.3 Now accepts arbitrary arguments (like log, info, warn, & error)
     */
    public function alert($message, $level = 'error', $dismissible = false)
    {
        $args = \func_get_args();
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            $args,
            array(
                'dismissible' => false,
                'level' => 'error',
            )
        );
        $this->setArgs($logEntry);
        $this->level($logEntry);
        $this->debug->data->set('logDest', 'alerts');
        $briefBak = $this->debug->abstracter->setCfg('brief', true);
        $this->debug->log($logEntry);
        $this->debug->abstracter->setCfg('brief', $briefBak);
        $this->debug->data->set('logDest', 'auto');
        return $this->debug;
    }

    /**
     * Does alert contain substitutions
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return bool
     */
    private function hasSubstitutions(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        return $logEntry->containsSubstitutions()
            && \array_key_exists(1, $args)
            && \in_array($args[1], $this->levelsAllowed, true) === false;
    }

    /**
     * Translate deprecated level values ("danger" & "warning")
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function level(LogEntry $logEntry)
    {
        $level = $logEntry->getMeta('level');
        $levelTrans = array(
            'danger' => 'error',
            'warning' => 'warn',
        );
        if (isset($levelTrans[$level])) {
            $logEntry->setMeta('level', $levelTrans[$level]);
        }
        if (\in_array($level, $this->levelsAllowed, true)) {
            return;
        }
        $logEntry->setMeta('level', 'error');
    }

    /**
     * Alert method has multiple signatures
     * Determine which signature was used and set meta values accordingly
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function setArgs(LogEntry $logEntry)
    {
        $args = \array_replace([
            '',
            $logEntry->getMeta('level'),
            $logEntry->getMeta('dismissible'),
        ], $logEntry['args']);

        if ($this->hasSubstitutions($logEntry)) {
            return;
        }

        $isMetaSignature = \count($args) === 3
            && \in_array($args[1], $this->levelsAllowed, true)
            && \is_bool($args[2]);

        if ($isMetaSignature) {
            $logEntry->setMeta('level', $args[1]);
            $logEntry->setMeta('dismissible', $args[2]);
            unset($args[1], $args[2]);
            $logEntry['args'] = $args;
        }
    }
}

<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug;

use bdk\Debug;
use bdk\PubSub\Event;

/**
 * Clear method
 */
class MethodClear
{

    private $data;
    private $debug;
    private $channelName = null;

    /**
     * Constructor
     *
     * @param \bdk\Debug $debug Debug instance
     * @param array      $data  debug data
     */
    public function __construct(Debug $debug, &$data)
    {
        $this->debug = $debug;
        $this->data = &$data;
    }

    /**
     * Handle clear() call
     *
     * @param Event $event event object
     *
     * @return Event
     */
    public function onLog(Event $event)
    {
        $this->channelName = $this->debug->getCfg('parent')
            ? $event['meta']['channel'] // just clear this specific channel
            : null;
        $bitmask = $event['meta']['bitmask'];
        $callerInfo = $this->debug->utilities->getCallerInfo();
        $cleared = array();
        $cleared[] = $this->clearAlerts($bitmask);
        $cleared[] = $this->clearLog($bitmask);
        $cleared[] = $this->clearSummary($bitmask);
        $this->clearErrors($bitmask);
        if (($bitmask & Debug::CLEAR_ALL) === Debug::CLEAR_ALL) {
            $cleared = array('everything');
        }
        $args = $this->getLogArgs($cleared);
        $event->setValues(array(
            'method' => 'clear',
            'args' => $args,
            'meta' =>  \array_merge(array(
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
                'bitmask' => $bitmask,
                'flags' => array(
                    'alerts' => (bool) ($bitmask & Debug::CLEAR_ALERTS),
                    'log' => (bool) ($bitmask & Debug::CLEAR_LOG),
                    'logErrors' => (bool) ($bitmask & Debug::CLEAR_LOG_ERRORS),
                    'summary' => (bool) ($bitmask & Debug::CLEAR_SUMMARY),
                    'summaryErrors' => (bool) ($bitmask & Debug::CLEAR_SUMMARY_ERRORS),
                    'silent' =>  (bool) ($bitmask & Debug::CLEAR_SILENT),
                ),
            ), $event['meta']),
            'log' => !($bitmask & Debug::CLEAR_SILENT) && $args[0],
            'publish' => (bool) $args[0],
        ));
        return $event;
    }

    /**
     * Clear alerts
     *
     * @param integer $flags flags passed to clear()
     *
     * @return string|null
     */
    private function clearAlerts($flags)
    {
        $clearAlerts = $flags & Debug::CLEAR_ALERTS;
        if (!$clearAlerts) {
            return null;
        }
        if ($this->channelName) {
            foreach ($this->alerts as $i => $entry) {
                $channel = isset($entry[2]['channel']) ? $entry[2]['channel'] : null;
                if ($channel === $this->channelName) {
                    unset($this->alerts[$i]);
                }
            }
            $this->data['alerts'] = \array_values($this->data['alerts']);
        } else {
            $this->data['alerts'] = array();
        }
        return 'alerts';
    }

    /**
     * Remove error & warn from summary & log
     *
     * @param integer $flags flags passed to clear()
     *
     * @return void
     */
    private function clearErrors($flags)
    {
        $clearErrors = $flags & Debug::CLEAR_LOG_ERRORS || $flags & Debug::CLEAR_SUMMARY_ERRORS;
        if (!$clearErrors) {
            return;
        }
        $errorsNotCleared = array();
        /*
            Clear Log Errors
        */
        $errorsNotCleared = $this->clearErrorsHelper(
            $this->data['log'],
            $flags & Debug::CLEAR_LOG_ERRORS
        );
        /*
            Clear Summary Errors
        */
        foreach (\array_keys($this->data['logSummary']) as $priority) {
            $errorsNotCleared = \array_merge($this->clearErrorsHelper(
                $this->data['logSummary'][$priority],
                $flags & Debug::CLEAR_SUMMARY_ERRORS
            ));
        }
        $errorsNotCleared = \array_unique($errorsNotCleared);
        $errors = $this->debug->errorHandler->get('errors');
        foreach ($errors as $error) {
            if (!\in_array($error['hash'], $errorsNotCleared)) {
                $error['inConsole'] = false;
            }
        }
    }

    /**
     * clear errors for given log
     *
     * @param array   $log   reference to log to clear of errors
     * @param boolean $clear clear errors, or return errors?
     *
     * @return string[] array of error-hashes not cleared
     */
    private function clearErrorsHelper(&$log, $clear = true)
    {
        $errorsNotCleared = array();
        foreach ($log as $k => $entry) {
            if (!\in_array($entry[0], array('error','warn'))) {
                continue;
            }
            $clear2 = $clear;
            if ($this->channelName) {
                $channel = isset($entry[2]['channel']) ? $entry[2]['channel'] : null;
                $clear2 = $clear && $channel === $this->channelName;
            }
            if ($clear2) {
                unset($log[$k]);
            } elseif (isset($entry[2]['errorHash'])) {
                $errorsNotCleared[] = $entry[2]['errorHash'];
            }
        }
        $log = \array_values($log);
        return $errorsNotCleared;
    }

    /**
     * Clear log entries
     *
     * @param integer $flags flags passed to clear()
     *
     * @return string|null
     */
    private function clearLog($flags)
    {
        $return = null;
        $clearErrors = $flags & Debug::CLEAR_LOG_ERRORS;
        if ($flags & Debug::CLEAR_LOG) {
            $return = 'log ('.($clearErrors ? 'incl errors' : 'sans errors').')';
            $curDepth = \array_sum(\array_column($this->data['groupStacks']['main'], 'collect'));
            $entriesKeep = $this->debug->internal->getCurrentGroups($this->data['log'], $curDepth);
            $this->clearLogHelper($this->data['log'], $clearErrors, $entriesKeep);
        } elseif ($clearErrors) {
            $return = 'errors';
        }
        return $return;
    }

    /**
     * [clearLogHelper description]
     *
     * @param array   $log         log to clear (passed by reference)
     * @param boolean $clearErrors whether or not to clear errors
     * @param array   $entriesKeep log entries to keep
     *
     * @return void
     */
    private function clearLogHelper(&$log, $clearErrors = false, $entriesKeep = array())
    {
        $keep = $clearErrors
            ? array()
            : array('error','warn');
        if ($keep || $this->channelName) {
            // we need to go through and filter based on method and/or channel
            foreach ($log as $k => $entry) {
                $channel = isset($entry[2]['channel']) ? $entry[2]['channel'] : null;
                $channelMatch = !$this->channelName || $channel === $this->channelName;
                if (\in_array($entry[0], $keep) || !$channelMatch) {
                    $entriesKeep[$k] = $entry;
                }
            }
        }
        \ksort($entriesKeep);
        $log = \array_values($entriesKeep);
    }

    /**
     * Clear summary entries
     *
     * @param integer $flags flags passed to clear()
     *
     * @return string|null
     */
    private function clearSummary($flags)
    {
        $return = null;
        $clearErrors = $flags & Debug::CLEAR_SUMMARY_ERRORS;
        if ($flags & Debug::CLEAR_SUMMARY) {
            $return = 'summary ('.($clearErrors ? 'incl errors' : 'sans errors').')';
            $curPriority = \end($this->data['groupPriorityStack']);  // false if empty
            foreach (\array_keys($this->data['logSummary']) as $priority) {
                $entriesKeep = array();
                if ($priority === $curPriority) {
                    $curDepth = \array_sum(\array_column($this->data['groupStacks'][$priority], 'collect'));
                    $entriesKeep = $this->debug->internal->getCurrentGroups(
                        $this->data['logSummary'][$priority],
                        $curDepth
                    );
                } else {
                    $this->data['groupStacks'][$priority] = array();
                }
                $this->clearLogHelper($this->data['logSummary'][$priority], $clearErrors, $entriesKeep);
            }
        } elseif ($clearErrors) {
            $return = 'summary errors';
        }
        return $return;
    }

    /**
     * Build message that gets appended to log
     *
     * @param array $cleared array of things that were cleared
     *
     * @return string
     */
    private function getLogArgs($cleared)
    {
        $cleared = \array_filter($cleared);
        $count = \count($cleared);
        $glue = $count == 2
            ? ' and '
            : ', ';
        if ($count > 2) {
            $cleared[$count-1] = 'and '.$cleared[$count-1];
        }
        $msg = $cleared
            ? 'Cleared '.\implode($glue, $cleared)
            : '';
        if ($this->channelName) {
            return array(
                $msg.' %c(%s)',
                'background-color:#c0c0c0; padding:0 .33em;',
                $this->channelName,
            );
        }
        return array($msg);
    }
}

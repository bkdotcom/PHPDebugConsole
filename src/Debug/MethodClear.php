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
use bdk\PubSub\Event;

/**
 * Clear method
 */
class MethodClear
{

    private $data;
    private $debug;
    private $channelName = null;
    private $channelRegex;
    private $isRootInstance = false;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     * @param array $data  debug data
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
        $this->channelName = $this->debug->parentInstance
            ? $event['meta']['channel'] // just clear this specific channel
            : null;
        $this->channelRegex = '#^'.\preg_quote($this->channelName, '#').'(\.|$)#';
        $this->isRootInstance = $this->debug->rootInstance === $this->debug;
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
            'appendLog' => $args && !($bitmask & Debug::CLEAR_SILENT),
            'publish' => (bool) $args,
        ));
        return $event;
    }

    /**
     * Test channel for inclussion
     *
     * @param array $logEntry log entry
     *
     * @return boolean
     */
    private function channelTest($logEntry)
    {
        $channelName = isset($logEntry[2]['channel']) ? $logEntry[2]['channel'] : null;
        return $this->isRootInstance || \preg_match($this->channelRegex, $channelName);
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
            foreach ($this->data['alerts'] as $i => $logEntry) {
                if ($this->channelTest($logEntry)) {
                    unset($this->data['alerts'][$i]);
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
        foreach ($log as $k => $logEntry) {
            if (!\in_array($logEntry[0], array('error','warn'))) {
                continue;
            }
            $clear2 = $clear;
            if ($this->channelName) {
                $channelName = isset($logEntry[2]['channel']) ? $logEntry[2]['channel'] : null;
                $clear2 = $clear && $channelName === $this->channelName;
            }
            if ($clear2) {
                unset($log[$k]);
            } elseif (isset($logEnntry[2]['errorHash'])) {
                $errorsNotCleared[] = $logEntry[2]['errorHash'];
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
            $curDepth = 0;
            foreach ($this->data['groupStacks']['main'] as $group) {
                $curDepth += (int) $group['collect'];
            }
            $entriesKeep = $this->debug->internal->getCurrentGroups($this->data['log'], $curDepth);
            $this->clearLogHelper($this->data['log'], $clearErrors, $entriesKeep);
        } elseif ($clearErrors) {
            $return = 'errors';
        }
        return $return;
    }

    /**
     * Clear log data
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
            foreach ($log as $k => $logEntry) {
                $channelName = isset($logEntry[2]['channel']) ? $logEntry[2]['channel'] : null;
                $channelMatch = !$this->channelName || $channelName === $this->channelName;
                if (\in_array($logEntry[0], $keep) || !$channelMatch) {
                    $entriesKeep[$k] = $logEntry;
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
                    $curDepth = 0;
                    foreach ($this->data['groupStacks'][$priority] as $group) {
                        $curDepth += (int) $group['collect'];
                    }
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
        if (!$cleared) {
            return array();
        }
        $count = \count($cleared);
        $glue = $count == 2
            ? ' and '
            : ', ';
        if ($count > 2) {
            $cleared[$count-1] = 'and '.$cleared[$count-1];
        }
        $msg = 'Cleared '.\implode($glue, $cleared);
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

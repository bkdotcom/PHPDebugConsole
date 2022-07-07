<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Method;

use bdk\Debug;
use bdk\Debug\LogEntry;

/**
 * Clear method
 */
class Clear
{
    private $data = array();
    private $debug;
    private $channelName = null;
    private $channelRegex;
    private $isRootInstance = false;

    /**
     * Handle clear() call
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function doClear(LogEntry $logEntry)
    {
        $this->debug = $logEntry->getSubject();
        $this->data = $this->debug->data->get();
        $this->channelName = $this->debug->parentInstance
            ? $logEntry->getChannelName() // just clear this specific channel
            : null;
        $this->channelRegex = '#^' . \preg_quote($this->channelName ?: '', '#') . '(\.|$)#';
        $this->isRootInstance = $this->debug->rootInstance === $this->debug;
        $bitmask = $logEntry['meta']['bitmask'];
        if ($bitmask === Debug::CLEAR_SILENT) {
            $bitmask = Debug::CLEAR_LOG | Debug::CLEAR_SILENT;
        }
        $cleared = array();
        $cleared[] = $this->clearAlerts($bitmask);
        $cleared[] = $this->clearLog($bitmask);
        $cleared[] = $this->clearSummary($bitmask);
        $this->clearErrors($bitmask);
        if (($bitmask & Debug::CLEAR_ALL) === Debug::CLEAR_ALL) {
            $cleared = array('everything');
        }
        $args = $this->getLogArgs($cleared);
        $this->debug->data->set($this->data);
        $this->data = array();
        $this->updateLogEntry($logEntry, $args);
    }

    /**
     * Test channel for inclussion
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return bool
     */
    private function channelTest(LogEntry $logEntry)
    {
        return $this->isRootInstance || \preg_match($this->channelRegex, $logEntry->getChannelName());
    }

    /**
     * Clear alerts
     *
     * @param int $flags flags passed to clear()
     *
     * @return string|null
     */
    private function clearAlerts($flags)
    {
        $clearAlerts = (bool) ($flags & Debug::CLEAR_ALERTS);
        if (!$clearAlerts) {
            return null;
        }
        if ($this->channelName === null) {
            $this->data['alerts'] = array();
            return 'alerts';
        }
        foreach ($this->data['alerts'] as $i => $logEntry) {
            if ($this->channelTest($logEntry)) {
                unset($this->data['alerts'][$i]);
            }
        }
        $this->data['alerts'] = \array_values($this->data['alerts']);
        return 'alerts';
    }

    /**
     * Remove error & warn from summary & log
     *
     * @param int $flags flags passed to clear()
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
            (bool) ($flags & Debug::CLEAR_LOG_ERRORS)
        );
        /*
            Clear Summary Errors
        */
        foreach (\array_keys($this->data['logSummary']) as $priority) {
            $errorsNotCleared = \array_merge($this->clearErrorsHelper(
                $this->data['logSummary'][$priority],
                (bool) ($flags & Debug::CLEAR_SUMMARY_ERRORS)
            ));
        }
        $errorsNotCleared = \array_unique($errorsNotCleared);
        $errors = $this->debug->errorHandler->get('errors');
        foreach ($errors as $error) {
            if (\in_array($error['hash'], $errorsNotCleared, true) === false) {
                $error['inConsole'] = false;
            }
        }
    }

    /**
     * clear errors for given log
     *
     * @param array $log   reference to log to clear of errors
     * @param bool  $clear clear errors, or return errors?
     *
     * @return string[] array of error-hashes not cleared
     */
    private function clearErrorsHelper(&$log, $clear = true)
    {
        $errorsNotCleared = array();
        foreach ($log as $k => $logEntry) {
            if (\in_array($logEntry['method'], array('error','warn'), true) === false) {
                continue;
            }
            $clear2 = $clear;
            if ($this->channelName) {
                $channelName = $logEntry->getChannelName();
                $clear2 = $clear && $channelName === $this->channelName;
            }
            if ($clear2) {
                unset($log[$k]);
            } elseif (isset($logEntry['meta']['errorHash'])) {
                $errorsNotCleared[] = $logEntry['meta']['errorHash'];
            }
        }
        $log = \array_values($log);
        return $errorsNotCleared;
    }

    /**
     * Clear log entries
     *
     * @param int $flags flags passed to clear()
     *
     * @return string|null
     */
    private function clearLog($flags)
    {
        $return = null;
        $clearErrors = (bool) ($flags & Debug::CLEAR_LOG_ERRORS);
        if ($flags & Debug::CLEAR_LOG) {
            $return = 'log (' . ($clearErrors ? 'incl errors' : 'sans errors') . ')';
            $entriesKeep = $this->debug->methodGroup->getCurrentGroups('main');
            $this->clearLogHelper($this->data['log'], $clearErrors, $entriesKeep);
        } elseif ($clearErrors) {
            $return = 'errors';
        }
        return $return;
    }

    /**
     * Clear log data
     *
     * @param array $log         log to clear (passed by reference)
     * @param bool  $clearErrors whether or not to clear errors
     * @param array $entriesKeep log entries to keep
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
                $channelName = $logEntry->getChannelName();
                $channelMatch = !$this->channelName || $channelName === $this->channelName;
                if (\in_array($logEntry['method'], $keep, true) || !$channelMatch) {
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
     * @param int $flags flags passed to clear()
     *
     * @return string|null
     */
    private function clearSummary($flags)
    {
        $return = null;
        $clearErrors = (bool) ($flags & Debug::CLEAR_SUMMARY_ERRORS);
        if ($flags & Debug::CLEAR_SUMMARY) {
            $return = 'summary (' . ($clearErrors ? 'incl errors' : 'sans errors') . ')';
            $curPriority = $this->debug->methodGroup->getCurrentPriority(); // 'main'|int
            foreach (\array_keys($this->data['logSummary']) as $priority) {
                if ($priority !== $curPriority) {
                    $this->debug->methodGroup->reset($priority);
                    $this->clearLogHelper($this->data['logSummary'][$priority], $clearErrors, array());
                    continue;
                }
                $entriesKeep = $this->debug->methodGroup->getCurrentGroups($priority);
                $this->clearLogHelper($this->data['logSummary'][$priority], $clearErrors, $entriesKeep);
            }
        } elseif ($clearErrors) {
            $return = 'summary errors';
        }
        return $return;
    }

    /**
     * Build log arguments
     *
     * @param array $cleared array of things that were cleared
     *
     * @return array
     */
    private function getLogArgs($cleared)
    {
        $cleared = \array_filter($cleared);
        if (!$cleared) {
            return array();
        }
        $count = \count($cleared);
        $glue = $count === 2
            ? ' and '
            : ', ';
        if ($count > 2) {
            $cleared[$count - 1] = 'and ' . $cleared[$count - 1];
        }
        $msg = 'Cleared ' . \implode($glue, $cleared);
        if ($this->channelName) {
            return array(
                $msg . ' %c(%s)',
                'background-color:#c0c0c0; padding:0 .33em;',
                $this->channelName,
            );
        }
        return array($msg);
    }

    /**
     * Update logEntry
     *
     * @param LogEntry $logEntry LogEntry instance
     * @param array    $args     arguments]
     *
     * @return void
     */
    private function updateLogEntry(LogEntry $logEntry, $args)
    {
        $bitmask = $logEntry['meta']['bitmask'];
        $callerInfo = $this->debug->backtrace->getCallerInfo();
        $logEntry->setValues(array(
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
                    'silent' => (bool) ($bitmask & Debug::CLEAR_SILENT),
                ),
            ), $logEntry['meta']),
            'appendLog' => $args && !($bitmask & Debug::CLEAR_SILENT),
            'forcePublish' => (bool) $args,   // publish event even if collect = false
        ));
    }
}

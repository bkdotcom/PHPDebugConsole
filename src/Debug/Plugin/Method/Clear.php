<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.2
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Clear method
 */
class Clear implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var string|null */
    private $channelName = null;

    /** @var array<string,mixed> */
    private $data = array();

    /** @var list<LogEntry> */
    private $entriesKeep = array();

    /** @var string|null */
    private $channelRegex;

    /** @var bool */
    private $isRootInstance = false;

    /** @var string[] */
    protected $methods = [
        'clear',
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
     * Clear the log
     *
     * This method executes even if `collect` is `false`
     *
     * @param int $bitmask A bitmask of options
     *                     `self::CLEAR_ALERTS` : Clear alerts generated with `alert()`
     *                     `self::CLEAR_LOG` : **default** Clear log entries (excluding warn & error)
     *                     `self::CLEAR_LOG_ERRORS` : Clear warn & error
     *                     `self::CLEAR_SUMMARY` : Clear summary entries (excluding warn & error)
     *                     `self::CLEAR_SUMMARY_ERRORS` : Clear warn & error within summary groups
     *                     `self::CLEAR_ALL` : Clear all log entries
     *                     `self::CLEAR_SILENT` : Don't add log entry
     *
     * @return Debug
     *
     * @since 2.2
     */
    public function clear($bitmask = Debug::CLEAR_LOG) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        $debug = $this->debug;
        $logEntry = new LogEntry(
            $debug,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__),
            ['bitmask']
        );
        $this->doClear($logEntry);
        // even if cleared from within summary, let's log this in primary log
        $debug->data->set('logDest', 'main');
        $debug->log($logEntry);
        $debug->data->set('logDest', 'auto');
        return $debug;
    }

    /**
     * Handle clear() call
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function doClear(LogEntry $logEntry)
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
            $cleared = ['everything'];
        }
        $args = $this->getLogArgs($cleared);
        $this->debug->data->set($this->data);
        $this->data = array();
        $this->updateLogEntry($logEntry, $args);
    }

    /**
     * Test channel for inclusion
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
            $errorsNotCleared = \array_merge($errorsNotCleared, $this->clearErrorsHelper(
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
     * @param list<LogEntry> $log   Reference to log to clear of errors
     * @param bool           $clear Clear errors?
     *
     * @return string[] error-hashes not cleared
     */
    private function clearErrorsHelper(&$log, $clear = true)
    {
        $errorsNotCleared = array();

        $log = \array_filter($log, function (LogEntry $logEntry) use ($clear, &$errorsNotCleared) {
            if (\in_array($logEntry['method'], ['error', 'warn'], true) === false) {
                return true;
            }
            $clear2 = $this->channelName
                ? $clear && $logEntry->getChannelName() === $this->channelName
                : $clear;
            if ($clear2) {
                return false;
            }
            if (isset($logEntry['meta']['errorHash'])) {
                $errorsNotCleared[] = $logEntry['meta']['errorHash'];
            }
            return true;
        });
        $log = \array_values($log);

        return $errorsNotCleared;
    }

    /**
     * Clear log entries
     *
     * @param int $flags flags passed to clear()
     *
     * @return string|null user friendly string specifying what was cleared
     */
    private function clearLog($flags)
    {
        $return = null;
        $clearErrors = (bool) ($flags & Debug::CLEAR_LOG_ERRORS);
        if ($flags & Debug::CLEAR_LOG) {
            $return = 'log (' . ($clearErrors ? 'incl errors' : 'sans errors') . ')';
            $this->entriesKeep = $this->debug->rootInstance->getPlugin('methodGroup')->getCurrentGroups('main');
            $this->clearLogHelper($this->data['log'], $clearErrors);
        } elseif ($clearErrors) {
            $return = 'errors';
        }
        return $return;
    }

    /**
     * Clear log data
     *
     * @param list<LogEntry> $log         log to clear (passed by reference)
     * @param bool           $clearErrors whether or not to clear errors
     *
     * @return void
     */
    private function clearLogHelper(array &$log, $clearErrors = false)
    {
        $keep = $clearErrors
            ? []
            : ['error', 'warn'];
        if ($keep || $this->channelName) {
            $this->clearLogHelperFilter($log, $keep);
        }
        $log = \array_values($this->entriesKeep);
    }

    /**
     * Add non-cleared log entries to $this->entriesKeep
     *
     * @param list<LogEntry> $log  Log entries to filter
     * @param list<string>   $keep methods to keep
     *
     * @return void
     */
    private function clearLogHelperFilter(array $log, array $keep)
    {
        // we need to go through and filter based on method and/or channel
        foreach ($log as $k => $logEntry) {
            $channelName = $logEntry->getChannelName();
            $channelMatch = !$this->channelName || $channelName === $this->channelName;
            if (\in_array($logEntry['method'], $keep, true) || !$channelMatch) {
                $this->entriesKeep[$k] = $logEntry;
            }
        }
        \ksort($this->entriesKeep);
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
        $clearSummary = (bool) ($flags & Debug::CLEAR_SUMMARY);
        $clearErrors = (bool) ($flags & Debug::CLEAR_SUMMARY_ERRORS);
        if (!$clearSummary) {
            return $clearErrors
                ? 'summary errors'
                : null;
        }
        $groupPlugin = $this->debug->rootInstance->getPlugin('methodGroup');
        $curPriority = $groupPlugin->getCurrentPriority(); // 'main'|int
        foreach (\array_keys($this->data['logSummary']) as $priority) {
            if ($priority !== $curPriority) {
                $groupPlugin->reset($priority);
                $this->entriesKeep = array(); // not a "reset", but an "init"
                $this->clearLogHelper($this->data['logSummary'][$priority], $clearErrors);
                continue;
            }
            $this->entriesKeep = $groupPlugin->getCurrentGroups($priority);
            $this->clearLogHelper($this->data['logSummary'][$priority], $clearErrors);
        }
        return 'summary (' . ($clearErrors ? 'incl errors' : 'sans errors') . ')';
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
     * @param array    $args     arguments
     *
     * @return void
     */
    private function updateLogEntry(LogEntry $logEntry, $args)
    {
        $bitmask = $logEntry['meta']['bitmask'];
        $callerInfo = $this->debug->backtrace->getCallerInfo();
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $logEntry->setValues(array(
            'method' => 'clear',
            'args' => $args,
            'meta' =>  \array_merge(array(
                'bitmask' => $bitmask,
                'evalLine' => $callerInfo['evalLine'],
                'file' => $callerInfo['file'],
                'flags' => array(
                    'alerts' => (bool) ($bitmask & Debug::CLEAR_ALERTS),
                    'log' => (bool) ($bitmask & Debug::CLEAR_LOG),
                    'logErrors' => (bool) ($bitmask & Debug::CLEAR_LOG_ERRORS),
                    'silent' => (bool) ($bitmask & Debug::CLEAR_SILENT),
                    'summary' => (bool) ($bitmask & Debug::CLEAR_SUMMARY),
                    'summaryErrors' => (bool) ($bitmask & Debug::CLEAR_SUMMARY_ERRORS),
                ),
                'line' => $callerInfo['line'],
            ), $logEntry['meta']),
            'appendLog' => $args && !($bitmask & Debug::CLEAR_SILENT),
            'forcePublish' => (bool) $args,   // publish event even if collect = false
        ));
    }
}

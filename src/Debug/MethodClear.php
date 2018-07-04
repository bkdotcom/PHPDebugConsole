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
        $flags = $event['args'][0];
        $cleared = array();
        $cleared[] = $this->clearAlerts($flags);
        $cleared[] = $this->clearLog($flags);
        $cleared[] = $this->clearSummary($flags);
        if (($flags & Debug::CLEAR_ALL) == Debug::CLEAR_ALL) {
            $cleared = array('everything');
        }
        $this->clearErrors($flags);
        $callerInfo = $this->debug->utilities->getCallerInfo();
        $message = $this->buildMessage($cleared);
       	$event->setValues(array(
        	'method' => 'clear',
        	'args' => array($message),
        	'meta' => array(
	            'file' => $callerInfo['file'],
	            'line' => $callerInfo['line'],
	            'flags' => $flags,
        	),
        	'log' => !($flags & Debug::CLEAR_SILENT) && $message,
        	'publish' => (bool) $message,
       	));
       	return $event;
	}

	/**
	 * Build message that gets appended to log
	 *
	 * @param array $cleared array of things that were cleared
	 *
	 * @return string
	 */
	private function buildMessage($cleared)
	{
        $cleared = \array_filter($cleared);
        $count = \count($cleared);
        $glue = $count == 2
            ? ' and '
            : ', ';
        if ($count > 2) {
            $cleared[$count-1] = 'and '.$cleared[$count-1];
        }
        return $cleared
        	? 'Cleared '.\implode($glue, $cleared)
        	: '';
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
        $this->data['alerts'] = array();
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
            if (\in_array($entry[0], array('error','warn'))) {
                if ($clear) {
                    unset($log[$k]);
                } elseif (isset($entry[2]['errorHash'])) {
                    $errorsNotCleared[] = $entry[2]['errorHash'];
                }
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
            $entriesKeep = $this->debug->internal->getCurrentGroups($this->data['log'], $this->data['groupDepth'][1]);
            $keep = $clearErrors
                ? array()
                : array('error','warn');
            if ($keep) {
                foreach ($this->data['log'] as $k => $entry) {
                    if (\in_array($entry[0], $keep)) {
                        $entriesKeep[$k] = $entry;
                    }
                }
            }
            \ksort($entriesKeep);
            $this->data['log'] = \array_values($entriesKeep);
        } elseif ($clearErrors) {
            $return = 'errors';
        }
        return $return;
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
            $curPriority = \end($this->data['groupSummaryStack']);  // false if empty
            foreach (\array_keys($this->data['logSummary']) as $priority) {
                $entriesKeep = array();
                if ($priority === $curPriority) {
                    $entriesKeep = $this->debug->internal->getCurrentGroups(
                    	$this->data['logSummary'][$priority],
                    	$this->data['groupSummaryDepths'][$priority][1]
					);
                } else {
                    $this->data['groupSummaryDepths'][$priority] = array(0, 0);
                }
                if (!$clearErrors) {
                    foreach ($this->data['logSummary'][$priority] as $k => $entry) {
                        if (\in_array($entry[0], array('error','warn'))) {
                            $entriesKeep[$k] = $entry;
                        }
                    }
                }
                \ksort($entriesKeep);
                $this->data['logSummary'][$priority] = \array_values($entriesKeep);
            }
        } elseif ($clearErrors) {
            $return = 'summary errors';
        }
        return $return;
    }
}

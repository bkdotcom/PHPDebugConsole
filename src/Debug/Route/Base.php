<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Component;
use bdk\Debug\ConfigurableInterface;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Base output plugin
 */
abstract class Base extends Component implements ConfigurableInterface, RouteInterface
{

    public $debug;
    protected $channelName = null;
    protected $channelNameRoot = null;
    protected $channelRegex;
    protected $data = array();
    protected $dump;
    protected $isRootInstance = false;

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->channelName = $this->debug->getCfg('channelName');
        $this->channelNameRoot = $this->debug->rootInstance->getCfg('channelName');
        $this->channelRegex = '#^' . \preg_quote($this->channelName, '#') . '(\.|$)#';
        $this->isRootInstance = $this->debug->rootInstance === $this->debug;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            'debug.output' => 'processLogEntries',
        );
    }

    /**
     * Output the log as text
     *
     * @param Event $event event object
     *
     * @return string|void
     */
    public function processLogEntries(Event $event)
    {
        $this->data = $this->debug->getData();
        $str = '';
        $str .= $this->processAlerts();
        $str .= $this->processSummary();
        $str .= $this->processLog();
        $this->data = array();
        $event['return'] .= $str;
    }

    /**
     * Process log entry
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return mixed|void
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        return $this->dump->processLogEntry($logEntry);
    }

    /**
     * Test channel for inclussion
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return boolean
     */
    protected function channelTest(LogEntry $logEntry)
    {
        $channelName = $logEntry->getChannel();
        return $this->isRootInstance || \preg_match($this->channelRegex, $channelName);
    }

    /**
     * Get dumper
     *
     * @return \bdk\Debug\Dump\Base
     */
    protected function getDump()
    {
        return $this->dump;
    }

    /**
     * Process alerts
     *
     * By default we just output alerts like error(), info(), and warn() calls
     *
     * @return string
     */
    protected function processAlerts()
    {
        $str = '';
        foreach ($this->data['alerts'] as $logEntry) {
            if ($this->channelTest($logEntry)) {
                $str .= $this->processLogEntryViaEvent($logEntry);
            }
        }
        return $str;
    }

    /**
     * Process log entries
     *
     * @return string
     */
    protected function processLog()
    {
        $str = '';
        foreach ($this->data['log'] as $logEntry) {
            if ($this->channelTest($logEntry)) {
                $str .= $this->processLogEntryViaEvent($logEntry);
            }
        }
        return $str;
    }

    /**
     * Publish debug.outputLogEntry.
     * Return event['return'] if not empty
     * Otherwise, propagation not stopped, return result of processLogEntry()
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return mixed
     */
    protected function processLogEntryViaEvent(LogEntry $logEntry)
    {
        $logEntry = new LogEntry($logEntry->getSubject(), $logEntry['method'], $logEntry['args'], $logEntry['meta']);
        $logEntry['route'] = $this;
        $this->debug->internal->publishBubbleEvent('debug.outputLogEntry', $logEntry);
        if ($logEntry['return'] !== null) {
            return $logEntry['return'];
        }
        return $this->processLogEntry($logEntry);
    }

    /**
     * Process summary
     *
     * @return string
     */
    protected function processSummary()
    {
        $str = '';
        $summaryData = $this->data['logSummary'];
        if ($summaryData) {
            \krsort($summaryData);
            $summaryData = \call_user_func_array('array_merge', $summaryData);
        }
        foreach ($summaryData as $logEntry) {
            if ($this->channelTest($logEntry)) {
                $str .= $this->processLogEntryViaEvent($logEntry);
            }
        }
        return $str;
    }
}

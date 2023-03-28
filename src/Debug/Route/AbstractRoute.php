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

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\LogEntry;
use bdk\Debug\Route\RouteInterface;
use bdk\PubSub\Event;

/**
 * Base route plugin
 */
abstract class AbstractRoute extends AbstractComponent implements RouteInterface
{
    public $debug;
    protected $appendsHeaders = false;
    protected $channelName = null;

    /**
     * channelName of instance initiating the output!
     * Not the rootInstance channelName
     *
     * @var string
     */
    protected $channelNameRoot = '';

    protected $channelRegex;
    protected $cfg = array(
        'channels' => array('*'),
        'channelsExclude' => array(),
    );
    protected $data = array();
    protected $dumper;

    /** @var array channelName => bool */
    private $shouldIncludeCache = array();

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->channelName = $this->debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        $this->channelNameRoot = $this->debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        $this->channelRegex = '#^' . \preg_quote($this->channelName, '#') . '(\.|$)#';
    }

    /**
     * {@inheritDoc}
     */
    public function appendsHeaders()
    {
        return $this->appendsHeaders;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OUTPUT => 'processLogEntries',
        );
    }

    /**
     * Process alerts
     *
     * By default we just output alerts like error(), info(), and warn() calls
     *
     * @return string
     */
    public function processAlerts()
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
    public function processLog()
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
     * Process summary
     *
     * @return string
     */
    public function processSummary()
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

    /**
     * Output the log as text
     *
     * @param Event $event event object
     *
     * @return void
     */
    public function processLogEntries(Event $event)
    {
        $this->dumper->crateRaw = false;
        $this->data = $this->debug->data->get();
        $event['return'] = ''
            . $this->processAlerts()
            . $this->processSummary()
            . $this->processLog();
        $this->data = array();
        $this->dumper->crateRaw = true;
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
        return $this->dumper->processLogEntry($logEntry);
    }

    /**
     * Set channel name regex used to test if log entry should be output
     *
     * @param string $regex channel regex
     *
     * @return void
     */
    public function setChannelRegex($regex)
    {
        $this->channelRegex = $regex;
    }

    /**
     * Test channel for inclussion
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return bool
     */
    protected function channelTest(LogEntry $logEntry)
    {
        return \preg_match($this->channelRegex, $logEntry->getChannelName()) === 1;
    }

    /**
     * Get dumper
     *
     * @return \bdk\Debug\Dump\Base
     */
    protected function getDumper()
    {
        return $this->dumper;
    }

    /**
     * Publish Debug::EVENT_OUTPUT_LOG_ENTRY.
     * Return event['return'] if not empty
     * Otherwise, propagation not stopped, return result of processLogEntry()
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return mixed
     */
    protected function processLogEntryViaEvent(LogEntry $logEntry)
    {
        if ($this->shouldInclude($logEntry) === false) {
            return '';
        }
        $logEntry = new LogEntry(
            $logEntry->getSubject(),
            $logEntry['method'],
            $logEntry['args'],
            $logEntry['meta']
        );
        $logEntry['route'] = $this;
        $this->debug->publishBubbleEvent(Debug::EVENT_OUTPUT_LOG_ENTRY, $logEntry);
        if ($logEntry['output'] === false) {
            return '';
        }
        if ($logEntry['return'] !== null) {
            return $logEntry['return'];
        }
        return $this->processLogEntry($logEntry);
    }

    /**
     * Should this route handle/process/output this logEntry?
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return bool
     */
    protected function shouldInclude(LogEntry $logEntry)
    {
        $channelName = $logEntry->getChannelName();
        $channelName = \strtolower($channelName);
        if (isset($this->shouldIncludeCache[$channelName])) {
            return $this->shouldIncludeCache[$channelName];
        }
        if (empty($this->cfg['channels'])) {
            $this->cfg['channels'] = array('*');
        }
        if (!isset($this->cfg['channelsExclude'])) {
            $this->cfg['channelsExclude'] = array();
        }
        $include = $this->testChannelNameMatch($channelName, $this->cfg['channels'])
            && !$this->testChannelNameMatch($channelName, $this->cfg['channelsExclude']);
        $this->shouldIncludeCache[$channelName] = $include;
        return $include;
    }

    /**
     * Test if string matches against list of strings
     *
     * @param string $channelName  channelName to test
     * @param array  $channelNames list of channelNames (may include wildcard '*')
     *
     * @return bool
     */
    private function testChannelNameMatch($channelName, $channelNames = array())
    {
        foreach ($channelNames as $channelNameTest) {
            if ($this->testChannelName($channelName, $channelNameTest)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Test if channel name matches test value
     *
     * @param string $channelName     channelName to test
     * @param string $channelNameTest test string
     *
     * @return bool
     */
    private function testChannelName($channelName, $channelNameTest)
    {
        $channelNameTest = \strtolower($channelNameTest);
        if ($channelNameTest === '*') {
            return true;
        }
        if ($channelNameTest === $channelName) {
            return true;
        }
        if (\substr($channelNameTest, -1, 1) === '*') {
            $prefix = \rtrim($channelNameTest, '*');
            if (\strpos($channelName, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }
}

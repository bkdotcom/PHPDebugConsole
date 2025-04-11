<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
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
    /** @var Debug  */
    public $debug;

    /** @var bool */
    protected $appendsHeaders = false;

    /** @var string|bool */
    protected $channelKey = null;

    /**
     * channelKey of instance initiating the output!
     * Not the rootInstance channelKey
     *
     * @var string
     */
    protected $channelKeyRoot = '';

    /** @var string */
    protected $channelRegex = '';

    /** @var array<string,mixed> */
    protected $cfg = array(
        'channels' => ['*'],
        'channelsExclude' => [],
    );

    /** @var array<string,mixed> */
    protected $data = array();

    /** @var \bdk\Debug\Dump\Base */
    protected $dumper;

    /** @var array channelKey => bool */
    private $shouldIncludeCache = array();

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
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
            Debug::EVENT_OUTPUT => [
                'setChannelName',
                'processLogEntries',
            ],
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
            if ($this->testChannelRegex($logEntry->getChannelKey())) {
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
            if ($this->testChannelRegex($logEntry->getChannelKey())) {
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
            if ($this->testChannelRegex($logEntry->getChannelKey())) {
                $str .= $this->processLogEntryViaEvent($logEntry);
            }
        }
        return $str;
    }

    /**
     * Output the log as text
     *
     * @param Event|null $event event object
     *
     * @return void
     */
    public function processLogEntries($event = null)
    {
        \bdk\Debug\Utility\PhpType::assertType($event, 'bdk\PubSub\Event|null');

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
     * Set channelKey values
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function setChannelName(Event $event)
    {
        $debug = $event->getSubject();
        $isRoot = $debug === $debug->rootInstance;
        $this->channelKey = $debug->getCfg('channelKey', Debug::CONFIG_DEBUG);
        $this->channelKeyRoot = $debug->getCfg('channelKey', Debug::CONFIG_DEBUG);
        $this->setChannelRegex('#^' . ($isRoot ? '.+' : \preg_quote($this->channelKey, '#')) . '(\.|$)#');
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
     * Get dumper
     *
     * @return \bdk\Debug\Dump\Base
     */
    protected function getDumper()
    {
        return $this->dumper;
    }

    /**
     * Get request-method + request-uri or command line args
     *
     * @return string
     */
    protected function getRequestMethodUri()
    {
        return $this->debug->isCli()
            ? '$: ' . \implode(' ', $this->debug->getServerParam('argv', array()))
            : $this->debug->serverRequest->getMethod()
                . ' ' . $this->debug->redact((string) $this->debug->serverRequest->getUri());
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
        $channelKey = $logEntry->getChannelKey();
        $channelKey = \strtolower($channelKey);
        if (isset($this->shouldIncludeCache[$channelKey])) {
            return $this->shouldIncludeCache[$channelKey];
        }
        if (empty($this->cfg['channels'])) {
            $this->cfg['channels'] = ['*'];
        }
        if (!isset($this->cfg['channelsExclude'])) {
            $this->cfg['channelsExclude'] = [];
        }
        $include = $this->testChannelKeyMatch($channelKey, $this->cfg['channels'])
            && !$this->testChannelKeyMatch($channelKey, $this->cfg['channelsExclude']);
        $this->shouldIncludeCache[$channelKey] = $include;
        return $include;
    }

    /**
     * Test if string matches against list of strings
     *
     * @param string $channelKey  channelKey to test
     * @param array  $channelKeys list of channelKeys (may include wildcard '*')
     *
     * @return bool
     */
    protected function testChannelKeyMatch($channelKey, $channelKeys = array())
    {
        foreach ($channelKeys as $channelKeyTest) {
            if ($this->testChannelKey($channelKey, $channelKeyTest)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Test if channel key matches test value
     *
     * @param string $channelKey     channelKey to test
     * @param string $channelKeyTest key to test against
     *
     * @return bool
     */
    private function testChannelKey($channelKey, $channelKeyTest)
    {
        $channelKeyTest = \strtolower($channelKeyTest);
        if ($channelKeyTest === '*') {
            return true;
        }
        if ($channelKeyTest === $channelKey) {
            return true;
        }
        if (\substr($channelKeyTest, -1, 1) === '*') {
            $prefix = \rtrim($channelKeyTest, '*');
            if (\strpos($channelKey, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Test channel for inclusion
     *
     * Channel must be the channel (or descendant) that we're outputting
     *
     * @param string $channelKey channelKey to test
     *
     * @return bool
     */
    private function testChannelRegex($channelKey)
    {
        return \preg_match($this->channelRegex, $channelKey) === 1;
    }
}

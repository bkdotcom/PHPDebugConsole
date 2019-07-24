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
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Base output plugin
 */
abstract class Base implements RouteInterface
{

    public $debug;
    protected $cfg = array();
    protected $channelName = null;    // should be set by onOutput
    protected $channelNameRoot = null;
    protected $channelRegex;
    protected $data = array();
    protected $isRootInstance = false;
    protected $name = '';

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        if (!$this->name) {
            $name = \get_called_class();
            $idx = \strrpos($name, '\\');
            if ($idx) {
                $name = \substr($name, $idx + 1);
                $name = \lcfirst($name);
            }
            $this->name = $name;
        }
        $this->channelName = $this->debug->getCfg('channelName');
        $this->channelNameRoot = $this->debug->rootInstance->getCfg('channelName');
        $this->channelRegex = '#^'.\preg_quote($this->channelName, '#').'(\.|$)#';
        $this->isRootInstance = $this->debug->rootInstance === $this->debug;
    }

    /**
     * Magic getter
     *
     * @param string $prop property to get
     *
     * @return mixed
     */
    public function __get($prop)
    {
        $getter = 'get'.\ucfirst($prop);
        if (\method_exists($this, $getter)) {
            return $this->{$getter}();
        }
    }

    /**
     * Get config value(s)
     *
     * @param string $key (optional) key
     *
     * @return mixed
     */
    public function getCfg($key = null)
    {
        if ($key === null) {
            return $this->cfg;
        }
        return isset($this->cfg[$key])
            ? $this->cfg[$key]
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            'debug.output' => 'onOutput',
        );
    }

    /**
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return mixed
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        return $this->dump->processLogEntry($logEntry);
    }

    /**
     * debug.output subscriber
     *
     * @param Event $event debug.output event object
     *
     * @return void
     */
    abstract public function onOutput(Event $event);

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string $mixed key=>value array or key
     * @param mixed  $val   new value
     *
     * @return mixed returns previous value(s)
     */
    public function setCfg($mixed, $val = null)
    {
        $ret = null;
        if (\is_string($mixed)) {
            $ret = isset($this->cfg[$mixed])
                ? $this->cfg[$mixed]
                : null;
            $this->cfg[$mixed] = $val;
        } elseif (\is_array($mixed)) {
            $ret = \array_intersect_key($this->cfg, $mixed);
            $this->cfg = \array_merge($this->cfg, $mixed);
        }
        return $ret;
    }

    /**
     * Process log entry without publishing `debug.outputLogEntry` event
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return mixed
     */
    // abstract public function processLogEntry(LogEntry $logEntry);

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
     * Get name property
     *
     * @return string
     */
    protected function getName()
    {
        return $this->name;
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
        $logEntry['outputAs'] = $this;
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

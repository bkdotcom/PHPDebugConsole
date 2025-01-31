<?php

/**
 * Route PHPDebugConsole method calls to
 * WAMP (Web Application Messaging Protocol) router
 *
 * This plugin requires bdk/wamp-publisher (not included)
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Route\RouteInterface;
use bdk\Debug\Route\WampCrate;
use bdk\Debug\Route\WampHelper;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\WampPublisher;

/**
 * PHPDebugConsole plugin for routing debug messages thru WAMP router
 *
 * Additional methods sent:
 *    endOutput
 *    errorNotConsoled
 *    meta
 */
class Wamp implements RouteInterface
{
    /** @var Debug */
    public $debug;

    /** @var string */
    public $requestId;

    /** @var string */
    public $topic = 'bdk.debug';

    /** @var WampPublisher */
    public $wamp;

    /** @var array<string,mixed> */
    protected $cfg = array(
        'output' => false,      // kept in sync with debug->cfg['output']
    );

    /** @var list<string> */
    protected $channelNames = array();

    /**
     * Utility for "crating" up our values & abstractions for transport
     *
     * @var WampCrate
     */
    protected $crate;

    /** @var WampHelper */
    protected $helper;

    /** @var bool */
    protected $isBootstrapped = false;

    /** @var bool */
    protected $metaPublished = false;

    /** @var bool */
    protected $notConnectedAlerted = false;

    /**
     * Constructor
     *
     * @param Debug         $debug Debug instance
     * @param WampPublisher $wamp  WAMP Publisher instance
     */
    public function __construct(Debug $debug, WampPublisher $wamp)
    {
        $this->debug = $debug;
        $this->wamp = $wamp;
        $this->crate = new WampCrate($debug);
        $this->helper = new WampHelper($debug);
    }

    /**
     * {@inheritDoc}
     */
    public function appendsHeaders()
    {
        return false;
    }

    /**
     * Return a list of event subscribers
     *
     * @return array The event names to subscribe to
     */
    public function getSubscriptions()
    {
        if (!$this->isConnected()) {
            if (!$this->notConnectedAlerted) {
                $this->debug->alert('WAMP publisher not connected to WAMP router');
                $this->notConnectedAlerted = true;
            }
            return array();
        }
        return array(
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
            Debug::EVENT_CONFIG => 'onConfig',
            Debug::EVENT_LOG => ['onLog', PHP_INT_MAX * -1],
            ErrorHandler::EVENT_ERROR => ['onError', -1],    // assumes errorhandler is using same dispatcher.. as should be
            EventManager::EVENT_PHP_SHUTDOWN => ['onShutdown', PHP_INT_MAX * -1],
        );
    }

    /**
     * Is WAMP publisher connected?
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->wamp->connected;
    }

    /**
     * Debug::EVENT_BOOTSTRAP subscriber
     *
     * @return void
     */
    public function onBootstrap()
    {
        $this->cfg['output'] = $this->debug->getCfg('output', Debug::CONFIG_DEBUG);
        $this->isBootstrapped = true;
        $this->requestId = $this->debug->data->get('requestId');
        $this->publishMeta();
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event['debug'];
        if (isset($cfg['output'])) {
            $this->cfg['output'] = $cfg['output'];
        }
        $this->publishMeta();
    }

    /**
     * ErrorHandler::EVENT_ERROR event subscriber
     *
     * Used to capture errors that aren't sent to log (ie debug capture is false)
     *
     * @param Error $error Error event instance
     *
     * @return void
     */
    public function onError(Error $error)
    {
        if ($error['inConsole'] || !$error['isFirstOccur']) {
            return;
        }
        if (!$this->cfg['output']) {
            /*
                We'll publish fatal errors even if output = false
            */
            if ($error['category'] !== 'fatal') {
                return;
            }
            $this->publishMeta();
        }
        $this->processLogEntry(new LogEntry(
            $this->debug->getChannel('phpError'),
            'errorNotConsoled',
            [
                $error['typeStr'] . ':',
                $error['message'],
                $error['fileAndLine'],
            ],
            array(
                'attribs' => array(
                    'class' => $error['type'] & $this->debug->getCfg('errorMask', Debug::CONFIG_DEBUG)
                        ? 'error'
                        : 'warn',
                ),
            )
        ));
    }

    /**
     * Debug::EVENT_LOG event subscriber
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function onLog(LogEntry $logEntry)
    {
        if (!$this->cfg['output'] || !$this->isBootstrapped) {
            return;
        }
        $this->processLogEntryViaEvent($logEntry);
    }

    /**
     * EventManager::EVENT_PHP_SHUTDOWN event subscriber
     *
     * @return void
     */
    public function onShutdown()
    {
        if (!$this->metaPublished) {
            return;
        }
        // publish a "we're done" message
        $this->processLogEntry(new LogEntry(
            $this->debug,
            'endOutput',
            [],
            array(
                'responseCode' => \strpos($this->debug->getInterface(), 'http') !== false
                    ? $this->debug->getResponseCode()
                    : null,
            )
        ));
    }

    /**
     * ProcessLogEntries
     *
     * We use this interface method to process pre-existing log data
     *
     * @param Event|null $event debug event
     *
     * @return void
     */
    public function processLogEntries($event = null) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        $this->debug->utility->assertType($event, 'bdk\PubSub\Event');

        $data = $this->debug->data->get();
        foreach ($data['alerts'] as $logEntry) {
            $this->processLogEntryViaEvent($logEntry);
        }
        foreach ($data['logSummary'] as $priority => $entries) {
            $this->processLogSummaryEntries($priority, $entries);
        }
        foreach ($data['log'] as $logEntry) {
            $this->processLogEntryViaEvent($logEntry);
        }
    }

    /**
     * Publish WAMP message to topic
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'format' => 'raw',
            'requestId' => $this->requestId,
        ), $logEntry['meta']);
        $classesNew = array();
        if ($logEntry->getSubject() !== $this->debug) {
            $meta['channel'] = $logEntry->getChannelName();
            if (\in_array($meta['channel'], $this->channelNames, true) === false) {
                $meta['channelIcon'] = $logEntry->getSubject()->getCfg('channelIcon', Debug::CONFIG_DEBUG);
                $meta['channelShow'] = $logEntry->getSubject()->getCfg('channelShow', Debug::CONFIG_DEBUG);
                $meta['channelSort'] = $logEntry->getSubject()->getCfg('channelSort', Debug::CONFIG_DEBUG);
                $this->channelNames[] = $meta['channel'];
            }
        }
        if ($logEntry['return']) {
            $args = $logEntry['return'];
        } elseif ($meta['format'] === 'raw') {
            list($args, $metaNew, $classesNew) = $this->crate->crateLogEntry($logEntry);
            $meta = \array_merge($meta, $metaNew);
        }
        $this->processNewClasses($classesNew, $meta);
        $this->wamp->publish($this->topic, [$logEntry['method'], $args, $meta]);
    }

    /**
     * Process/publish a log entry
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    protected function processLogEntryViaEvent(LogEntry $logEntry)
    {
        $logEntry = new LogEntry(
            $logEntry->getSubject(),
            $logEntry['method'],
            $logEntry['args'],
            $logEntry['meta']
        );
        $logEntry['route'] = $this;
        $this->debug->publishBubbleEvent(Debug::EVENT_OUTPUT_LOG_ENTRY, $logEntry);
        if ($logEntry['output'] === false) {
            return;
        }
        $this->processLogEntry($logEntry);
    }

    /**
     * Process logSummary priority
     *
     * @param int        $priority Priority
     * @param LogEntry[] $entries  LogEntries
     *
     * @return void
     */
    private function processLogSummaryEntries($priority, array $entries)
    {
        $this->processLogEntryViaEvent(new LogEntry(
            $this->debug,
            'groupSummary',
            [],
            array(
                'priority' => $priority,
            )
        ));
        foreach ($entries as $logEntry) {
            $this->processLogEntryViaEvent($logEntry);
        }
        $this->processLogEntryViaEvent(new LogEntry(
            $this->debug,
            'groupEnd',
            [],
            array(
                'closesSummary' => true,
            )
        ));
    }

    /**
     * Process class definitions that have not yet been pushed
     *
     * @param array $classesNew New classnames
     *
     * @return void
     */
    private function processNewClasses(array $classesNew)
    {
        if (!$classesNew) {
            return;
        }
        $classDefinitions = array();
        foreach ($classesNew as $classKey) {
            $classDefinitions[$classKey] = $this->debug->data->get('classDefinitions/' . $classKey);
        }
        $this->processLogEntry(new LogEntry(
            $this->debug,
            'meta',
            [
                array(
                    'classDefinitions' => $classDefinitions,
                ),
            ]
        ));
    }

    /**
     * Publish initial meta data
     *
     * @return void
     */
    private function publishMeta()
    {
        if ($this->isBootstrapped !== true || $this->cfg['output'] !== true || $this->metaPublished) {
            return;
        }

        $this->metaPublished = true;

        $this->processLogEntry(new LogEntry(
            $this->debug,
            'meta',
            [
                $this->helper->getMeta(),
            ],
            $this->helper->getMetaConfig()
        ));

        $this->processLogEntries();
    }
}

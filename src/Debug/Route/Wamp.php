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
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;
use bdk\Debug\Route\RouteInterface;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\WampPublisher;

/**
 * PHPDebugConsole plugin for routing debug messages thru WAMP router
 */
class Wamp implements RouteInterface
{

    public $debug;
    public $requestId;
    public $topic = 'bdk.debug';
    public $wamp;
    protected $cfg = array(
        'output' => false,      // kept in sync with debug->cfg['output']
    );
    protected $channelName = '';
    protected $channelNames = array();
    protected $detectFiles = false;
    protected $foundFiles = array();
    protected $metaPublished = false;
    protected $notConnectedAlert = false;

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
        $this->requestId = $this->debug->getData('requestId');
    }

    /**
     * Return a list of event subscribers
     *
     * @return array The event names to subscribe to
     */
    public function getSubscriptions()
    {
        if (!$this->isConnected()) {
            if (!$this->notConnectedAlert) {
                $this->debug->alert('WAMP publisher not connected to WAMP router');
                $this->notConnectedAlert = true;
            }
            return array();
        }
        return array(
            Debug::EVENT_CONFIG => 'onConfig',
            Debug::EVENT_LOG => array('onLog', PHP_INT_MAX * -1),
            Debug::EVENT_PLUGIN_INIT => 'init',
            ErrorHandler::EVENT_ERROR => 'onError',    // assumes errorhandler is using same dispatcher.. as should be
            EventManager::EVENT_PHP_SHUTDOWN => array('onShutdown', PHP_INT_MAX * -1),
        );
    }

    /**
     * Debug::EVENT_PLUGIN_INIT subscriber
     *
     * @return void
     */
    public function init()
    {
        $this->cfg['output'] = $this->debug->getCfg('output', Debug::CONFIG_DEBUG);
        if ($this->cfg['output']) {
            $this->publishMeta();
        }
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
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event->getValues();
        if (isset($cfg['debug']['output'])) {
            $this->cfg['output'] = $cfg['debug']['output'];
        }
        if ($this->cfg['output']) {
            $this->publishMeta();
        }
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
            array(
                $error['typeStr'] . ':',
                $error['message'],
                \sprintf('%s (line %s)', $error['file'], $error['line']),
            ),
            array(
                'attribs' => array(
                    'class' => $error['type'] & $this->debug->getCfg('errorMask', Debug::CONFIG_DEBUG)
                        ? 'error'
                        : 'warn',
                )
            )
        ));
    }

    /**
     * Debug::EVENT_LOG event subscriber
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return void
     */
    public function onLog(LogEntry $logEntry)
    {
        if (!$this->cfg['output']) {
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
            array(),
            array(
                'responseCode' => \http_response_code(),
            )
        ));
    }

    /**
     * ProcessLogEntries
     *
     * We use this interface method to process pre-existing log data
     *
     * @param Event $event debug event
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function processLogEntries(Event $event = null)
    {
        $data = $this->debug->getData();
        foreach ($data['alerts'] as $logEntry) {
            $this->processLogEntryViaEvent($logEntry);
        }
        foreach ($data['logSummary'] as $priority => $entries) {
            $this->processLogEntryViaEvent(new LogEntry(
                $this->debug,
                'groupSummary',
                array(),
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
                array(),
                array(
                    'closesSummary' => true,
                )
            ));
        }
        foreach ($data['log'] as $logEntry) {
            $this->processLogEntryViaEvent($logEntry);
        }
    }

    /**
     * Publish WAMP message to topic
     *
     * @param LogEntry $logEntry log entry instance
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
        if ($logEntry->getSubject() !== $this->debug) {
            $meta['channel'] = $logEntry->getChannelName();
            if (!\in_array($meta['channel'], $this->channelNames)) {
                $meta['channelIcon'] = $logEntry->getSubject()->getCfg('channelIcon', Debug::CONFIG_DEBUG);
                $meta['channelShow'] = $logEntry->getSubject()->getCfg('channelShow', Debug::CONFIG_DEBUG);
                $meta['channelSort'] = $logEntry->getSubject()->getCfg('channelSort', Debug::CONFIG_DEBUG);
                $this->channelNames[] = $meta['channel'];
            }
        }
        if ($logEntry['return']) {
            $args = $logEntry['return'];
        }
        $this->detectFiles = $logEntry->getMeta('detectFiles', false);
        $this->foundFiles = array();
        if ($meta['format'] === 'raw') {
            $args = $this->crateValues($args);
        }
        if (!empty($meta['trace'])) {
            $logEntryTmp = new LogEntry(
                $this->debug,
                'trace',
                array($meta['trace']),
                array(
                    'columns' => array('file','line','function'),
                    'inclContext' => $logEntry->getMeta('inclContext', false),
                )
            );
            $this->debug->methodTable->onLog($logEntryTmp);
            unset($args[2]);
            $meta = \array_merge($meta, array(
                'caption' => 'trace',
                'tableInfo' => $logEntryTmp['meta']['tableInfo'],
                'trace' => $logEntryTmp['args'][0],
            ));
        }
        if ($this->detectFiles) {
            $meta['foundFiles'] = $this->foundFiles;
        }
        $this->wamp->publish($this->topic, array($logEntry['method'], $args, $meta));
    }

    /**
     * Crate array (may be encapsulated by Abstraction)
     *
     * @param array $array array
     *
     * @return array
     */
    private function crateArray($array)
    {
        $return = array();
        $keys = array();
        foreach ($array as $k => $v) {
            if (\is_string($k) && \substr($k, 0, 1) === "\x00") {
                // key starts with null...
                // php based wamp router will choke (attempt to json_decode to obj)
                $k = '_b64_:' . \base64_encode($k);
            }
            $return[$k] = $this->crateValues($v);
        }
        if ($this->debug->utility->arrayIsList($array) === false) {
            /*
                Compare sorted vs unsorted
                if differ pass the key order
            */
            $keys = \array_keys($array);
            $keysSorted = $keys;
            \sort($keysSorted, SORT_STRING);
            if ($keys !== $keysSorted) {
                $return['__debug_key_order__'] = $keys;
            }
        }
        return $return;
    }

    /**
     * Crate object abstraction
     * (make sure string values are base64 encoded when necessary)
     *
     * @param Abstraction $abs Object abstraction
     *
     * @return array
     */
    private function crateObject(Abstraction $abs)
    {
        $info = $abs->jsonSerialize();
        foreach ($info['properties'] as $k => $propInfo) {
            $info['properties'][$k]['value'] = $this->crateValues($propInfo['value']);
        }
        if (isset($info['methods']['__toString'])) {
            $info['methods']['__toString'] = $this->crateValues($info['methods']['__toString']);
        }
        return $info;
    }

    /**
     * Base64 encode string if it contains non-utf8 characters
     *
     * @param string $str string
     *
     * @return string
     */
    private function crateString($str)
    {
        if (!$str) {
            return $str;
        }
        if ($this->debug->utf8->isUtf8($str) === false) {
            return '_b64_:' . \base64_encode($str);
        }
        if ($this->detectFiles && $this->debug->utility->isFile($str)) {
            $this->foundFiles[] = $str;
        }
        return $str;
    }

    /**
     * JSON doesn't handle binary well (at all)
     *     a) strings with invalid utf-8 can't be json_encoded
     *     b) "javascript has a unicode problem" / will munge strings
     *   base64_encode all strings!
     *
     * Associative arrays get JSON encoded to js objects...
     *     Javascript doesn't maintain order for object properties
     *     in practice this seems to only be an issue with int/numeric keys
     *     store key order if needed
     *
     * @param mixed $mixed value to crate
     *
     * @return array|string
     */
    private function crateValues($mixed)
    {
        if (\is_array($mixed)) {
            return $this->crateArray($mixed);
        }
        if (\is_string($mixed)) {
            return $this->crateString($mixed);
        }
        if ($mixed instanceof Abstraction) {
            $clone = clone $mixed;
            switch ($mixed['type']) {
                case Abstracter::TYPE_ARRAY:
                    $clone['value'] = $this->crateArray($clone['value']);
                    return $clone;
                case Abstracter::TYPE_OBJECT:
                    return $this->crateObject($clone);
                case Abstracter::TYPE_STRING:
                    $clone['value'] = $this->crateString($clone['value']);
                    if (isset($clone['valueDecoded'])) {
                        $clone['valueDecoded'] = $this->crateValues($clone['valueDecoded']);
                    }
                    return $clone;
            }
        }
        return $mixed;
    }

    /**
     * Process/publish a log entry
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    protected function processLogEntryViaEvent(LogEntry $logEntry)
    {
        $logEntry = new LogEntry($logEntry->getSubject(), $logEntry['method'], $logEntry['args'], $logEntry['meta']);
        $logEntry['route'] = $this;
        $this->debug->publishBubbleEvent(Debug::EVENT_OUTPUT_LOG_ENTRY, $logEntry);
        $this->processLogEntry($logEntry);
    }

    /**
     * Publish initial meta data
     *
     * @return void
     */
    private function publishMeta()
    {
        if ($this->metaPublished) {
            return;
        }
        $this->metaPublished = true;
        $debugClass = \get_class($this->debug);
        $metaVals = array(
            'HTTP_HOST' => null,
            'HTTPS' => null,
            'REMOTE_ADDR' => null,
            'REQUEST_METHOD' => $this->debug->request->getMethod(),
            'REQUEST_TIME' => null,
            'REQUEST_URI' => $this->debug->request->getRequestTarget(),
            'SERVER_ADDR' => null,
            'SERVER_NAME' => null,
        );
        $serverParams = \array_merge(
            array(
                'argv' => array(),
            ),
            $this->debug->request->getServerParams(),
            $metaVals
        );
        foreach (\array_keys($metaVals) as $k) {
            $metaVals[$k] = $serverParams[$k];
        }
        $isCli = \strpos($this->debug->getInterface(), 'cli') === 0;
        if ($isCli) {
            $metaVals['REQUEST_METHOD'] = null;
            $metaVals['REQUEST_URI'] = '$: ' . \implode(' ', $serverParams['argv']);
        }
        $this->processLogEntry(new LogEntry(
            $this->debug,
            'meta',
            array(
                $this->debug->redact($metaVals),
            ),
            array(
                'channelNameRoot' => $this->debug->rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG),
                'debugVersion' => $debugClass::VERSION,
                'drawer' => $this->debug->getCfg('routeHtml.drawer'),
                'interface' => $this->debug->getInterface(),
                'linkFilesTemplateDefault' => \strtr(
                    \ini_get('xdebug.file_link_format'),
                    array(
                        '%f' => '%file',
                        '%l' => '%line',
                    )
                ) ?: null,
            )
        ));
        $this->processLogEntries();
    }
}

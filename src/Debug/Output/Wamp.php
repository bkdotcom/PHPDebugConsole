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
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Output;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\ErrorHandler\Error;
use bdk\WampPublisher;

/**
 * PHPDebugConsole plugin for routing debug messages thru WAMP router
 */
class Wamp implements OutputInterface
{

    public $debug;
    protected $name = 'wamp';
    public $requestId;
    public $topic = 'bdk.debug';
    public $wamp;
    protected $channelName = '';
    protected $detectFiles = false;
    protected $foundFiles = array();

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
     * {@inheritdoc}
     */
    public function dump($val)
    {
    }

    /**
     * Return a list of event subscribers
     *
     * @return array The event names to subscribe to
     */
    public function getSubscriptions()
    {
        if (!$this->isConnected()) {
            $this->debug->alert('WAMP publisher not connected to WAMP router');
            return array();
        }
        $this->publishMeta();
        $this->processExistingData();
        return array(
            'debug.log' => array('onLog', PHP_INT_MAX * -1),
            'errorHandler.error' => 'onError',    // assumes errorhandler is using same dispatcher.. as should be
            'php.shutdown' => array('onShutdown', PHP_INT_MAX * -1),
        );
    }

    /**
     * Is WAMP publisher connected?
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->wamp->connected;
    }

    /**
     * errorHandler.error event subscriber
     *
     * Used to capture errors that aren't sent to log (ie debug capture is false)
     *
     * @param Error $error error event instance
     *
     * @return void
     */
    public function onError(Error $error)
    {
        if ($error['inConsole'] || !$error['isFirstOccur']) {
            return;
        }
        $this->processLogEntry(new LogEntry(
            $this->debug->getChannel('phpError'),
            'errorNotConsoled',
            array(
                $error['typeStr'].': '.$error['file'].' (line '.$error['line'].'): '.$error['message']
            ),
            array(
                'class' => $error['type'] & $this->debug->getCfg('errorMask')
                    ? 'danger'
                    : 'warning',
            )
        ));
    }

    /**
     * debug.log event subscriber
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return void
     */
    public function onLog(LogEntry $logEntry)
    {
        $this->processLogEntryViaEvent($logEntry);
    }

    /**
     * php.shutdown event subscriber
     *
     * @return void
     */
    public function onShutdown()
    {
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
            $meta['channel'] = $logEntry->getChannel();
        }
        if ($logEntry['return']) {
            $args = $logEntry['return'];
        }
        $this->detectFiles = $logEntry->getMeta('detectFiles', false);
        $this->foundFiles = array();
        if ($meta['format'] == 'raw') {
            $args = $this->crateValues($args);
        }
        if (!empty($meta['backtrace'])) {
            $meta['backtrace'] = $this->crateValues($meta['backtrace']);
        }
        if ($this->detectFiles) {
            $meta['foundFiles'] = $this->foundFiles;
        }
        $this->wamp->publish($this->topic, array($logEntry['method'], $args, $meta));
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
     *     store property order
     *
     * @param array $values array structure
     *
     * @return array
     */
    private function crateValues($values)
    {
        $prevIntK = null;
        $storeKeyOrder = false;
        foreach ($values as $k => $v) {
            if (!$storeKeyOrder && \is_int($k)) {
                if ($k < $prevIntK) {
                    $storeKeyOrder = true;
                }
                $prevIntK = $k;
            }
            if (\is_array($v)) {
                if ($this->debug->abstracter->isAbstraction($v) && $v['type'] == 'object') {
                    $values[$k]['properties'] = self::crateValues($v['properties']);
                } else {
                    $values[$k] = self::crateValues($v);
                }
            } elseif (\is_string($v)) {
                if (!$this->debug->utf8->isUtf8($v)) {
                    $values[$k] = '_b64_:'.\base64_encode($v);
                } elseif ($this->detectFiles && !\preg_match('/[\r\n]/', $v) && \is_file($v)) {
                    $this->foundFiles[] = $v;
                }
            }
        }
        if ($storeKeyOrder) {
            $values['__debug_key_order__'] = \array_keys($values);
        }
        return $values;
    }

    /**
     * Publish pre-existing log entries
     *
     * @return void
     */
    private function processExistingData()
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
     * Process/publish a log entry
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    protected function processLogEntryViaEvent(LogEntry $logEntry)
    {
        $logEntry = new LogEntry($logEntry->getSubject(), $logEntry['method'], $logEntry['args'], $logEntry['meta']);
        $logEntry['outputAs'] = $this;
        $this->debug->internal->publishBubbleEvent('debug.outputLogEntry', $logEntry);
        $this->processLogEntry($logEntry);
    }

    /**
     * Publish initial meta data
     *
     * @return void
     */
    private function publishMeta()
    {
        $debugClass = \get_class($this->debug);
        $metaVals = array(
            'debug_version' => $debugClass::VERSION,
        );
        $keys = array(
            'HTTP_HOST',
            'HTTPS',
            'REMOTE_ADDR',
            'REQUEST_METHOD',
            'REQUEST_TIME',
            'REQUEST_URI',
            'SERVER_ADDR',
            'SERVER_NAME',
        );
        foreach ($keys as $k) {
            $metaVals[$k] = isset($_SERVER[$k])
                ? $_SERVER[$k]
                : null;
        }
        if (!isset($metaVals['REQUEST_URI']) && !empty($_SERVER['argv'])) {
            $metaVals['REQUEST_URI'] = '$: '. \implode(' ', $_SERVER['argv']);
        }
        $this->processLogEntry(new LogEntry(
            $this->debug,
            'meta',
            array(
                $metaVals,
                array(
                    'drawer' => $this->debug->getCfg('output.drawer'),
                    'channelRoot' => $this->debug->rootInstance->getCfg('channelName'),
                    'linkFilesTemplateDefault' => \strtr(
                        \ini_get('xdebug.file_link_format'),
                        array('%f'=>'%file', '%l'=>'%line')
                    ) ?: null,
                ),
            )
        ));
    }
}

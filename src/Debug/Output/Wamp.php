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
 * @version   v2.3
 */

namespace bdk\Debug\Output;

use bdk\Debug;
use bdk\PubSub\Event;
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
     * @param Event $event event object
     *
     * @return void
     */
    public function onError(Event $event)
    {
        if ($event['inConsole'] || !$event['isFirstOccur']) {
            return;
        }
        $this->processLogEntry(
            'errorNotConsoled',
            array(
                $event['typeStr'].': '.$event['file'].' (line '.$event['line'].'): '.$event['message']
            ),
            array(
                'channel' => 'phpError',
                'class' => $event['type'] & $this->debug->getCfg('errorMask')
                    ? 'danger'
                    : 'warning',
            )
        );
    }

    /**
     * debug.log event subscriber
     *
     * @param Event $event event object
     *
     * @return void
     */
    public function onLog(Event $event)
    {
        $this->processLogEntryWEvent($event['method'], $event['args'], $event['meta']);
    }

    /**
     * php.shutdown event subscriber
     *
     * @return void
     */
    public function onShutdown()
    {
        // publish a "we're done" message
        $this->processLogEntry(
            'endOutput',
            array(),
            array(
                'responseCode' => \http_response_code(),
                'channel' => $this->debug->getCfg('channelName'),
            )
        );
    }

    /**
     * Publish WAMP message to topic
     *
     * @param string $method debug method
     * @param array  $args   arguments
     * @param array  $meta   meta values
     *
     * @return void
     */
    public function processLogEntry($method, $args = array(), $meta = array())
    {
        $meta = \array_merge(array(
            'format' => 'raw',
            'requestId' => $this->requestId,
        ), $meta);
        if ($meta['channel'] == $this->debug->getCfg('channelName')) {
            unset($meta['channel']);
        }
        if ($meta['format'] == 'raw') {
            $args = $this->crateValues($args);
        }
        if (!empty($meta['backtrace'])) {
            $meta['backtrace'] = $this->crateValues($meta['backtrace']);
        }
        $this->wamp->publish($this->topic, array($method, $args, $meta));
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
            } elseif (\is_string($v) && !$this->debug->utf8->isUtf8($v)) {
                $values[$k] = '_b64_:'.\base64_encode($v);
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
        $channelName = $this->debug->getCfg('channelName');
        foreach ($data['alerts'] as $entry) {
            $this->processLogEntryWEvent($entry[0], $entry[1], $entry[2]);
        }
        foreach ($data['logSummary'] as $priority => $entries) {
            $this->processLogEntryWEvent(
                'groupSummary',
                array(),
                array(
                    'channel' => $channelName,
                    'priority'=> $priority,
                )
            );
            foreach ($entries as $entry) {
                $this->processLogEntryWEvent($entry[0], $entry[1], $entry[2]);
            }
            $this->processLogEntryWEvent(
                'groupEnd',
                array(),
                array(
                    'channel' => $channelName,
                    'closesSummary'=>true,
                )
            );
        }
        foreach ($data['log'] as $entry) {
            $this->processLogEntryWEvent($entry[0], $entry[1], $entry[2]);
        }
    }

    /**
     * Process/publish a log entry
     *
     * @param string $method method
     * @param array  $args   args
     * @param array  $meta   meta values
     *
     * @return void
     */
    protected function processLogEntryWEvent($method, $args = array(), $meta = array())
    {
        if (!isset($meta['channel'])) {
            $meta['channel'] = $this->channelName;
        }
        $event = $this->debug->eventManager->publish(
            'debug.outputLogEntry',
            $this,
            array(
                'method' => $method,
                'args' => $args,
                'meta' => $meta,
            )
        );
        if (!$event->isPropagationStopped()) {
            $this->processLogEntry($event['method'], $event['args'], $event['meta']);
        }
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
        $this->processLogEntry(
            'meta',
            array(
                $metaVals,
                array(
                    'channelRoot' => $this->debug->rootInstance->getCfg('channelName'),
                ),
            ),
            array(
                'channel' => $this->debug->getCfg('channelName'),
            )
        );
    }
}

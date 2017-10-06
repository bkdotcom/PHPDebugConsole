<?php
/**
 * Route PHPDebugConsole method calls to
 * WAMP (Web Application Messaging Protocol) router
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v2.0.0
 */

namespace bdk\Debug;

use bdk\Debug;
use bdk\PubSub\SubscriberInterface;
use bdk\PubSub\Event;
use bdk\WampPublisher;

/**
 * \bdk\Debug plugin for routing debug messsages thru WAMP server
 */
class OutputWamp implements SubscriberInterface
{

    protected $debug;
    protected $topic = 'bdk.debug';
    private $requestId;
    private $wamp;


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
    public function getSubscriptions()
    {
        $metaVals = array();
        foreach (array('HTTP_HOST','REMOTE_ADDR','REQUEST_METHOD','REQUEST_TIME','REQUEST_URI','SERVER_ADDR','SERVER_NAME') as $k) {
            $metaVals[$k] = isset($_SERVER[$k])
                ? $_SERVER[$k]
                : null;
        }
        if (!isset($metaVals['REQUEST_URI']) && !empty($_SERVER['argv'])) {
        	$metaVals['REQUEST_URI'] = '$: '. implode(' ', $_SERVER['argv']);
        }
        $this->publish('meta', $metaVals);
        /*
            Publish anything that's already been logged
        */
        $data = $this->debug->getData();
        foreach ($data['log'] as $args) {
            $method = array_shift($args);
            $meta = $this->debug->internal->getMetaArg($args);
            $this->publish($method, $args, $meta);
        }
        return array(
            'debug.log' => 'onLog',
            'debug.output' => 'onOutput',
            'errorHandler.error' => 'onError',    // assumes errorhandler is using same dispatcher.. as should be
        );
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
        if (!$event['inConsole'] && $event['firstOccur']) {
            $this->publish(
                'errorNotConsoled',
                array(
                    $event['typeStr'].': '.$event['file'].' (line '.$event['line'].'): '.$event['message']
                ),
                array(
                    'class' => $event['type'] & $this->debug->getCfg('errorMask')
                        ? 'danger'
                        : 'warning',
                )
            );
        }
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
        $this->publish($event['method'], $event['args'], $event['meta']);
    }

    /**
     * debug.output event subscriber
     *
     * @return void
     */
    public function onOutput()
    {
        // Send a "we're done" message
        $this->publish('endOutput', array(
            'responseCode' => http_response_code(),
        ));
    }

    /**
     * Publish
     *
     * @param string $method debug method
     * @param array  $args   arguments
     * @param array  $meta   meta values
     *
     * @return void
     */
    private function publish($method, $args = array(), $meta = array())
    {
        $args = $this->crateValues($args);
        $meta = array_merge(array(
            'requestId' => $this->requestId,
        ), $meta);
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
     *     in practice this seems to only be an issue with int/numberic keys
     *     store property order
     *
     * @param array $values array structure
     *
     * @return array
     */
    private function crateValues($values)
    {
        $prevIntK = null;
        $storeValues = false;
        foreach ($values as $k => $v) {
            if (!$storeValues && is_int($k)) {
                if ($k < $prevIntK) {
                    $storeValues = true;
                }
                $prevIntK = $k;
            }
            if (is_array($v)) {
                $values[$k] = self::crateValues($v);
            } elseif (is_string($v)) {
                $values[$k] = base64_encode($v);
            }
        }
        if ($storeValues) {
            $values['__debug_key_order__'] = array_keys($values);
        }
        return $values;
    }
}

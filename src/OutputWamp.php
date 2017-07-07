<?php
/**
 * Output log as HTML
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 */

namespace bdk\Debug;

/**
 * \bdk\Debug plugin for routing debug messsages thru WAMP server
 */
class OutputWamp implements PluginInterface
{
	// private $debug;
	private $wamp;
	private $requestId;

	/**
	 * Constructor
	 *
	 * @param \bdk\WampPublisher $wamp wamp instance
	 */
	public function __construct($wamp)
	{
		// $this->debug = $debug;
		$this->wamp = $wamp;
	}

	/**
	 * {@inheritdoc}
	 */
	public function debugListeners(\bdk\Debug $debug)
	{
		$this->requestId = $debug->getData('requestId');
		$metaVals = array();
		foreach (array('REQUEST_URI','REQUEST_TIME','HTTP_HOST','SERVER_NAME','SERVER_ADDR','REMOTE_ADDR') as $k) {
			$metaVals[$k] = isset($_SERVER[$k])
				? $_SERVER[$k]
				: null;
		}
		$this->publish('meta', $metaVals);
		return array(
			'debug.log' => 'onLog',
			'debug.output' => 'onOutput',
		);
	}

	/**
	 * debug.log event listener
	 *
	 * @param \bdk\Debug\Event $event event object
	 *
	 * @return void
	 */
	public function onLog(\bdk\Debug\Event $event)
	{
        $debug = $event->getSubject();
        $args = $event->getValues();
        $method = array_shift($args);
        $meta = $debug->output->getMetaArg($args);
        // $meta['requestId'] = $debug->getData('requestId');
        // $args = array($method, $args, $meta);
        // $this->wamp->publish('bdk.debug', $args);
        $this->publish($method, $args, $meta);
	}

	/**
	 * debug.output event listener
	 *
	 * @param \bdk\Debug\Event $event event object
	 *
	 * @return void
	 */
	public function onOutput(\bdk\Debug\Event $event)
	{
		/*
			Send a "we're done" message
		*/
		/*
        $debug = $event->getSubject();
		$args = array(
			'endOutput',
			array(
				'requestId' => $debug->getData('requestId'),
			),
		);
		*/
		$this->publish('endOutput');
	}

	/**
	 * pusblish
	 *
	 * @param string $method debug method
	 * @param array  $args   arguments
	 * @param array  $meta   meta values
	 *
	 * @return void
	 */
	private function publish($method, $args = array(), $meta = array())
	{
        array_walk_recursive($args, function (&$val) {
            /*
                base64_encode all strings!
                    a) strings with invalid utf-8 can't be json_encoded
                    b) "javascript has a unicode problem" / will munge strings
            */
            if (is_string($val) || is_int($val) || is_float($val)) {
                $val = base64_encode($val);
            }
        });
		$meta = array_merge(array(
			'requestId' => $this->requestId,
		), $meta);
		$this->wamp->publish('bdk.debug', array($method, $args, $meta));
	}
}

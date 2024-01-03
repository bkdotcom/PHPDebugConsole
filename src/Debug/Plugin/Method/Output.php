<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Output method
 */
class Output implements SubscriberInterface
{
    use CustomMethodTrait;

    protected $methods = array(
        'output',
    );

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
    }

    /**
     * Return debug log output
     *
     * Publishes `Debug::EVENT_OUTPUT` event and returns event's 'return' value
     *
     * If output config value is `false`, null will be returned.
     *
     * Note: Log output is handled automatically, and calling output is gnerally not necessary.
     *
     * @param array $cfg Override any config values
     *
     * @return string|null
     */
    public function output($cfg = array())
    {
        $debug = $this->debug;
        $cfgRestore = $debug->config->set($cfg);
        if ($debug->getCfg('output', Debug::CONFIG_DEBUG) === false) {
            $debug->config->set($cfgRestore);
            $debug->obEnd();
            return null;
        }
        $event = $this->publishOutputEvent();
        if (!$debug->parentInstance) {
            $debug->data->set('outputSent', true);
        }
        $debug->config->set($cfgRestore);
        $debug->obEnd();
        return $event['return'];
    }

    /**
     * Publish Debug::EVENT_OUTPUT
     *    on all descendant channels
     *    rootInstance
     *    finally ourself
     * This isn't outputing each channel, but for performing any per-channel "before output" activities
     *
     * @return \bdk\PubSub\Event
     */
    private function publishOutputEvent()
    {
        $debug = $this->debug;
        $channels = $debug->getChannels(true);
        if ($debug !== $debug->rootInstance) {
            $channels[] = $debug->rootInstance;
        }
        $channels[] = $debug;
        foreach ($channels as $channel) {
            if ($channel->getCfg('output', Debug::CONFIG_DEBUG) === false) {
                continue;
            }
            $event = $channel->eventManager->publish(
                Debug::EVENT_OUTPUT,
                $channel,
                array(
                    'headers' => array(),
                    'isTarget' => $channel === $debug,
                    'return' => '',
                )
            );
        }
        return $event;
    }
}

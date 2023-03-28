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

namespace bdk\Debug;

use bdk\Debug;

/**
 * Internal Helper methods
 */
class Internal
{
    private $debug;

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
     * Determine default route
     *
     * @return string
     */
    public function getDefaultRoute()
    {
        $interface = $this->debug->getInterface();
        if (\strpos($interface, 'ajax') !== false) {
            return $this->debug->getCfg('routeNonHtml', Debug::CONFIG_DEBUG);
        }
        if ($interface === 'http') {
            $contentType = $this->debug->getResponseHeader('Content-Type');
            if ($contentType && \strpos($contentType, 'text/html') === false) {
                return $this->debug->getCfg('routeNonHtml', Debug::CONFIG_DEBUG);
            }
            return 'html';
        }
        return 'stream';
    }

    /**
     * Create config meta argument/value
     *
     * @param string|array $key key or array of key/values
     * @param mixed        $val config value
     *
     * @return array
     */
    public function metaCfg($key, $val)
    {
        if (\is_array($key)) {
            return array(
                'cfg' => $key,
                'debug' => Debug::META,
            );
        }
        if (\is_string($key)) {
            return array(
                'cfg' => array(
                    $key => $val,
                ),
                'debug' => Debug::META,
            );
        }
        // invalid cfg key / return empty meta array
        return array('debug' => Debug::META);
    }

    /**
     * Publish Debug::EVENT_OUTPUT
     *    on all descendant channels
     *    rootInstance
     *    finally ourself
     * This isn't outputing each channel, but for performing any per-channel "before output" activities
     *
     * @return string output
     */
    public function publishOutputEvent()
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
        return $event['return'];
    }
}

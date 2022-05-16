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

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Channel management
 */
class Channel implements SubscriberInterface
{
    use CustomMethodTrait;

    private $channels = array();

    protected $methods = array(
        'getChannel',
        'getChannels',
        'getChannelsTop',
        'getPropagateValues',
    );

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
        );
    }

    /**
     * Return a named sub-instance... if channel does not exist, it will be created
     *
     * Channels can be used to categorize log data... for example, may have a framework channel, database channel, library-x channel, etc
     * Channels may have subchannels
     *
     * @param string $name   channel name
     * @param array  $config channel specific configuration
     *
     * @return \bdk\Debug new or existing `Debug` instance
     */
    public function getChannel($name, $config = array())
    {
        // Split on "."
        // Split on "/" not adjacent to whitespace
        $names = \is_string($name)
            ? \preg_split('#(\.|(?<!\s)/(?!\s))#', $name)
            : $name;
        $name = \array_shift($names);
        if ($name === $this->debug->rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG)) {
            return $names
                ? $this->debug->rootInstance->getChannel($names, $config)
                : $this->debug->rootInstance;
        }
        if (!isset($this->channels[$name])) {
            $this->channels[$name] = $this->createChannel($name, $names
                ? array()
                : $config);
        }
        $channel = $this->channels[$name];
        if ($names) {
            $channel = $channel->getChannel($names, $config);
        }
        unset($config['nested']);
        if ($config) {
            $channel->setCfg($config);
        }
        return $channel;
    }

    /**
     * Return array of channels
     *
     * If $allDescendants == true :  key = "fully qualified" channel name
     *
     * @param bool $allDescendants (false) include all descendants?
     * @param bool $inclTop        (false) whether to incl topmost channels (ie "tabs")
     *
     * @return \bdk\Debug[] Does not include self
     */
    public function getChannels($allDescendants = false, $inclTop = false)
    {
        $channels = $this->channels;
        if ($allDescendants) {
            $channels = array();
            foreach ($this->channels as $channel) {
                $channelName = $channel->getCfg('channelName', Debug::CONFIG_DEBUG);
                $channels = \array_merge(
                    $channels,
                    array(
                        $channelName => $channel,
                    ),
                    $channel->getChannels(true)
                );
            }
        }
        if ($inclTop) {
            return $channels;
        }
        if ($this->debug === $this->debug->rootInstance) {
            $channelsTop = $this->getChannelsTop();
            $channels = \array_diff_key($channels, $channelsTop);
        }
        return $channels;
    }

    /**
     * Get the topmost channels (ie "tabs")
     *
     * (includes the general/root channel)
     *
     * @return \bdk\Debug[]
     */
    public function getChannelsTop()
    {
        $channelName = $this->debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        $channels = array(
            $channelName => $this->debug,
        );
        if ($this->debug->parentInstance) {
            return $channels;
        }
        foreach ($this->debug->rootInstance->getChannels(false, true) as $name => $channel) {
            $fqn = $channel->getCfg('channelName', Debug::CONFIG_DEBUG);
            if (\strpos($fqn, '.') === false) {
                $channels[$name] = $channel;
            }
        }
        return $channels;
    }

    /**
     * Remove config values that should not be propagated to children channels
     *
     * @param array $cfg config array
     *
     * @return array
     */
    public function getPropagateValues($cfg)
    {
        $cfg = \array_diff_key($cfg, \array_flip(array(
            'errorHandler',
            'routeStream',
        )));
        $cfg['debug'] = \array_diff_key($cfg['debug'], \array_flip(array(
            'channelIcon',
            'channelName',
            'onBootstrap',
            'route',
        )));
        foreach ($cfg as $k => $v) {
            if ($v === array()) {
                unset($cfg[$k]);
            }
        }
        return $cfg;
    }

    /**
     * Create a child channel
     *
     * @param string $name   Channel name
     * @param array  $config channel config
     *
     * @return array
     */
    private function createChannel($name, $config)
    {
        $cfg = $this->debug->getCfg(null, Debug::CONFIG_INIT);
        $channelNameCur = $cfg['debug']['channelName'];
        $cfg = $this->getPropagateValues($cfg);
        $cfg['debug'] = \array_merge(
            $cfg['debug'],
            array(
                'channelIcon' => null,
                'nested' => true, // true = regular child channel, false = tab
            ),
            isset($cfg['debug']['channels'][$name])
                ? $cfg['debug']['channels'][$name]
                : array(),
            $config
        );
        $cfg['debug']['channelName'] = $cfg['debug']['nested'] || $this->debug->parentInstance
            ? $channelNameCur . '.' . $name
            : $name;
        $cfg['debug']['parent'] = $this->debug;
        unset($cfg['debug']['nested']);
        return new Debug($cfg);
    }
}

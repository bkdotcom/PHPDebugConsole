<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
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

    /**
     * Split on "."
     * Split on "/" not adjacent to whitespace
     *
     * @var string
     */
    const NAME_SPLIT_REGEX = '#(\.|(?<!\s)/(?!\s))#';

    /** @var array<string,Debug> */
    private $channels = array();

    /** @var string[] */
    protected $methods = [
        'getChannel',
        'getChannels',
        'getChannelsTop',
        'getPropagateValues',
        'hasChannel',
    ];

    /**
     * Return a named sub-instance... if channel does not exist, it will be created
     *
     * Channels can be used to categorize log data... for example, may have a framework channel, database channel, library-x channel, etc
     * Channels may have subchannels
     *
     * @param string|array $name   channel name (or channel path)
     * @param array        $config channel specific configuration
     *
     * @return Debug new or existing `Debug` instance
     *
     * @since 2.3
     */
    public function getChannel($name, $config = array())
    {
        $names = \is_string($name)
            ? \preg_split(self::NAME_SPLIT_REGEX, $name)
            : $name;
        $name = \array_shift($names);
        if ($name === $this->debug->rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG)) {
            return $names
                ? $this->debug->rootInstance->getChannel($names, $config)
                : $this->debug->rootInstance;
        }
        $curChannelName = $this->debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        if (!isset($this->channels[$curChannelName][$name])) {
            $this->channels[$curChannelName][$name] = $this->createChannel($name, $names
                ? array()
                : $config);
        }
        $channel = $this->channels[$curChannelName][$name];
        if ($names) {
            $channel = $channel->getChannel($names, $config);
        }
        unset($config['nested']);
        if ($config) {
            $channel->setCfg($config, Debug::CONFIG_NO_RETURN);
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
        $curChannelName = $this->debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        $channels = isset($this->channels[$curChannelName])
            ? $this->channels[$curChannelName]
            : array();
        if ($allDescendants) {
            $debug = $this->debug;
            $channelsNew = array();
            foreach ($channels as $channel) {
                $channelName = $channel->getCfg('channelName', Debug::CONFIG_DEBUG);
                $channelsNew = \array_merge(
                    $channelsNew,
                    array(
                        $channelName => $channel,
                    ),
                    $channel->getChannels(true)
                );
            }
            $channels = $channelsNew;
            $this->debug = $debug;
        }
        return $inclTop === false && $this->debug === $this->debug->rootInstance
            ? \array_diff_key($channels, $this->getChannelsTop())
            : $channels;
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
        $cfg = \array_diff_key($cfg, \array_flip([
            'errorHandler',
            'routeStream',
        ]));
        $cfg['debug'] = \array_diff_key($cfg['debug'], \array_flip([
            'channelIcon',
            'channelName',
            'onBootstrap',
            'onLog',
            'onOutput',
            'route',
        ]));
        foreach ($cfg as $k => $v) {
            if ($v === array()) {
                unset($cfg[$k]);
            }
        }
        return $cfg;
    }

    /**
     * Has channel been created?
     *
     * @param string|array $name channel name (or channel path)
     *
     * @return bool
     */
    public function hasChannel($name)
    {
        $names = \is_string($name)
            ? \preg_split(self::NAME_SPLIT_REGEX, $name)
            : $name;
        $name = \array_shift($names);
        if ($name === $this->debug->rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG)) {
            return $names
                ? $this->debug->rootInstance->hasChannel($names)
                : true;
        }
        $curChannelName = $this->debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        if (isset($this->channels[$curChannelName][$name]) === false) {
            return false;
        }
        return $names
            ? $this->channels[$curChannelName][$name]->hasChannel($names)
            : true;
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

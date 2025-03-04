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
     * Channels may have sub-channels
     *
     * @param string|array $path   channel key/name/path
     * @param array        $config channel specific configuration
     *
     * @return Debug new or existing `Debug` instance
     *
     * @since 2.3
     */
    public function getChannel($path, $config = array())
    {
        $path = \is_string($path)
            ? \preg_split(self::NAME_SPLIT_REGEX, $path)
            : $path;
        $key = \array_shift($path);
        if ($key === $this->debug->rootInstance->getCfg('channelKey', Debug::CONFIG_DEBUG)) {
            return $path
                ? $this->debug->rootInstance->getChannel($path, $config)
                : $this->debug->rootInstance;
        }
        $curChannelKey = $this->debug->getCfg('channelKey', Debug::CONFIG_DEBUG);
        if (!isset($this->channels[$curChannelKey][$key])) {
            $this->channels[$curChannelKey][$key] = $this->createChannel($key, $path
                ? array()
                : $config);
        }
        $channel = $this->channels[$curChannelKey][$key];
        if ($path) {
            $channel = $channel->getChannel($path, $config);
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
        $curChannelKey = $this->debug->getCfg('channelKey', Debug::CONFIG_DEBUG);
        $channels = isset($this->channels[$curChannelKey])
            ? $this->channels[$curChannelKey]
            : array();
        if ($allDescendants) {
            $debug = $this->debug;
            $channelsNew = array();
            foreach ($channels as $channel) {
                $channelKey = $channel->getCfg('channelKey', Debug::CONFIG_DEBUG);
                $channelsNew = \array_merge(
                    $channelsNew,
                    array(
                        $channelKey => $channel,
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
        $channelKey = $this->debug->getCfg('channelKey', Debug::CONFIG_DEBUG);
        $channels = array(
            $channelKey => $this->debug,
        );
        if ($this->debug->parentInstance) {
            return $channels;
        }
        foreach ($this->debug->rootInstance->getChannels(false, true) as $key => $channel) {
            $fqn = $channel->getCfg('channelKey', Debug::CONFIG_DEBUG);
            if (\strpos($fqn, '.') === false) {
                $channels[$key] = $channel;
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
            'channelKey',
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
     * @param string|array $path channel name (or channel path)
     *
     * @return bool
     */
    public function hasChannel($path)
    {
        $path = \is_string($path)
            ? \preg_split(self::NAME_SPLIT_REGEX, $path)
            : $path;
        $key = \array_shift($path);
        if ($key === $this->debug->rootInstance->getCfg('channelKey', Debug::CONFIG_DEBUG)) {
            return $path
                ? $this->debug->rootInstance->hasChannel($path)
                : true;
        }
        $curChannelKey = $this->debug->getCfg('channelKey', Debug::CONFIG_DEBUG);
        if (isset($this->channels[$curChannelKey][$key]) === false) {
            return false;
        }
        return $path
            ? $this->channels[$curChannelKey][$key]->hasChannel($path)
            : true;
    }

    /**
     * Create a child channel
     *
     * @param string $key    Channel name
     * @param array  $config channel config
     *
     * @return array
     */
    private function createChannel($key, $config)
    {
        $cfg = $this->debug->getCfg(null, Debug::CONFIG_INIT);
        $channelKeyCur = $cfg['debug']['channelKey'];
        $cfg = $this->getPropagateValues($cfg);
        $cfg['debug'] = \array_merge(
            $cfg['debug'],
            array(
                'channelIcon' => null,
                'channelName' => $key,
                'nested' => true, // true = regular child channel, false = tab
            ),
            isset($cfg['debug']['channels'][$key])
                ? $cfg['debug']['channels'][$key]
                : array(),
            $config
        );
        $cfg['debug']['channelKey'] = $cfg['debug']['nested'] || $this->debug->parentInstance
            ? $channelKeyCur . '.' . $key
            : $key;
        $cfg['debug']['parent'] = $this->debug;
        unset($cfg['debug']['nested']);
        return new Debug($cfg);
    }
}

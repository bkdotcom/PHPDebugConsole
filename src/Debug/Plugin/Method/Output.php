<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Output method
 */
class Output implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var string[] */
    protected $methods = [
        'output',
    ];

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
     * Note: Log output is handled automatically, and calling output is generally not necessary.
     *
     * @param array|bool $returnOrCfg (true)
     *                                if array, treated as config values,
     *                                if bool, whether to return (true) or echo output (false)
     *
     * @return string|null
     *
     * @since 1.2 explicitly calling output() is no longer necessary.. log will be output automatically via shutdown function
     * @since 2.3 `$cfg` parameter
     * @since 3.5 now accepts array or bool as first parameter
     */
    public function output($returnOrCfg = true)
    {
        $debug = $this->debug;
        $cfg = $this->outputNormalizeCfg($returnOrCfg);
        $return = $cfg['return'];
        unset($cfg['return']);
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
        if ($return === true) {
            return $event['return'];
        }
        echo $event['return'];
    }

    /**
     * Convert output parameter to array
     *
     * @param array|bool $returnOrCfg Return output or config values
     *
     * @return array
     */
    private function outputNormalizeCfg($returnOrCfg)
    {
        if (\is_bool($returnOrCfg)) {
            return array(
                'return' => $returnOrCfg,
            );
        }
        if (\is_array($returnOrCfg) === false) {
            $returnOrCfg = array();
        }
        return \array_merge(array(
            'return' => true,
        ), $returnOrCfg);
    }

    /**
     * Publish Debug::EVENT_OUTPUT
     *    on all descendant channels
     *    rootInstance
     *    finally ourself
     * This isn't outputting each channel, but for performing any per-channel "before output" activities
     *
     * @return Event
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
        /** @var Event */
        return $event;
    }
}

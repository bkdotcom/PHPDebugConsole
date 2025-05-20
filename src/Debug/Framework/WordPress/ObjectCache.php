<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Log cache info
 */
class ObjectCache implements SubscriberInterface
{
    const I18N_DOMAIN = 'wordpress';

    /** @var array<string,mixed> */
    protected $cfg = array(
        'enabled' => true,
    );

    /** @var Debug */
    protected $debug;

    /** @var int total cache size (in bytes) */
    protected $totalCacheSize = 0;

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OUTPUT => 'onOutput',
        );
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * @param Event $event Debug::EVENT_OUTPUT event object
     *
     * @return void
     */
    public function onOutput(Event $event)
    {
        if ($this->cfg['enabled'] === false) {
            return;
        }

        $this->debug = $event->getSubject()->getChannel('cache', array(
            'channelIcon' => ':cache:',
            'channelName' => 'channel.cache|trans',
            'channelSort' => 1,
            'nested' => false,
        ));

        $this->debug->log($this->debug->i18n->trans('cache.hits', [], self::I18N_DOMAIN), $GLOBALS['wp_object_cache']->cache_hits);
        $this->debug->log($this->debug->i18n->trans('cache.misses', [], self::I18N_DOMAIN), $GLOBALS['wp_object_cache']->cache_misses);

        $cacheInfo = $this->getCacheInfo();
        $this->debug->table($cacheInfo, $this->debug->meta('tableInfo', array(
            'columns' => array(
                'size' => array(
                    'attribs' => array('class' => ['no-quotes']),
                    'total' => $this->debug->utility->getBytes($this->totalCacheSize),
                ),
            ),
        )));
    }

    /**
     * Get cache info (group -> size)
     *
     * @return array
     */
    private function getCacheInfo()
    {
        $cacheInfo = array();
        foreach ($GLOBALS['wp_object_cache']->cache as $group => $cache) {
            $serialized = \serialize($cache);
            $size = \strlen($serialized);
            $this->totalCacheSize += $size;
            $cacheInfo[$group] = array(
                'size' => $this->debug->utility->getBytes($size),
            );
        }
        \ksort($cacheInfo);
        return $cacheInfo;
    }
}

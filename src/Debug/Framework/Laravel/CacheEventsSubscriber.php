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

namespace bdk\Debug\Framework\Laravel;

use bdk\Debug;
use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Events\Dispatcher;

/**
 * Log cache events
 */
class CacheEventsSubscriber
{
    /** @var array */
    protected $classMap = array(
        CacheHit::class => 'hit',
        CacheMissed::class => 'missed',
        KeyForgotten::class => 'forgotten',
        KeyWritten::class => 'written',
    );

    protected $debug;
    protected $options = array(
        'collectValues' => true,
        'icon' => 'fa fa-cube',
    );
    protected $loggedActions = array();

    /**
     * Constructor
     *
     * @param array $options Options
     * @param Debug $debug   (optional) Specify PHPDebugConsole instance
     *                         if not passed, will create PDO channel on singleton instance
     *                         if root channel is specified, will create a PDO channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($options = array(), Debug $debug = null)
    {
        $this->options = \array_merge($this->options, $options);
        $channelOptions = array(
            'channelIcon' => $this->options['icon'],
            'channelShow' => false,
        );
        if (!$debug) {
            $debug = Debug::_getChannel('cache', $channelOptions);
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('cache', $channelOptions);
        }
        $this->debug = $debug;
    }

    /**
     * Log cache event
     *
     * @param CacheEvent $event cache event instance
     *
     * @return void
     */
    public function onCacheEvent(CacheEvent $event)
    {
        $label = $this->classMap[\get_class($event)];
        $params = \get_object_vars($event);
        $this->debug->log($label, $params);
    }

    /**
     * Subscribe to events
     *
     * @param Dispatcher $dispatcher Dispater interface
     *
     * @return void
     */
    public function subscribe(Dispatcher $dispatcher)
    {
        foreach (\array_keys($this->classMap) as $eventClass) {
            $dispatcher->listen($eventClass, array($this, 'onCacheEvent'));
        }
    }
}

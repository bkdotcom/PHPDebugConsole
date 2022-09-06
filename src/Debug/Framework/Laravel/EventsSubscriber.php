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
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;

/**
 * Log Laravel events
 */
class EventsSubscriber
{
    /** @var Debug */
    protected $debug;

    /** @var Dispatcher */
    protected $eventDispatcher;

    protected $icon = 'fa fa-bell-o';

    /**
     * Constructor
     *
     * @param Debug $debug (optional) Specify PHPDebugConsole instance
     *                       if not passed, will create PDO channel on singleton instance
     *                       if root channel is specified, will create a PDO channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(Debug $debug = null)
    {
        $channelOptions = array(
            'channelIcon' => $this->icon,
            'channelShow' => false,
        );
        if (!$debug) {
            $debug = Debug::_getChannel('events', $channelOptions);
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('events', $channelOptions);
        }
        $this->debug = $debug;
    }

    /**
     * Wildcard event listener
     *
     * @param string $name    event name
     * @param array  $payload event payload
     *
     * @return void
     */
    public function onWildcardEvent($name = null, $payload = array())
    {
        $groupParams = array($name);
        if (\preg_match('/^(\S+):\s+(\S+)$/', $name, $matches)) {
            $groupParams = array($matches[1], $matches[2]);
        }
        $groupParams[] = $this->debug->meta(array(
            'icon' => $this->icon,
            'argsAsParams' => false,
        ));
        $this->debug->groupCollapsed(...$groupParams); // php 5.6+
        $this->logPayload($payload);
        $this->debug->groupEnd();
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
        $this->eventDispatcher = $dispatcher;
        $dispatcher->listen('*', array($this, 'onWildcardEvent'));
    }

    /**
     * Log listeners for given event name
     * Unable to log anything meaningful
     * Just a bunch of Closures created via Dispatcher
     *
     * @param string $eventName event name
     *
     * @return void
     */
    private function logListeners($eventName)
    {
        $listeners = $this->eventDispatcher->getListeners($eventName);
        foreach ($listeners as $i => $listener) {
            if (!($listener instanceof \Closure)) {
                continue;
            }
            $ref = new \ReflectionFunction($listener);
            $filename = $ref->getFileName();
            // ourself
            if (\strpos($filename, '/Illuminate/Events/Dispatcher.php') !== false) {
                unset($listeners[$i]);
                break;
            }
        }
        $this->debug->log('listeners', $listeners);
    }

    /**
     * Log payload sent to event subscriber
     *
     * @param array $payload Event payload
     *
     * @return void
     */
    private function logPayload($payload)
    {
        $cfg = array(
            'objectsWhitelist' => array(),
        );
        foreach ($payload as $val) {
            if (\is_object($val) && Str::is('Illuminate\*\Events\*', \get_class($val))) {
                $cfg['objectsWhitelist'][] = \get_class($val);
            }
        }
        $this->debug->log('payload', $payload, $this->debug->meta('cfg', $cfg));
    }
}

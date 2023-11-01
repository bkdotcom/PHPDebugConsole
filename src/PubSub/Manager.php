<?php

/**
 * Manage event subscriptions
 *
 * @package   bdk\PubSub
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 * @link      http://www.github.com/bkdotcom/PubSub
 */

namespace bdk\PubSub;

use bdk\PubSub\ManagerHelperTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Event publish/subscribe event manager
 */
class Manager
{
    use ManagerHelperTrait;

    const DEFAULT_PRIORITY = 0;
    const EVENT_PHP_SHUTDOWN = 'php.shutdown';

    protected $subscribers = array();
    protected $sorted = array();
    protected $subscriberStack = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        /*
            As a convenience, make shutdown subscribeable
        */
        \register_shutdown_function(function () {
            $this->publish(self::EVENT_PHP_SHUTDOWN); // @codeCoverageIgnore
        });
    }

    /**
     * Subscribe to all of the event subscribers provided by passed object
     *
     * Calls `$interface`'s `getInterfaceSubscribers` method and subscribes accordingly
     *
     * @param SubscriberInterface $interface object implementing subscriber interface
     *
     * @return array A normalized list of subscriptions added.
     */
    public function addSubscriberInterface(SubscriberInterface $interface)
    {
        $subscribersByEvent = $this->getInterfaceSubscribers($interface);
        foreach ($subscribersByEvent as $eventName => $eventSubscribers) {
            foreach ($eventSubscribers as $subscriberInfo) {
                $this->subscribe(
                    $eventName,
                    $subscriberInfo['callable'],
                    $subscriberInfo['priority'],
                    $subscriberInfo['onlyOnce']
                );
            }
        }
        return $subscribersByEvent;
    }

    /**
     * Gets the subscribers of a specific event or all subscribers sorted by descending priority.
     *
     * If event name is not specified, subscribers for all events will be returned
     *
     * @param string $eventName The name of the event
     *
     * @return array The event subscribers for the specified event, or all event subscribers by event name
     */
    public function getSubscribers($eventName = null)
    {
        if ($eventName !== null) {
            if (!isset($this->subscribers[$eventName])) {
                return array();
            }
            $this->setSorted($eventName);
            return $this->sorted[$eventName];
        }
        // return all subscribers
        foreach (\array_keys($this->subscribers) as $eventName) {
            $this->setSorted($eventName);
        }
        return \array_filter($this->sorted);
    }

    /**
     * Checks whether an event has any registered subscribers.
     *
     * @param string $eventName The name of the event
     *
     * @return bool
     */
    public function hasSubscribers($eventName = null)
    {
        if ($eventName !== null) {
            return !empty($this->subscribers[$eventName]);
        }
        foreach ($this->subscribers as $subscribers) {
            if ($subscribers) {
                return true;
            }
        }
        return false;
    }

    /**
     * Publish/Trigger/Dispatch event
     *
     * @param string $eventName      event name
     * @param mixed  $eventOrSubject passed to subscribers
     * @param array  $values         values to attach to event
     *
     * @return Event
     */
    public function publish($eventName, $eventOrSubject = null, array $values = array())
    {
        $event = $eventOrSubject instanceof Event
            ? $eventOrSubject
            : new Event($eventOrSubject, $values);
        $subscribers = $this->getSubscribers($eventName);
        $this->doPublish($eventName, $subscribers, $event);
        return $event;
    }

    /**
     * Unsubscribe from all of the event subscribers provided by passed object
     *
     * Calls `$interface`'s `getInterfaceSubscribers` method and unsubscribes accordingly
     *
     * @param SubscriberInterface $interface object implementing subscriber interface
     *
     * @return array[] normalized list of subscriptions removed.
     */
    public function removeSubscriberInterface(SubscriberInterface $interface)
    {
        $subscribersByEvent = $this->getInterfaceSubscribers($interface);
        foreach ($subscribersByEvent as $eventName => $eventSubscribers) {
            foreach ($eventSubscribers as $subscriberInfo) {
                $this->unsubscribe($eventName, $subscriberInfo['callable']);
            }
        }
        return $subscribersByEvent;
    }

    /**
     * Subscribe to event
     *
     * # Callable will receive 3 params:
     *  * Event
     *  * (string) eventName
     *  * EventManager
     *
     * # Lazy-load the subscriber
     *   It's possible to lazy load the subscriber object via a "closure factory"
     *    `array(Closure, 'methodName')` - closure returns object
     *    `array(Closure)` - closure returns object that is callable (ie has `__invoke` method)
     *   The closure will be called the first time the event occurs
     *
     * @param string         $eventName event name
     * @param callable|array $callable  callable or closure factory
     * @param int            $priority  The higher this value, the earlier we handle event
     * @param bool           $onlyOnce  (false) Auto-unsubscribe after first invocation
     *
     * @return void
     */
    public function subscribe($eventName, $callable, $priority = 0, $onlyOnce = false)
    {
        unset($this->sorted[$eventName]); // clear the sorted cache
        $this->assertCallable($callable);
        $subscriberInfo = array(
            'callable' => $callable,
            'onlyOnce' => $onlyOnce,
            'priority' => $priority,
        );
        $this->subscribers[$eventName][$priority][] = $subscriberInfo;
        // add to active event subscribers
        foreach ($this->subscriberStack as $i => $stackInfo) {
            if ($stackInfo['eventName'] === $eventName) {
                $this->subscribeActive($i, $subscriberInfo);
            }
        }
    }

    /**
     * Removes an event subscriber from the specified event.
     *
     * @param string         $eventName The event we're unsubscribing from
     * @param callable|array $callable  The subscriber to remove
     *
     * @return void
     */
    public function unsubscribe($eventName, $callable)
    {
        if ($this->isClosureFactory($callable)) {
            $callable = $this->doClosureFactory($callable);
        }
        $this->prepSubscribers($eventName);
        $priorities = \array_keys($this->subscribers[$eventName]);
        foreach ($priorities as $priority) {
            $this->unsubscribeFromPriority($eventName, $callable, $priority, false);
        }
        // remove from any active events
        foreach ($this->subscriberStack as $i => $stackInfo) {
            if ($stackInfo['eventName'] === $eventName) {
                $this->unsubscribeActive($i, $callable, $priority);
            }
        }
    }

    /**
     * Calls the subscribers of an event.
     *
     * @param string $eventName   The name of the event to publish
     * @param array  $subscribers The event subscribers
     * @param Event  $event       The event object to pass to the subscribers
     *
     * @return void
     */
    protected function doPublish($eventName, $subscribers, Event $event)
    {
        $this->subscriberStack[] = array(
            'eventName' => $eventName,
            'subscribers' => $subscribers,
        );
        $stackIndex = \count($this->subscriberStack) - 1;
        $subscribers = &$this->subscriberStack[$stackIndex]['subscribers'];
        while ($subscribers) {
            if ($event->isPropagationStopped()) {
                break;
            }
            $subscriberInfo = \array_shift($subscribers);
            $return = \call_user_func($subscriberInfo['callable'], $event, $eventName, $this);
            $this->attachReturnToEvent($return, $event);
            if ($subscriberInfo['onlyOnce']) {
                $this->unsubscribeFromPriority($eventName, $subscriberInfo['callable'], $subscriberInfo['priority'], true);
            }
        }
        \array_pop($this->subscriberStack);
    }
}

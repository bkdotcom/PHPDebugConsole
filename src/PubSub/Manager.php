<?php

/**
 * Manage event subscriptions
 *
 * @package   bdk\PubSub
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 * @link      http://www.github.com/bkdotcom/PubSub
 */

namespace bdk\PubSub;

/**
 * Event publish/subscribe event manager
 */
class Manager
{

    private $subscribers = array();
    private $sorted = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        /*
            As a convenience, make shutdown subscribeable
        */
        \register_shutdown_function(function () {
            $this->publish('php.shutdown');
        });
    }

    /**
     * Subscribe to all of the event subscribers provided by passed object
     *
     * Calls `$interface`'s `getInterfaceSubscribers` method and subscribes accordingly
     *
     * @param SubscriberInterface $interface object implementing subscriber interface
     *
     * @return array a normalized list of subscriptions added.
     *      each returned is array(eventName, callable, priority)
     */
    public function addSubscriberInterface(SubscriberInterface $interface)
    {
        $subscribers = $this->getInterfaceSubscribers($interface);
        foreach ($subscribers as $row) {
            $this->subscribe($row[0], $row[1], $row[2]);
        }
        return $subscribers;
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
            if (!isset($this->sorted[$eventName])) {
                $this->sortSubscribers($eventName);
            }
            return $this->sorted[$eventName];
        }
        // return all subscribers
        foreach (\array_keys($this->subscribers) as $eventName) {
            if (!isset($this->sorted[$eventName])) {
                $this->sortSubscribers($eventName);
            }
        }
        return \array_filter($this->sorted);
    }

    /**
     * Checks whether an event has any registered subscribers.
     *
     * @param string $eventName The name of the event
     *
     * @return boolean
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
        if ($eventOrSubject instanceof Event) {
            $event = $eventOrSubject;
        } else {
            $event = new Event($eventOrSubject, $values);
        }
        $subscribers = $this->getSubscribers($eventName);
        if ($subscribers) {
            $this->doPublish($eventName, $subscribers, $event);
        }
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
     *      each returned is `array(eventName, callable, priority)`
     */
    public function removeSubscriberInterface(SubscriberInterface $interface)
    {
        $subscribers = $this->getInterfaceSubscribers($interface);
        foreach ($subscribers as $row) {
            $this->unsubscribe($row[0], $row[1]);
        }
        return $subscribers;
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
     * @param string   $eventName event name
     * @param callable $callable  callable or closure factory
     * @param integer  $priority  The higher this value, the earlier we handle event
     *
     * @return void
     */
    public function subscribe($eventName, $callable, $priority = 0)
    {
        unset($this->sorted[$eventName]);           // clear the sorted cache
        $this->subscribers[$eventName][$priority][] = $callable;
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
        if (!isset($this->subscribers[$eventName])) {
            return;
        }
        if ($this->isClosureFactory($callable)) {
            $callable = $this->doClosureFactory($callable);
        }
        foreach ($this->subscribers[$eventName] as $priority => $subscribers) {
            foreach ($subscribers as $k => $v) {
                if ($v !== $callable && $this->isClosureFactory($v)) {
                    $v = $this->doClosureFactory($v);
                }
                if ($v === $callable) {
                    unset($subscribers[$k], $this->sorted[$eventName]);
                } else {
                    $subscribers[$k] = $v;
                }
            }
            if ($subscribers) {
                $this->subscribers[$eventName][$priority] = $subscribers;
            } else {
                unset($this->subscribers[$eventName][$priority]);
            }
        }
    }

    /**
     * Instantiate the object wrapped in the closure factory
     * closure factory may be
     *    [Closure, 'methodName'] - closure returns object
     *    [Closure] - closure returns object that is callable (ie has __invoke)
     *
     * @param array $closureFactory "closure factory" lazy loads an object / subscriber
     *
     * @return callable
     */
    private function doClosureFactory($closureFactory = array())
    {
        $closureFactory[0] = $closureFactory[0]();
        return \count($closureFactory) === 1
            ? $closureFactory[0]
            : $closureFactory;
    }

    /**
     * Calls the subscribers of an event.
     *
     * @param string     $eventName   The name of the event to publish
     * @param callable[] $subscribers The event subscribers
     * @param Event      $event       The event object to pass to the subscribers
     *
     * @return void
     */
    protected function doPublish($eventName, $subscribers, Event $event)
    {
        foreach ($subscribers as $callable) {
            if ($event->isPropagationStopped()) {
                break;
            }
            \call_user_func($callable, $event, $eventName, $this);
        }
    }

    /**
     * Does val appear to be a "closure factory"?
     * array & array[0] instanceof Closure
     *
     * @param mixed $val value to check
     *
     * @return boolean
     */
    private function isClosureFactory($val)
    {
        return \is_array($val) && isset($val[0]) && $val[0] instanceof \Closure;
    }

    /**
     * Calls the passed object's getSubscriptions() method and returns a normalized list of subscriptions
     *
     * @param SubscriberInterface $interface object implementing subscriber interface
     *
     * @return array
     */
    private function getInterfaceSubscribers(SubscriberInterface $interface)
    {
        $subscribers = array();
        foreach ($interface->getSubscriptions() as $eventName => $mixed) {
            if (\is_string($mixed)) {
                // methodName
                $subscribers[] = array($eventName, array($interface, $mixed), 0);
            } elseif (\count($mixed) == 2 && \is_int($mixed[1])) {
                // array('methodName', priority)
                $subscribers[] = array($eventName, array($interface, $mixed[0]), $mixed[1]);
            } else {
                foreach ($mixed as $mixed2) {
                    // methodName
                    // or array(methodName[, priority])
                    if (\is_string($mixed2)) {
                        $callable = array($interface, $mixed2);
                        $priority = 0;
                    } else {
                        $callable = array($interface, $mixed2[0]);
                        $priority = isset($mixed2[1]) ? $mixed2[1] : 0;
                    }
                    $subscribers[] = array($eventName, $callable, $priority);
                }
            }
        }
        return $subscribers;
    }

    /**
     * Sorts the internal list of subscribers for the given event by priority.
     *
     * @param string $eventName The name of the event
     *
     * @return void
     */
    private function sortSubscribers($eventName)
    {
        \krsort($this->subscribers[$eventName]);
        $this->sorted[$eventName] = array();
        foreach ($this->subscribers[$eventName] as $priority => $subscribers) {
            foreach ($subscribers as $k => $subscriber) {
                if ($this->isClosureFactory($subscriber)) {
                    $subscriber = $this->doClosureFactory($subscriber);
                    $this->subscribers[$eventName][$priority][$k] = $subscriber;
                }
                $this->sorted[$eventName][] = $subscriber;
            }
        }
    }
}

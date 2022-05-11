<?php

/**
 * Manage event subscriptions
 *
 * @package   bdk\PubSub
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v2.4
 * @link      http://www.github.com/bkdotcom/PubSub
 */

namespace bdk\PubSub;

use bdk\PubSub\SubscriberInterface;

/**
 * Event publish/subscribe event manager
 */
class Manager
{
    const EVENT_PHP_SHUTDOWN = 'php.shutdown';
    const DEFAULT_PRIORITY = 0;

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
     * @return array a normalized list of subscriptions added.
     */
    public function addSubscriberInterface(SubscriberInterface $interface)
    {
        $subscribersByEvent = $this->getInterfaceSubscribers($interface);
        foreach ($subscribersByEvent as $eventName => $subscribers) {
            foreach ($subscribers as $methodPriority) {
                $callable = array($interface, $methodPriority[0]);
                $this->subscribe($eventName, $callable, $methodPriority[1]);
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
            if (!isset($this->sorted[$eventName])) {
                $this->prepSubscribers($eventName);
            }
            return $this->sorted[$eventName];
        }
        // return all subscribers
        foreach (\array_keys($this->subscribers) as $eventName) {
            if (!isset($this->sorted[$eventName])) {
                $this->prepSubscribers($eventName);
            }
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
        foreach ($subscribersByEvent as $eventName => $subscribers) {
            foreach ($subscribers as $methodPriority) {
                $callable = array($interface, $methodPriority[0]);
                $this->unsubscribe($eventName, $callable);
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
     *
     * @return void
     */
    public function subscribe($eventName, $callable, $priority = 0)
    {
        unset($this->sorted[$eventName]); // clear the sorted cache
        $this->assertCallable($callable);
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
        $this->prepSubscribers($eventName);
        foreach ($this->subscribers[$eventName] as $priority => $subscribers) {
            foreach ($subscribers as $k => $subscriber) {
                if ($subscriber === $callable) {
                    unset($this->subscribers[$eventName][$priority][$k], $this->sorted[$eventName]);
                }
            }
            if (empty($this->subscribers[$eventName][$priority])) {
                unset($this->subscribers[$eventName][$priority]);
            }
        }
    }

    /**
     * Test if value is a callable or "closure factory"
     *
     * @param mixed $val Value to test
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function assertCallable($val)
    {
        if (\is_callable($val, true)) {
            return;
        }
        if ($this->isClosureFactory($val)) {
            return;
        }
        throw new \InvalidArgumentException(\sprintf(
            'Expected callable or "closure factory", but %s provided',
            \is_object($val) ? \get_class($val) : \gettype($val)
        ));
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
        $closureFactory[0] = $closureFactory[0]($this);
        return \count($closureFactory) === 1
            ? $closureFactory[0]    // invokeable object
            : $closureFactory;      // [obj, 'method']
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
     * @return bool
     *
     * @psalm-assert-if-true array $val
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
            $subscribers[$eventName] = $this->normalizeInterfaceSubscribers($mixed);
        }
        return $subscribers;
    }

    /**
     * Normalize event subscribers
     *
     * @param string|array $mixed method(s) with priority
     *
     * @return array list of array(methodName, priority)
     */
    private function normalizeInterfaceSubscribers($mixed)
    {
        if (\is_string($mixed)) {
            // methodName
            return array(
                array($mixed, self::DEFAULT_PRIORITY),
            );
        }
        if (\count($mixed) === 2 && \is_int($mixed[1])) {
            // ['methodName', priority]
            return array(
                $mixed,
            );
        }
        // array of methods
        $eventSubscribers = array();
        foreach ($mixed as $mixed2) {
            if (\is_string($mixed2)) {
                // methodName
                $eventSubscribers[] = array($mixed2, self::DEFAULT_PRIORITY);
                continue;
            }
            // array(methodName[, priority])
            $priority = isset($mixed2[1])
                ? $mixed2[1]
                : self::DEFAULT_PRIORITY;
            $eventSubscribers[] = array($mixed2[0], $priority);
        }
        return $eventSubscribers;
    }

    /**
     * Sorts the internal list of subscribers for the given event by priority.
     * Any closure factories for eventName are invoked
     *
     * @param string $eventName The name of the event
     *
     * @return void
     */
    private function prepSubscribers($eventName)
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

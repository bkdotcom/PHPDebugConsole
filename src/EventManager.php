<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk\Debug;

/**
 * Event publish/subscribe event manager
 */
class EventManager
{

    private $subscribers = array();
    private $sorted = array();

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
        foreach (array_keys($this->subscribers) as $eventName) {
            if (!isset($this->sorted[$eventName])) {
                $this->sortSubscribers($eventName);
            }
        }
        return array_filter($this->sorted);
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
        return (bool) $this->getSubscribers($eventName);
    }

    /**
     * Publish/Trigger/Dispatch event
     *
     * @param string $eventName      event name
     * @param mixed  $eventOrSubject to pass to subscriber
     * @param array  $values         values to attach to event
     *
     * @return mixed
     */
    public function publish($eventName, $eventOrSubject = null, array $values = array())
    {
        if ($eventOrSubject instanceof Event) {
            $event = $eventOrSubject;
        } else {
            $event = new Event($eventOrSubject, $values);
        }
        if ($subscribers = $this->getSubscribers($eventName)) {
            $this->doPublish($eventName, $subscribers, $event);
        }
        return $event;
    }

    /**
     * Subscribe / listen to event
     *
     * If callable is already subscribed to event it will first be removed.
     * This allows you to reassign priority
     *
     * @param string   $eventName event name
     * @param callable $callable  callable
     * @param integer  $priority  The higher this value, the earlier an event
     *
     * @return void
     */
    public function subscribe($eventName, $callable, $priority = 0)
    {
        $this->unsubscribe($eventName, $callable);  // remove callable if already subscribed
        unset($this->sorted[$eventName]);           // clear the sorted cache
        $this->subscribers[$eventName][$priority][] = $callable;
    }

    /**
     * Removes an event subscriber from the specified event.
     *
     * @param string   $eventName The event we're unsubscribing from
     * @param callable $callable  The subscriber to remove
     *
     * @return void
     */
    public function unsubscribe($eventName, $callable)
    {
        if (!isset($this->subscribers[$eventName])) {
            return;
        }
        foreach ($this->subscribers[$eventName] as $priority => $subscribers) {
            $index = array_search($callable, $subscribers, true);
            if ($index !== false) {
                unset($this->subscribers[$eventName][$priority][$index]);
                // and clear cached sorted subscribers
                unset($this->sorted[$eventName]);
                // now some trash collection
                if (empty($this->subscribers[$eventName][$priority])) {
                    unset($this->subscribers[$eventName][$priority]);
                }
                if (empty($this->subscribers[$eventName])) {
                    unset($this->subscribers[$eventName]);
                }
                break;
            }
        }
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
            call_user_func($callable, $event, $eventName, $this);
        }
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
        krsort($this->subscribers[$eventName]);
        $this->sorted[$eventName] = call_user_func_array('array_merge', $this->subscribers[$eventName]);
    }
}

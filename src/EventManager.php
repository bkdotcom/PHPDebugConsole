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
 * Event publish/subscribe framework
 */
class EventManager
{

    private $listeners = array();
    private $sorted = array();

    /**
     * Subscribe / listen to event
     *
     * If callable is already listening to event it will first be removed.
     * This allows you to reassign priority
     *
     * @param string   $eventName event name
     * @param callable $callable  callable
     * @param integer  $priority  The higher this value, the earlier an event
     *
     * @return void
     */
    public function addListener($eventName, $callable, $priority = 0)
    {
        $this->removeListener($eventName, $callable);
        $this->listeners[$eventName][$priority][] = $callable;
        unset($this->sorted[$eventName]);
    }

    /**
     * Dispatch/publish event
     *
     * @param string $eventName      event name
     * @param mixed  $eventOrSubject to pass to listener
     * @param array  $values         values to attach to event
     *
     * @return mixed
     */
    public function dispatch($eventName, $eventOrSubject = null, array $values = array())
    {
        if ($eventOrSubject === null) {
            $event = new Event();
        } elseif (!($eventOrSubject instanceof Event)) {
            $event = new Event($eventOrSubject, $values);
        } else {
            $event = $eventOrSubject;
        }
        if ($listeners = $this->getListeners($eventName)) {
            $this->doDispatch($eventName, $listeners, $event);
        }
        return $event;
    }

    /**
     * Gets the listeners of a specific event or all listeners sorted by descending priority.
     *
     * If event name is not specified, listeners for all events will be returned
     *
     * @param string $eventName The name of the event
     *
     * @return array The event listeners for the specified event, or all event listeners by event name
     */
    public function getListeners($eventName = null)
    {
        if ($eventName !== null) {
            if (!isset($this->listeners[$eventName])) {
                return array();
            }
            if (!isset($this->sorted[$eventName])) {
                $this->sortListeners($eventName);
            }
            return $this->sorted[$eventName];
        }
        // return all listeners
        foreach (array_keys($this->listeners) as $eventName) {
            if (!isset($this->sorted[$eventName])) {
                $this->sortListeners($eventName);
            }
        }
        return array_filter($this->sorted);
    }

    /**
     * Checks whether an event has any registered listeners.
     *
     * @param string $eventName The name of the event
     *
     * @return boolean
     */
    public function hasListeners($eventName = null)
    {
        return (bool) $this->getListeners($eventName);
    }

    /**
     * Removes an event listener from the specified event.
     *
     * @param string   $eventName The event to remove a listener from
     * @param callable $callable  The listener to remove
     *
     * @return void
     */
    public function removeListener($eventName, $callable)
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }
        foreach ($this->listeners[$eventName] as $priority => $listeners) {
            $index = array_search($callable, $listeners, true);
            if ($index !== false) {
                unset($this->listeners[$eventName][$priority][$index]);
                // and clear cached sorted listeners
                unset($this->sorted[$eventName]);
                // now some trash collection
                if (empty($this->listeners[$eventName][$priority])) {
                    unset($this->listeners[$eventName][$priority]);
                }
                if (empty($this->listeners[$eventName])) {
                    unset($this->listeners[$eventName]);
                }
                break;
            }
        }
    }

    /**
     * Triggers the listeners of an event.
     *
     * @param string     $eventName The name of the event to dispatch
     * @param callable[] $listeners The event listeners
     * @param Event      $event     The event object to pass to the event handlers/listeners
     *
     * @return void
     */
    protected function doDispatch($eventName, $listeners, Event $event)
    {
        foreach ($listeners as $callable) {
            if ($event->isPropagationStopped()) {
                break;
            }
            call_user_func($callable, $event, $eventName, $this);
        }
    }

    /**
     * Sorts the internal list of listeners for the given event by priority.
     *
     * @param string $eventName The name of the event
     *
     * @return void
     */
    private function sortListeners($eventName)
    {
        krsort($this->listeners[$eventName]);
        $this->sorted[$eventName] = call_user_func_array('array_merge', $this->listeners[$eventName]);
    }
}

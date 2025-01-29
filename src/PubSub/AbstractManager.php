<?php

/**
 * Manage event subscriptions
 *
 * @package   bdk\PubSub
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.1
 * @link      http://www.github.com/bkdotcom/PubSub
 */

namespace bdk\PubSub;

use Closure;
use InvalidArgumentException;

/**
 * Support methods for manager
 *
 * @psalm-type EventName = string
 * @psalm-type ClosureFactory = array{0: \Closure, 1?: string}
 * @psalm-type SubscriberInfo = array{callable: callable, onlyOnce: bool, priority: int}
 * @psalm-type SubscriberInfoRaw = array{callable: callable|ClosureFactory, onlyOnce: bool, priority: int}
 */
abstract class AbstractManager
{
    const DEFAULT_PRIORITY = 0;

    /** @var array<EventName,array<int,array<int,SubscriberInfoRaw>>> */
    protected $subscribers = array();

    /** @var array<EventName,list<SubscriberInfo>> */
    protected $sorted = array();

    /** @var list<array{
     *   eventName: EventName,
     *   subscribers: array<int, SubscriberInfo>
     * }> */
    protected $subscriberStack = array();

    /**
     * As a convenience, we'll attach subscriber's return value to event['return']
     *
     *     * Event must already have 'return' value defined and must be `null` or ""
     *
     * @param mixed $return return value
     * @param Event $event  Event instance
     *
     * @return void
     */
    protected function attachReturnToEvent($return, Event $event)
    {
        if ($return === null) {
            return;
        }
        if (\in_array($event['return'], [null, ''], true) === false) {
            // event already has non-null return value
            return;
        }
        if (\array_key_exists('return', $event->getValues()) === false) {
            // return value not defined / not expected to be set
            return;
        }
        /** @var mixed */
        $event['return'] = $return;
    }

    /**
     * Instantiate the object wrapped in the closure factory
     * closure factory may be
     *    [Closure, 'methodName'] - closure returns object
     *    [Closure] - closure returns object that is callable (ie has __invoke)
     *
     * @param array $closureFactory "closure factory" lazy loads an object / subscriber
     *
     * @psalm-param ClosureFactory $closureFactory
     *
     * @return callable
     *
     * @throws InvalidArgumentException
     */
    protected function doClosureFactory(array $closureFactory)
    {
        $object = $closureFactory[0]($this);
        \assert(\is_object($object), '"Closure factory" did not return an object');
        $closureFactory[0] = $object;
        $callable = \count($closureFactory) === 1
            ? $closureFactory[0]    // invokable object
            : $closureFactory;      // [obj, 'method']
        if (\is_callable($callable) === false) {
            throw new InvalidArgumentException('"Closure factory" did not produce a callable');
        }
        return $callable;
    }

    /**
     * Gets the type name of a variable in a way that is suitable for debugging
     *
     * @param mixed $value Value to inspect
     *
     * @return string
     */
    protected static function getDebugType($value)
    {
        return \is_object($value)
            ? \get_class($value)
            : \strtolower(\gettype($value));
    }

    /**
     * Test if value is a callable or "closure factory"
     *
     * @param mixed $val Value to test
     *
     * @return bool
     *
     * @psalm-assert-if-true callable|ClosureFactory $val
     */
    protected static function isCallableOrFactory($val)
    {
        return \is_callable($val, true) || self::isClosureFactory($val);
    }

    /**
     * Does val appear to be a "closure factory"?
     * array & array[0] instanceof Closure
     *
     * @param mixed $val value to check
     *
     * @return bool
     *
     * @psalm-assert-if-true ClosureFactory $val
     */
    protected static function isClosureFactory($val)
    {
        return \is_array($val) && isset($val[0]) && $val[0] instanceof Closure;
    }

    /**
     * Sorts the internal list of subscribers for the given event by priority (high to low).
     * Any closure factories for eventName are invoked
     *
     * @param string $eventName The name of the event
     *
     * @return void
     */
    protected function prepSubscribers($eventName)
    {
        if (isset($this->subscribers[$eventName]) === false) {
            $this->subscribers[$eventName] = array();
        }
        \krsort($this->subscribers[$eventName]);
        $this->sorted[$eventName] = array();
        $priorities = \array_keys($this->subscribers[$eventName]);
        \array_map(function ($priority) use ($eventName) {
            $eventSubscribers = $this->subscribers[$eventName][$priority];
            foreach ($eventSubscribers as $k => $subscriberInfo) {
                if ($this->isClosureFactory($subscriberInfo['callable'])) {
                    $subscriberInfo['callable'] = $this->doClosureFactory($subscriberInfo['callable']);
                    $this->subscribers[$eventName][$priority][$k] = $subscriberInfo;
                }
                $this->sorted[$eventName][] = $subscriberInfo;
            }
        }, $priorities);
    }

    /**
     * Prep and sort subscribers for the specified event name
     *
     * @param string $eventName The name of the event
     *
     * @return void
     */
    protected function setSorted($eventName)
    {
        if (isset($this->sorted[$eventName]) === false) {
            $this->prepSubscribers($eventName);
        }
    }

    /**
     * Add subscriber to subscriber stack
     *
     * @param int   $stackIndex     subscriberStack index to add to
     * @param array $subscriberInfo subscriber info (callable, priority, onlyOnce)
     *
     * @psalm-param SubscriberInfoRaw $subscriberInfo
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function subscribeActive($stackIndex, array $subscriberInfo)
    {
        if (isset($this->subscriberStack[$stackIndex]) === false) {
            throw new InvalidArgumentException('unknown index');
        }
        if ($this->isClosureFactory($subscriberInfo['callable'])) {
            $subscriberInfo['callable'] = $this->doClosureFactory($subscriberInfo['callable']);
        }
        $priority = $subscriberInfo['priority'];
        foreach ($this->subscriberStack[$stackIndex]['subscribers'] as $i => $subscriberInfoCur) {
            if ($priority > $subscriberInfoCur['priority']) {
                \array_splice($this->subscriberStack[$stackIndex]['subscribers'], $i, 0, [$subscriberInfo]);
                return;
            }
        }
        $this->subscriberStack[$stackIndex]['subscribers'][] = $subscriberInfo;
    }

    /**
     * Remove callable from active event subscribers
     *
     * @param int      $stackIndex subscriberStack index to add to
     * @param callable $callable   callable
     *
     * @return void
     */
    protected function unsubscribeActive($stackIndex, $callable)
    {
        foreach ($this->subscriberStack[$stackIndex]['subscribers'] as $i => $subscriberInfo) {
            if ($subscriberInfo['callable'] === $callable) {
                \array_splice($this->subscriberStack[$stackIndex]['subscribers'], $i, 1);
            }
        }
    }

    /**
     * Find callable in eventName/priority array and remove it
     *
     * @param string                  $eventName The event we're unsubscribing from
     * @param callable|ClosureFactory $callable  callable
     * @param int                     $priority  The priority
     * @param bool                    $onlyOnce  Only unsubscribe "onlyOnce" subscribers
     *
     * @return void
     */
    protected function unsubscribeFromPriority($eventName, $callable, $priority, $onlyOnce)
    {
        $search = \array_filter(array(
            'callable' => $callable,
            'onlyOnce' => $onlyOnce,
        ));
        foreach ($this->subscribers[$eventName][$priority] as $k => $subscriberInfo) {
            if (\array_intersect_key($subscriberInfo, $search) !== $search) {
                continue;
            }
            unset($this->subscribers[$eventName][$priority][$k], $this->sorted[$eventName]);
            if ($onlyOnce) {
                break;
            }
        }
        if (empty($this->subscribers[$eventName][$priority])) {
            unset($this->subscribers[$eventName][$priority]);
        }
    }
}

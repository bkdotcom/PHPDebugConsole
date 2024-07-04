<?php

/**
 * Manage event subscriptions
 *
 * @package   bdk\PubSub
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.1
 * @link      http://www.github.com/bkdotcom/PubSub
 */

namespace bdk\PubSub;

use bdk\PubSub\Manager;
use bdk\PubSub\SubscriberInterface;
use Closure;
use RuntimeException;

/**
 * Get subscribers from SubscriberInterface
 *
 * @psalm-import-type ClosureFactory from \bdk\PubSub\AbstractManager
 * @psalm-import-type SubscriberInfo from \bdk\PubSub\AbstractManager
 * @psalm-import-type SubscriberInfoRaw from \bdk\PubSub\AbstractManager
 */
class InterfaceManager
{
    /**
     * Calls the passed object's getSubscriptions() method and returns a normalized list of subscriptions
     *
     * @param SubscriberInterface $interface SubscriberInterface instance
     *
     * @return array<string,list<SubscriberInfo>>
     *
     * @throws RuntimeException
     */
    public static function getSubscribers(SubscriberInterface $interface)
    {
        $subscriptions = $interface->getSubscriptions();
        if (\is_array($subscriptions) === false) {
            throw new RuntimeException(\sprintf(
                'Expected array from %s::getSubscriptions().  Got %s',
                \get_class($interface),
                self::getDebugType($subscriptions)
            ));
        }
        // array<string, array<array-key, mixed>|string>
        foreach ($subscriptions as $eventName => $mixed) {
            $eventSubscribers = self::normalizeEventSubscribers($interface, $mixed);
            if ($eventSubscribers === false) {
                throw new RuntimeException(\sprintf(
                    '%s::getSubscriptions():  Unexpected subscriber(s) defined for %s',
                    \get_class($interface),
                    $eventName
                ));
            }
            $subscriptions[$eventName] = $eventSubscribers;
        }
        /** @var array<string,list<SubscriberInfo>> */
        return $subscriptions;
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
            : \gettype($value);
    }

    /**
     * Normalize event subscribers
     *
     * @param SubscriberInterface $interface SubscriberInterface instance
     * @param string|array        $mixed     method(s) with optional priority/onlyOnce
     *
     * @return list<SubscriberInfoRaw>|false list of "subscriber info"
     */
    private static function normalizeEventSubscribers(SubscriberInterface $interface, $mixed)
    {
        // test if single subscriber
        //   ie, 'eventName' => 'method',
        //      or 'eventName' => array('method'), etc
        $subscriberInfo = self::normalizeSubscriber($interface, $mixed);
        if ($subscriberInfo) {
            return array($subscriberInfo);
        }
        if (\is_array($mixed) === false) {
            return false;
        }
        // multiple subscribers
        $eventSubscribers = array();
        /** @var mixed $mixed2 */
        foreach ($mixed as $mixed2) {
            $subscriberInfo = self::normalizeSubscriber($interface, $mixed2);
            if ($subscriberInfo) {
                $eventSubscribers[] = $subscriberInfo;
                continue;
            }
            return false;
        }
        return $eventSubscribers;
    }

    /**
     * Test if value defines method/priority/onlyOnce
     *
     * @param SubscriberInterface $interface SubscriberInterface instance
     * @param mixed               $mixed     method/priority/onlyOnce info
     *
     * @return SubscriberInfoRaw|false
     */
    private static function normalizeSubscriber(SubscriberInterface $interface, $mixed)
    {
        /** @var SubscriberInfoRaw */
        $subscriberInfo = array(
            'callable' => null,
            'onlyOnce' => false,
            'priority' => Manager::DEFAULT_PRIORITY,
        );
        if (\is_string($mixed)) {
            $subscriberInfo['callable'] = array($interface, $mixed);
            return $subscriberInfo;
        }
        if ($mixed instanceof Closure) {
            $subscriberInfo['callable'] = $mixed;
            return $subscriberInfo;
        }
        return \is_array($mixed)
        	? self::normalizeSubscriberArray($interface, $mixed)
        	: false;
    }

    /**
     * Test if given array defines method/priority/onlyOnce
     *
     * @param SubscriberInterface $interface SubscriberInterface instance
     * @param array               $values    subscriber values
     *
     * @return SubscriberInfoRaw|false updated subscriberInfo
     */
    private static function normalizeSubscriberArray(SubscriberInterface $interface, array $values)
    {
        $subscriberInfo = self::buildSubscriberInfoFromValues($values);
        /** @var mixed */
        $callable = $subscriberInfo['callable'];
        if (\is_string($callable)) {
            $subscriberInfo['callable'] = array($interface, $callable);
        }
        return \is_callable($subscriberInfo['callable'], true)
        	? $subscriberInfo
        	: false;
    }

    /**
     * Find
     *
     * @param array $values array values
     *
     * @return array{callable:string|Closure|null,onlyOnce:bool,priority:int}
     */
    private static function buildSubscriberInfoFromValues($values)
    {
        $subscriberInfo = array(
            'callable' => null,
            'onlyOnce' => false,
            'priority' => Manager::DEFAULT_PRIORITY,
        );
        $tests = array(
            'callable' => static function ($val) {
                return \is_string($val) || ($val instanceof Closure);
            },
            'onlyOnce' => 'is_bool',
            'priority' => 'is_int',
        );
        while ($values && $tests) {
            /** @var mixed */
            $val = \array_shift($values);
            foreach ($tests as $key => $test) {
                if ($test($val)) {
                    /** @var string|Closure|bool|int */
                    $subscriberInfo[$key] = $val;
                    unset($tests[$key]);
                    continue 2;
                }
            }
            // all tests failed
            $subscriberInfo['callable'] = null;
            break;
        }
        /** @var array{callable:string|Closure|null,onlyOnce:bool,priority:int} */
        return $subscriberInfo;
    }
}

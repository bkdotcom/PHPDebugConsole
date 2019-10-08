<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\PubSub\Event;
use bdk\Debug\Collector\SimpleCache\CallInfo;
use Psr\SimpleCache\CacheInterface;
use Traversable;

/**
 * A SimpleCache (PSR-16) wrapper to log SimpleCache operations
 */
class SimpleCache implements CacheInterface
{
    public $debug;
    protected $cache;
    protected $icon = 'fa fa-cube';
    protected $loggedActions = array();

    /**
     * Constructor
     *
     * @param CacheInterface $cache SimpleCache instance
     * @param Debug          $debug (optional) Specify PHPDebugConsole instance
     *                                if not passed, will create PDO channnel on singleton instance
     *                                if root channel is specified, will create a PDO channel
     */
    public function __construct(CacheInterface $cache, Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('SimpleCache', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('SimpleCache', array('channelIcon' => $this->icon));
        }
        $this->cache = $cache;
        $this->debug = $debug;
        $this->debug->eventManager->subscribe('debug.output', array($this, 'onDebugOutput'), 1);
    }

    /**
     * Magic method... inaccessible method called.
     *
     * @param string $name method name
     * @param array  $args method arguments
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
        $keys = !empty($args[0])
            ? $args[0]
            : null;
        return $this->profileCall($name, $args, false, $keys);
    }


    /**
     * {@inheritDoc}
     */
    public function get($key, $default = null)
    {
        return $this->profileCall('get', \func_get_args(), false, $key);
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->profileCall('set', \func_get_args(), true, $key);
    }

    /**
     * {@inheritDoc}
     */
    public function delete($key)
    {
        return $this->profileCall('delete', \func_get_args(), true, $key);
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        return $this->profileCall('clear', array(), true);
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple($keys, $default = null)
    {
        $keysDebug = array();
        if ($keys instanceof Traversable) {
            $keysDebug = \iterator_to_array($keys, false);
        } elseif (\is_array($keys)) {
            $keysDebug = $keys;
        }
        return $this->profileCall('getMultiple', \func_get_args(), false, $keysDebug);
    }

    /**
     * {@inheritDoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        $keysDebug = array();
        if ($keys instanceof Traversable) {
            $keysDebug = \array_keys(\iterator_to_array($values));
        } elseif (\is_array($keys)) {
            $keysDebug = \array_keys($values);
        }
        return $this->profileCall('setMultiple', \func_get_args(), true, $keysDebug);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMultiple($keys)
    {
        $keysDebug = array();
        if ($keys instanceof Traversable) {
            $keysDebug = \iterator_to_array($keys, false);
        } elseif (\is_array($keys)) {
            $keysDebug = $keys;
        }
        return $this->profileCall('deleteMultiple', \func_get_args(), true, $keysDebug);
    }

    /**
     * {@inheritDoc}
     */
    public function has($key)
    {
        return $this->profileCall('has', \func_get_args(), false, $key);
    }

    /**
     * debug.output subscriber
     *
     * @param Event $event event instance
     *
     * @return void
     */
    public function onDebugOutput(Event $event)
    {
        $debug = $event->getSubject();
        $debug->groupSummary(0);
        $debug->groupCollapsed(
            'SimpleCache info',
            $debug->meta(array(
                'level' => 'info',
                'icon' => $this->icon,
            ))
        );
        $debug->log('logged operations: ', \count($this->loggedActions));
        $debug->log('total time: ', $this->getTimeSpent());
        $debug->log('max memory usage', $debug->utilities->getBytes($this->getPeakMemoryUsage()));
        $debug->groupEnd();
        $debug->groupEnd();
    }

    /**
     * Profiles a call to a PDO method
     *
     * @param string  $method            SimpleCache method
     * @param array   $args              method args
     * @param boolean $isSuccessResponse does the method return boolean success?
     * @param boolean $keyOrKeys         key(s) being queried/set
     *
     * @return mixed The result of the call
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function profileCall($method, array $args = array(), $isSuccessResponse = false, $keyOrKeys = null)
    {
        $info = new CallInfo($method, $keyOrKeys);

        $exception = null;
        try {
            $result = \call_user_func_array(array($this->cache, $method), $args);
            if ($isSuccessResponse && $result === false) {
                $exception = new \Exception();
            }
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            $exception = $e;
        }

        $info->end($exception);
        $this->addCallInfo($info);

        if ($exception) {
            throw $exception;
        }
        return $result;
    }

    /**
     * Logs CallInfo
     *
     * @param CallInfo $info statement info instance
     *
     * @return void
     */
    public function addCallInfo(CallInfo $info)
    {
        $this->loggedActions[] = $info;
        $this->debug->log(
            $info->method . '(' . \json_encode($info->keyOrKeys) . ') took ' . \number_format($info->duration, 5) . 'sec',
            $this->debug->meta('icon', $this->icon)
        );
    }

    /**
     * Returns the accumulated execution time of statements
     *
     * @return integer
     */
    public function getTimeSpent()
    {
        $time = \array_reduce($this->loggedActions, function ($val, CallInfo $info) {
            return $val + $info->duration;
        });
        return \round($time, 6);
    }

    /**
     * Returns the peak memory usage while performing statements
     *
     * @return integer
     */
    public function getPeakMemoryUsage()
    {
        return \array_reduce($this->loggedActions, function ($carry, CallInfo $info) {
            $mem = $info->memoryUsage;
            return $mem > $carry
                ? $mem
                : $carry;
        });
    }

    /**
     * Returns the list of executed statements as CallInfo objects
     *
     * @return CallInfo[]
     */
    public function getLoggedActions()
    {
        return $this->loggedActions;
    }
}

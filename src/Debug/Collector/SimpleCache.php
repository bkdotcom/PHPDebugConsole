<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Collector;

use BadMethodCallException;
use bdk\Debug;
use bdk\Debug\Collector\SimpleCache\CallInfo;
use bdk\Debug\Collector\SimpleCache\CompatTrait;
use bdk\PubSub\Event;
use Exception;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Traversable;

/**
 * A SimpleCache (PSR-16) decorator that logs SimpleCache operations
 */
class SimpleCache implements CacheInterface
{
    use CompatTrait;

    /** @var Debug */
    public $debug;

    /** @var CacheInterface */
    protected $cache;

    /** @var string */
    protected $icon = ':cache:';

    /** @var list<CallInfo> */
    protected $loggedActions = array();

    /**
     * Constructor
     *
     * @param CacheInterface $cache SimpleCache instance
     * @param Debug|null     $debug (optional) Specify PHPDebugConsole instance
     *                                if not passed, will create PDO channel on singleton instance
     *                                if root channel is specified, will create a PDO channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(CacheInterface $cache, $debug = null)
    {
        \bdk\Debug\Utility::assertType($debug, 'bdk\Debug');

        if (!$debug) {
            $debug = Debug::getChannel('SimpleCache', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('SimpleCache', array('channelIcon' => $this->icon));
        }
        $this->cache = $cache;
        $this->debug = $debug;
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, [$this, 'onDebugOutput'], 1);
    }

    /**
     * Magic method... inaccessible method called.
     *
     * @param string $method method name
     * @param array  $args   method arguments
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call($method, array $args)
    {
        if (\method_exists($this->cache, $method) === false) {
            throw new BadMethodCallException('method ' . __CLASS__ . '::' . $method . ' is not defined');
        }
        // we'll just pass the first arg since we don't know what we're dealing with
        $keys = !empty($args[0])
            ? $args[0]
            : null;
        return $this->profileCall($method, $args, false, $keys);
    }

    /*
    Defined in CompatTrait:
        get, set, delete, clear, getMultiple, setMultiple, deleteMultiple, has
    */

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * @param Event $event Event instance
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
                'icon' => $this->icon,
                'level' => 'info',
            ))
        );
        $debug->log('logged operations: ', \count($this->loggedActions));
        $debug->log('total time: ', $this->getTimeSpent());
        $debug->log('max memory usage', $debug->utility->getBytes($this->getPeakMemoryUsage()));
        $debug->groupEnd();
        $debug->groupEnd();
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
        $duration = $this->debug->utility->formatDuration($info->duration);
        $keyOrKeys = $info->keyOrKeys === null
            ? ''
            : \json_encode($info->keyOrKeys);
        $message = \sprintf('%s(%s) took %s', $info->method, $keyOrKeys, $duration);
        if ($info->isSuccess === false) {
            $message .= ' (return false)';
        }
        $this->debug->log(
            $message,
            $this->debug->meta('icon', $this->icon)
        );
    }

    /**
     * Returns the accumulated execution time of statements
     *
     * @return float
     */
    public function getTimeSpent()
    {
        $time = \array_reduce($this->loggedActions, static function ($val, CallInfo $info) {
            return $val + $info->duration;
        });
        return \round($time, 6);
    }

    /**
     * Returns the peak memory usage while performing statements
     *
     * @return int
     */
    public function getPeakMemoryUsage()
    {
        return \array_reduce($this->loggedActions, static function ($carry, CallInfo $info) {
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

    /**
     * Get the keys being get/set/deleted
     *
     * @param iterable $keysOrValues keys or key=>value pairs
     * @param bool     $isValues     key/values ?
     *
     * @return array
     */
    protected function keysDebug($keysOrValues, $isValues = false)
    {
        $keysDebug = array();
        if ($keysOrValues instanceof Traversable) {
            $keysDebug = \iterator_to_array($keysOrValues, $isValues);
        } elseif (\is_array($keysOrValues)) {
            $keysDebug = $keysOrValues;
        }
        if ($isValues) {
            $keysDebug = \array_keys($keysDebug);
        }
        return $keysDebug;
    }

    /**
     * Profiles a call to a PDO method
     *
     * @param string       $method            SimpleCache method
     * @param array        $args              method args
     * @param bool         $isSuccessResponse does the method return boolean success?
     * @param string|array $keyOrKeys         key(s) being queried/set
     *
     * @return mixed The result of the call
     * @throws RuntimeException
     */
    protected function profileCall($method, array $args = array(), $isSuccessResponse = false, $keyOrKeys = null)
    {
        $info = new CallInfo($method, $keyOrKeys);

        $exception = null; // unexpected exception / will be re-thrown
        $failureException = null; // method returned false
        $result = null;
        try {
            $result = \call_user_func_array([$this->cache, $method], $args);
            if ($isSuccessResponse && $result === false) {
                $failureException = new RuntimeException(__CLASS__ . '::' . $method . '() failed');
            }
        } catch (Exception $e) {
            $exception = $e;
        }

        $info->end($exception ?: $failureException);
        $this->addCallInfo($info);

        if ($exception) {
            throw $exception;
        }
        return $result;
    }
}

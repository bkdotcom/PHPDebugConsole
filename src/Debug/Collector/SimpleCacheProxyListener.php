<?php

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\SimpleCache\CallInfo;
use bdk\Proxy\ListenerInterface;
use bdk\PubSub\Event;
use Exception;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Traversable;

/**
 * Listener for SimpleCache proxy
 */
class SimpleCacheProxyListener implements ListenerInterface
{
    /** @var array arguments passed to method */
    private $arguments = [];

    /** @var Debug */
    private $debug;

    /** @var Exception|null */
    private $exception = null;

    /** @var string */
    private $icon = ':cache:';

    /** @var array */
    private $initValues = array();

    /** @var list<CallInfo> */
    protected $loggedActions = array();

    /** @var CacheInterface */
    private $proxy;

    /** @var mixed method return value */
    private $result = null;

    /** @var CacheInterface */
    private $subject;

    /**
     * Constructor
     *
     * @param Debug|null $debug (optional) $debug instance
     */
    public function __construct($debug = null)
    {
        $channelKey = 'SimpleCache';
        $channelOptions = array(
            'channelIcon' => $this->icon,
            'channelname' => 'SimpleCache',
        );

        if (!$debug) {
            $debug = Debug::getChannel($channelKey, $channelOptions);
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel($channelKey, $channelOptions);
        }

        $this->debug = $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function afterCall($methodName, array $arguments, $result, array $initValues, $exception = null)
    {
        $this->arguments = $arguments;
        $this->exception = $exception;
        $this->initValues = $initValues;
        $this->result = $result;
        $listenerMethod = 'afterCall' . \str_replace(' ', '', \ucwords(\str_replace('_', ' ', $methodName)));
        \method_exists($this, $listenerMethod)
            ? $this->$listenerMethod()
            : $this->logCall($methodName, null);
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function init($subject, $proxy)
    {
        $this->subject = $subject;
        $this->proxy = $proxy;
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, [$this, 'onDebugOutput'], 1);
    }

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
        $debug->log($debug->i18n->trans('runtime.logged-operations') . ': ', \count($this->loggedActions));
        $debug->log($debug->i18n->trans('runtime.total-time'), $this->getTimeSpent());
        $peakMemoryUsage = $this->getPeakMemoryUsage();
        if ($peakMemoryUsage !== null) {
            $debug->log($debug->i18n->trans('runtime.memory.peak'), $debug->utility->getBytes($peakMemoryUsage));
        }
        $debug->groupEnd();
        $debug->groupEnd();
    }

    /**
     * Log call to `get`
     *
     * @return void
     */
    protected function afterCallGet()
    {
        $this->logCall('get', $this->arguments[0], false);
    }

    /**
     * Log call to `set`
     *
     * @return void
     */
    protected function afterCallSet()
    {
        $this->logCall('set', $this->arguments[0], true);
    }

    /**
     * Log call to `delete`
     *
     * @return void
     */
    protected function afterCallDelete()
    {
        $this->logCall('delete', $this->arguments[0], true);
    }

    /**
     * Log call to `clear`
     *
     * @return void
     */
    protected function afterCallClear()
    {
        $this->logCall('clear', null, true);
    }

    /**
     * Log call to `getMultiple`
     *
     * @return void
     */
    protected function afterCallGetMultiple()
    {
        $keysDebug = $this->keysDebug($this->arguments[0]);
        $this->logCall('getMultiple', $keysDebug, false);
    }

    /**
     * Log call to `setMultiple`
     *
     * @return void
     */
    protected function afterCallSetMultiple()
    {
        $keysDebug = $this->keysDebug($this->arguments[0], true);
        $this->logCall('setMultiple', $keysDebug, true);
    }

    /**
     * Log call to `deleteMultiple`
     *
     * @return void
     */
    protected function afterCallDeleteMultiple()
    {
        $keysDebug = $this->keysDebug($this->arguments[0]);
        $this->logCall('deleteMultiple', $keysDebug, true);
    }

    /**
     * Log call to `has`
     *
     * @return void
     */
    protected function afterCallHas()
    {
        $this->logCall('has', $this->arguments[0], false);
    }

    /**
     * Logs CallInfo
     *
     * @param CallInfo $info statement info instance
     *
     * @return void
     */
    private function addCallInfo(CallInfo $info)
    {
        $this->loggedActions[] = $info;
        $duration = $this->debug->utility->formatDuration($info->duration);
        $keyOrKeys = $info->keyOrKeys === null
            ? ''
            : \json_encode($info->keyOrKeys);
        $debugMethod = 'log';
        $errMessage = null;
        if ($info->exception) {
            $debugMethod = 'error';
            $errMessage = '(' . \get_class($info->exception) . ': ' . $info->exception->getMessage() . ')';
        } elseif ($info->success === false) {
            $errMessage = '(return false)';
        }
        $args = [
            \sprintf('%s(%s) took %s', $info->method, $keyOrKeys, $duration),
            $errMessage,
            $this->debug->meta(array(
                'glue' => ' ',
                'icon' => $this->icon,
            )),
        ];
        $args = \array_filter($args);
        \call_user_func_array([$this->debug, $debugMethod], $args);
    }

    /**
     * Returns the accumulated execution time of statements
     *
     * @return float
     */
    private function getTimeSpent()
    {
        $time = \array_reduce($this->loggedActions, static function ($carry, CallInfo $info) {
            return $carry + $info->duration;
        }, 0);
        return \round($time, 6);
    }

    /**
     * Returns the peak memory usage while performing statements
     *
     * @return int
     */
    private function getPeakMemoryUsage()
    {
        return \array_reduce($this->loggedActions, static function ($carry, CallInfo $info) {
            $mem = $info->memoryUsage;
            return $mem > $carry
                ? $mem
                : $carry;
        }, 0);
    }

    /**
     * Get the keys being get/set/deleted
     *
     * @param iterable $keysOrValues keys or key=>value pairs
     * @param bool     $isValues     key/values ?
     *
     * @return array
     */
    private function keysDebug($keysOrValues, $isValues = false)
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
     * @param string            $method          SimpleCache method
     * @param string|array|null $keyOrKeys       Key(s) being queried/set
     * @param bool              $isSuccessResult Does the method return boolean success?
     *
     * @return void
     * @throws RuntimeException
     */
    private function logCall($method, $keyOrKeys, $isSuccessResult = false)
    {
        $info = new CallInfo($method, $keyOrKeys, $this->initValues);
        $isSuccess = $isSuccessResult
            ? $this->result === true
            : null;
        $info->end($isSuccess, $this->exception);
        $this->addCallInfo($info);
    }
}

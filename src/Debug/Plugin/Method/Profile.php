<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\Debug\Utility\FileStreamWrapper;
use bdk\Debug\Utility\Profile as ProfileInstance;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Handle Debug's profile methods
 */
class Profile implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var int */
    protected $autoInc = 1;

    /** @var ProfileInstance[] */
    protected $instances = array();

    /** @var string[] */
    protected $methods = array(
        'profile',
        'profileEnd',
    );

    /** @var bool */
    private static $profilingEnabled = false;

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CONFIG => 'onConfig',
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
            Debug::EVENT_STREAM_WRAP => 'onStreamWrap',
        );
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        if (!$event['debug'] || $event['isTarget'] === false) {
            return;
        }
        $this->debug = $event->getSubject();
        $cfgDebug = $event['debug'];
        $valActions = \array_intersect_key(array(
            'enableProfiling' => [$this, 'onCfgEnableProfiling'],
        ), $cfgDebug);
        foreach ($valActions as $key => $callable) {
            /** @psalm-suppress TooManyArguments */
            $cfgDebug[$key] = $callable($cfgDebug[$key], $key, $event);
        }
        $event['debug'] = \array_merge($event['debug'], $cfgDebug);
    }

    /**
     * If profiling, inject `declare(ticks=1)`
     *
     * @param Event $event Debug::EVENT_STREAM_WRAP event object
     *
     * @return void
     */
    public function onStreamWrap(Event $event)
    {
        $declare = 'declare(ticks=1);';
        $event['content'] = \preg_replace(
            '/^(<\?php)\s*?$/m',
            '$0 ' . $declare,
            $event['content'],
            1
        );
    }

    /**
     * Starts recording a performance profile
     *
     * @param string $name Optional profile name
     *
     * @return Debug
     *
     * @since 2.3
     */
    public function profile($name = null) // @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        $debug = $this->debug;
        if (!$debug->getCfg('collect', Debug::CONFIG_DEBUG)) {
            return $debug;
        }
        if (!$debug->getCfg('enableProfiling', Debug::CONFIG_DEBUG)) {
            $callerInfo = $debug->backtrace->getCallerInfo();
            $msg = \sprintf(
                'Profile: Unable to start - enableProfiling opt not set.  %s on line %s.',
                $callerInfo['file'],
                $callerInfo['line']
            );
            return $debug->log(new LogEntry(
                $debug,
                __FUNCTION__,
                [$msg]
            ));
        }
        return $this->doProfile(new LogEntry(
            $debug,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__),
            ['name']
        ));
    }

    /**
     * Stops recording profile info & adds info to the log
     *
     *  * if name is passed and it matches the name of a profile being recorded, then that profile is stopped.
     *  * if name is passed and it does not match the name of a profile being recorded, nothing will be done
     *  * if name is not passed, the most recently started profile is stopped (named, or non-named).
     *
     * @param string $name Optional profile name
     *
     * @return Debug
     *
     * @since 2.3
     */
    public function profileEnd($name = null) // @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $this->debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__),
            ['name']
        );
        $this->doProfileEnd($logEntry);
        return $this->debug;
    }

    /**
     * Start profiling
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return Debug
     */
    private function doProfile(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        if ($logEntry['meta']['name'] === null) {
            $logEntry['meta']['name'] = 'Profile ' . $this->autoInc;
            $this->autoInc++;
        }
        $name = $logEntry['meta']['name'];
        if (isset($this->instances[$name])) {
            $instance = $this->instances[$name];
            $instance->end();
            $instance->start();
            // move it to end (last started)
            unset($this->instances[$name]);
            $this->instances[$name] = $instance;
            $logEntry['args'] = ['Profile \'' . $name . '\' restarted'];
            $debug->log($logEntry);
            return $debug;
        }
        $instance = new ProfileInstance();
        $instance->start();
        $this->instances[$name] = $instance;
        $logEntry['args'] = ['Profile \'' . $name . '\' started'];
        return $debug->log($logEntry);
    }

    /**
     * Handle profileEnd() call
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function doProfileEnd(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        if ($logEntry['meta']['name'] === null) {
            \end($this->instances);
            $logEntry['meta']['name'] = \key($this->instances);
        }
        $name = $logEntry['meta']['name'];
        $logEntry['args'] = isset($this->instances[$name])
            ? $this->doProfileEndArgs($logEntry)
            : ($name !== null
                ? 'profileEnd: No such Profile: ' . $name
                : 'profileEnd: Not currently profiling'
            );
        $debug->rootInstance->getPlugin('methodTable')->doTable($logEntry);
        $debug->log($logEntry);
        unset($this->instances[$name]);
    }

    /**
     * Build table data and info
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array
     */
    private function doProfileEndArgs(LogEntry $logEntry)
    {
        $name = $logEntry['meta']['name'];
        $caption = 'Profile \'' . $name . '\' Results';
        $instance = $this->instances[$name];
        $data = $instance->end();
        if (!$data) {
            return [$caption, 'no data'];
        }
        $tableInfo = \array_replace_recursive(array(
            'rows' => \array_fill_keys(\array_keys($data), array()),
        ), $logEntry->getMeta('tableInfo', array()));
        foreach (\array_keys($data) as $k) {
            $tableInfo['rows'][$k]['key'] = new Abstraction(Type::TYPE_IDENTIFIER, array(
                'typeMore' => Type::TYPE_IDENTIFIER_METHOD,
                'value' => $k,
            ));
        }
        $logEntry->setMeta(array(
            'caption' => $caption,
            'tableInfo' => $tableInfo,
            'totalCols' => ['ownTime'],
        ));
        return [$data];
    }

    /**
     * Test if we need to enable profiling
     *
     * @param bool   $val   config value
     * @param string $key   config param name
     * @param Event  $event The config change event
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgEnableProfiling($val, $key, Event $event) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        if (static::$profilingEnabled) {
            // profiling currently enabled
            if ($val === false) {
                FileStreamWrapper::unregister();
                static::$profilingEnabled = false;
            }
            return $val;
        }
        $cfgAll = \array_merge(
            $this->debug->getCfg(null, Debug::CONFIG_DEBUG),
            $event['debug']
        );
        if ($cfgAll['enableProfiling'] && $cfgAll['collect']) {
            static::$profilingEnabled = true;
            FileStreamWrapper::setEventManager($this->debug->eventManager);
            FileStreamWrapper::setPathsExclude(\array_merge(FileStreamWrapper::getPathsExclude(), [
                __DIR__ . '/../../',
            ]));
            FileStreamWrapper::register();
        }
        return $val;
    }
}

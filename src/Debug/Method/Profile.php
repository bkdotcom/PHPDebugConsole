<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Method;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;

/**
 * Handle Debug's profile methods
 *
 * Serves as both the handler for profile() / profileEnd()
 * and as individual profile instances
 */
class Profile
{
    /*
        Handler properties
    */

    protected $autoInc = 1;
    protected $instances = array();

    /*
        Profile instance properties
    */

    /** @var array profile data */
    protected $data = array();
    protected $funcStack = array();
    protected $isProfiling = false;
    protected $namespace = 'bdk\\Debug';
    /** @var string Ignore methods in these namespaces */
    protected $nsIgnoreRegex;
    protected $rootStack = array();
    protected $timeLastTick = null;
    protected $trace = array();

    /**
     * Constructor
     *
     * @param array $namespacesIgnore array of namespaces who's methods will be excluded from profile
     */
    public function __construct($namespacesIgnore = array())
    {
        $namespacesIgnore = \array_merge(array($this->namespace), (array) $namespacesIgnore);
        $namespacesIgnore = \array_unique($namespacesIgnore);
        $this->nsIgnoreRegex = \str_replace('\\', '\\\\', '#^(' . \implode('|', $namespacesIgnore) . ')(\\|$)#');
    }

    /**
     * Handle clear() call
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function doProfile(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        if (!$debug->getCfg('enableProfiling', Debug::CONFIG_DEBUG)) {
            $callerInfo = $debug->backtrace->getCallerInfo();
            $msg = \sprintf(
                'Profile: Unable to start - enableProfiling opt not set.  %s on line %s.',
                $callerInfo['file'],
                $callerInfo['line']
            );
            $debug->log(new LogEntry(
                $debug,
                __FUNCTION__,
                array($msg)
            ));
            return;
        }
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
            $logEntry['args'] = array('Profile \'' . $name . '\' restarted');
            $debug->log($logEntry);
            return;
        }
        $instance = new self();
        $instance->start();
        $this->instances[$name] = $instance;
        $logEntry['args'] = array('Profile \'' . $name . '\' started');
        $debug->log($logEntry);
    }

    /**
     * Handle profileEnd() call
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function profileEnd(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        if ($logEntry['meta']['name'] === null) {
            \end($this->instances);
            $logEntry['meta']['name'] = \key($this->instances);
        }
        $name = $logEntry['meta']['name'];
        $args = array( $name !== null
            ? 'profileEnd: No such Profile: ' . $name
            : 'profileEnd: Not currently profiling'
        );
        if (isset($this->instances[$name])) {
            $instance = $this->instances[$name];
            $data = $instance->end();
            /*
                So that our row keys can receive 'callable' formatting,
                set special '__key' value
            */
            $tableInfo = $logEntry->getMeta('tableInfo', array());
            $tableInfo = \array_replace_recursive(array(
                'rows' => \array_fill_keys(\array_keys($data), array()),
            ), $tableInfo);
            foreach (\array_keys($data) as $k) {
                $tableInfo['rows'][$k]['key'] = new Abstraction(
                    Abstracter::TYPE_CALLABLE,
                    array(
                        'value' => $k,
                        'hideType' => true, // don't output 'callable'
                    )
                );
            }
            $caption = 'Profile \'' . $name . '\' Results';
            $args = array($caption, 'no data');
            if ($data) {
                $args = array( $data );
                $logEntry->setMeta(array(
                    'caption' => $caption,
                    'totalCols' => array('ownTime'),
                    'tableInfo' => $tableInfo,
                ));
            }
            unset($this->instances[$name]);
        }
        $logEntry['args'] = $args;
        $debug->methodTable->doTable($logEntry);
        $debug->log($logEntry);
    }

    /**
     * End profiling and return data
     *
     * @return array profile data
     */
    public function end()
    {
        \unregister_tick_function(array($this, 'tickFunction'));
        while ($this->funcStack) {
            $this->popStack();
        }
        // sort by totalTime descending
        \uasort($this->data, static function ($valA, $valB) {
            return $valA['totalTime'] < $valB['totalTime'] ? 1 : -1;
        });
        $data =  \array_map(static function ($row) {
            $row['totalTime'] = \round($row['totalTime'], 6);
            $row['ownTime'] = \round($row['ownTime'], 6);
            return $row;
        }, $this->data);
        $this->data = array();
        $this->funcStack = array();
        $this->isProfiling = false;
        $this->rootStack = array();
        return $data;
    }

    /**
     * Set initial stack info
     *
     * @return bool
     */
    public function start()
    {
        if ($this->isProfiling) {
            return false;
        }
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $backtrace = $this->backtraceRemoveInternal($backtrace);
        foreach ($backtrace as $frame) {
            $class = isset($frame['class']) ? $frame['class'] . '::' : '';
            $this->rootStack[] = $class . $frame['function'];
        }
        \register_tick_function(array($this, 'tickFunction'));
        $this->isProfiling = true;
        $this->timeLastTick = \microtime(true);
        return true;
    }

    /**
     * Tick function
     *
     * @return void
     */
    public function tickFunction()
    {
        $this->trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $stackCount = \count($this->trace) - \count($this->rootStack) - 1;
        $stackCountInternal = \count($this->funcStack);
        $class = isset($this->trace[1]['class']) ? $this->trace[1]['class'] : null;
        if ($stackCount === 0 && $this->data) {
            $function = \ltrim($class . '::' . $this->trace[1]['function'], ':');
            if ($function !== $this->rootStack[0]) {
                // We've traveled up the stack above where we started
                \array_shift($this->rootStack);
                $this->pushStack($function);
            }
            $this->timeLastTick = \microtime(true);
            return;
        }
        $conditionsMet = \array_filter(array(
            $stackCount < 1,
            $stackCount === $stackCountInternal,        // no change in stack
            \preg_match($this->nsIgnoreRegex, $class)
        ));
        if ($conditionsMet) {
            $this->timeLastTick = \microtime(true);
            return;
        }
        $this->updateStack($stackCount, $stackCountInternal);
        $this->timeLastTick = \microtime(true);
    }

    /**
     * Remove internal frames from backtrace
     *
     * @param array $backtrace backtrace
     *
     * @return array
     */
    protected function backtraceRemoveInternal($backtrace)
    {
        $count = \count($backtrace);
        $nsRegex = '#^' . \preg_quote($this->namespace) . '\b#';
        for ($i = $count - 1; $i > 0; $i--) {
            $frame = $backtrace[$i];
            if (isset($frame['class']) && \preg_match($nsRegex, $frame['class'])) {
                break;
            }
        }
        $i++;
        return \array_slice($backtrace, $i);
    }

    /**
     * Remove function from stack and add time to profile
     *
     * @return string name of popped function
     */
    protected function popStack()
    {
        $stackInfo = \array_pop($this->funcStack);
        $funcPopped = $stackInfo['function'];
        $timeElapsed = \microtime(true) - $stackInfo['tsStart'];
        $this->data[$funcPopped]['ownTime'] += $timeElapsed - $stackInfo['subTime'];
        $this->data[$funcPopped]['totalTime'] += $timeElapsed;
        if ($this->data[$funcPopped]['calls'] === 0) {
            $this->data[$funcPopped]['calls'] ++;
        }
        if ($this->funcStack) {
            $this->funcStack[\count($this->funcStack) - 1]['subTime'] += $timeElapsed;
        }
        return $stackInfo['function'];
    }

    /**
     * Add function to call stack
     *
     * @param string $funcName fully qualified function name
     *
     * @return void
     */
    protected function pushStack($funcName)
    {
        $this->funcStack[] = array(
            'function' => $funcName,
            'tsStart' => $this->timeLastTick,
            'subTime' => 0,         // how much time spent in nested functions
        );
        if (!isset($this->data[$funcName])) {
            $this->data[$funcName] = array(
                'calls' => 0,
                'totalTime' => 0,   // time spent in function and nested func
                'ownTime' => 0,     // time spent in function excluding nested funcs
            );
        }
        $this->data[$funcName]['calls'] ++;
    }

    /**
     * Add or remove functions to stack
     *
     * @param int $stackCount         diff between backtrace and initial backtrace
     * @param int $stackCountInternal num functions on our stack
     *
     * @return void
     */
    protected function updateStack($stackCount, $stackCountInternal)
    {
        if ($stackCount <= $stackCountInternal) {
            $this->popStack();
            return;
        }
        $diff = $stackCount - $stackCountInternal;
        for ($i = $diff; $i > 0; $i--) {
            $class = isset($this->trace[$i]['class']) ? $this->trace[$i]['class'] : null;
            if (\preg_match($this->nsIgnoreRegex, $class)) {
                break;
            }
            $function = \ltrim($class . '::' . $this->trace[$i]['function'], ':');
            $this->pushStack($function);
        }
    }
}

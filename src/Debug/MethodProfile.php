<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug;

use bdk\PubSub\Event;

/**
 * Clear method
 */
class MethodProfile
{

    /**
     * @var string Ignore methods in these namespaces
     */
    protected $nsIgnoreRegex;

    /**
     * @var array profile data
     */
    protected $data = array();

    protected $funcStack = array();
    protected $isProfiling = false;
    protected $rootStack = array();
    protected $timeLastTick = null;

    /**
     * Constructor
     *
     * @param array $namespacesIgnore array of namespaces who's methods will be excluded from profile
     */
    public function __construct($namespacesIgnore = array())
    {
        $namespacesIgnore = \array_merge(array(__NAMESPACE__), $namespacesIgnore);
        $this->nsIgnoreRegex = \str_replace('\\', '\\\\', '#^('.\implode('|', $namespacesIgnore).')(\\|$)#');
        $this->start();
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
        \uasort($this->data, function ($valA, $valB) {
            return ($valA['totalTime'] < $valB['totalTime']) ? 1 : -1;
        });
        $data =  \array_map(function ($row) {
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
     * @return boolean
     */
    public function start()
    {
        if ($this->isProfiling) {
            return false;
        }
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $backtrace = $this->backtraceRemoveInternal($backtrace);
        foreach ($backtrace as $frame) {
            $class = isset($frame['class']) ? $frame['class'].'::' : '';
            $this->rootStack[] = $class.$frame['function'];
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
        $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $stackCount = \count($trace) - \count($this->rootStack) - 1;
        $stackCountInternal = \count($this->funcStack);
        $class = isset($trace[1]['class']) ? $trace[1]['class'] : null;
        if ($stackCount === 0 && $this->data) {
            $function = \ltrim($class.'::'.$trace[1]['function'], ':');
            if ($function !== $this->rootStack[0]) {
                // We've traveled up the stack above where we started
                \array_shift($this->rootStack);
                $this->pushStack($function);
            }
            $this->timeLastTick = \microtime(true);
            return;
        }
        if (\array_filter(array(
            $stackCount < 1,
            $stackCount === $stackCountInternal,        // no change in stack
            \preg_match($this->nsIgnoreRegex, $class)
        ))) {
            $this->timeLastTick = \microtime(true);
            return;
        }
        if ($stackCount > $stackCountInternal) {
            $diff = $stackCount - $stackCountInternal;
            for ($i = $diff; $i > 0; $i--) {
                $class = isset($trace[$i]['class']) ? $trace[$i]['class'] : null;
                if (\preg_match($this->nsIgnoreRegex, $class)) {
                    break;
                }
                $function = \ltrim($class.'::'.$trace[$i]['function'], ':');
                $this->pushStack($function);
            }
        } else {
            $this->popStack();
        }
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
        for ($i = $count - 1; $i > 0; $i--) {
            $frame = $backtrace[$i];
            if (isset($frame['class']) && \strpos($frame['class'], __NAMESPACE__) === 0) {
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
            $this->funcStack[\count($this->funcStack)-1]['subTime'] += $timeElapsed;
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
}

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

namespace bdk\Debug\Utility;

/**
 * Utility for collecting profile data via tick_function
 */
class Profile
{
    /** @var array profile data */
    protected $data = array();
    /** @var list<array{function:non-empty-string,subTime:float,tsStart:float}> */
    protected $funcStack = [];
    /** @var bool */
    protected $isProfiling = false;
    /** @var non-empty-string */
    protected $namespace = 'bdk\\Debug';
    /** @var non-empty-string Ignore methods in these namespaces */
    protected $nsIgnoreRegex;
    /** @var list<non-empty-string> */
    protected $rootStack = [];
    /** @var float|null */
    protected $timeLastTick = null;
    /** @var array */
    protected $trace = array();

    /**
     * Constructor
     *
     * @param array $namespacesIgnore array of namespaces who's methods will be excluded from profile
     */
    public function __construct($namespacesIgnore = array())
    {
        $namespacesIgnore = \array_merge([$this->namespace], (array) $namespacesIgnore);
        $namespacesIgnore = \array_unique($namespacesIgnore);
        $this->nsIgnoreRegex = \str_replace('\\', '\\\\', '#^(' . \implode('|', $namespacesIgnore) . ')(\\|$)#');
    }

    /**
     * End profiling and return data
     *
     * @return array profile data
     */
    public function end()
    {
        \unregister_tick_function([$this, 'tickFunction']);
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
        $this->funcStack = [];
        $this->isProfiling = false;
        $this->rootStack = [];
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
        \register_tick_function([$this, 'tickFunction']);
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
        $class = isset($this->trace[1]['class']) ? $this->trace[1]['class'] : '';
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
        $conditionsMet = \array_filter([
            $stackCount < 1,
            $stackCount === $stackCountInternal,        // no change in stack
            \preg_match($this->nsIgnoreRegex, $class),
        ]);
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
            $this->data[$funcPopped]['calls']++;
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
            'subTime' => 0,         // how much time spent in nested functions
            'tsStart' => $this->timeLastTick,
        );
        if (!isset($this->data[$funcName])) {
            // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
            $this->data[$funcName] = array(
                'calls' => 0,
                'totalTime' => 0,   // time spent in function and nested func
                'ownTime' => 0,     // time spent in function excluding nested funcs
            );
        }
        $this->data[$funcName]['calls']++;
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
            $class = isset($this->trace[$i]['class']) ? $this->trace[$i]['class'] : '';
            if (\preg_match($this->nsIgnoreRegex, $class)) {
                break;
            }
            $function = \ltrim($class . '::' . $this->trace[$i]['function'], ':');
            $this->pushStack($function);
        }
    }
}

<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Plugin\Method;

use BadMethodCallException;
use bdk\Backtrace;
use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\Debug\Plugin\Method\GroupCleanup;
use bdk\Debug\Plugin\Method\GroupStack;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Group methods
 */
class Group implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var GroupStack|null */
    protected $groupStack;

    /** @var string[] */
    protected $methods = [
        'group',
        'groupCollapsed',
        'groupEnd',
        'groupSummary',
        'groupUncollapse',
    ];

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
    }

    /**
     * Magic method... inaccessible method called.
     *
     * Try custom method.
     *
     * @param string $method Inaccessible method name
     * @param array  $args   Arguments passed to method
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call($method, array $args)
    {
        $methods = [
            'getCurrentGroups',
            'getCurrentPriority',
            'getDepth',
            'reset',
            'setLogDest',
        ];
        if (\in_array($method, $methods, true) === false) {
            throw new BadMethodCallException(__CLASS__ . '::' . $method . ' is inaccessible');
        }
        return \call_user_func_array([$this->groupStack, $method], $args);
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
            Debug::EVENT_PLUGIN_INIT => 'onPluginInit',
        );
    }

    /**
     * Create a new inline group
     *
     * Groups generally get indented and will receive an expand/collapse toggle.
     *
     * applicable meta args:
     *      argsAsParams: true
     *      boldLabel: true
     *      hideIfEmpty: false
     *      isFuncName: (bool)
     *      level: (string)
     *      ungroup: false  // when closed: if no children, convert to plain log entry
     *                      // when closed: if only one child, remove the containing group
     *
     * @param mixed ...$arg. label / values
     *
     * @return Debug
     */
    public function group()
    {
        $this->doGroup(new LogEntry($this->debug, __FUNCTION__, \func_get_args()));
        return $this->debug;
    }

    /**
     * Create a new inline group
     *
     * Unlike `group()`, `groupCollapsed()`, will initially be collapsed
     *
     * @param mixed ...$arg label / values
     *
     * @return Debug
     */
    public function groupCollapsed()
    {
        $this->doGroup(new LogEntry($this->debug, __FUNCTION__, \func_get_args()));
        return $this->debug;
    }

    /**
     * Close current group
     *
     * Every call to `group()`, `groupCollapsed()`, and `groupSummary()` should be paired with `groupEnd()`
     *
     * The optional return value will be visible when the group is both expanded and collapsed.
     *
     * @param mixed $value (optional) "return" value
     *
     * @return Debug
     *
     * @since 2.3 accepts `$value` parameter
     */
    public function groupEnd($value = Abstracter::UNDEFINED) // @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        $this->doGroupEnd(new LogEntry(
            $this->debug,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $this->debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__)
        ));
        return $this->debug;
    }

    /**
     * Open a "summary" group
     *
     * Debug methods called from within a groupSummary will appear at the top of the log.
     * Call `groupEnd()` to close the summary group
     *
     * All groupSummary groups will appear together at the top of the output
     *
     * @param int $priority (0) The higher the priority, the earlier the group will appear in output
     *
     * @return Debug
     */
    public function groupSummary($priority = 0) // @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        $this->doGroupSummary(new LogEntry(
            $this->debug,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $this->debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__),
            ['priority']
        ));
        return $this->debug;
    }

    /**
     * Uncollapse ancestor groups
     *
     * This will only occur if `cfg['collect']` is currently `true`
     *
     * @return Debug
     */
    public function groupUncollapse()
    {
        if ($this->debug->getCfg('collect', Debug::CONFIG_DEBUG) === false) {
            return $this->debug;
        }
        $this->doGroupUncollapse(new LogEntry($this->debug, __FUNCTION__, \func_get_args()));
        return $this->debug;
    }

    /**
     * Debug::EVENT_PLUGIN_INIT subscriber
     *
     * @param Event $event Debug::EVENT_PLUGIN_INIT Event instance
     *
     * @return void
     */
    public function onPluginInit(Event $event)
    {
        $debug = $event->getSubject();
        $this->groupStack = new GroupStack($debug);
        $debug->addPlugin(new GroupCleanup($this->groupStack), 'groupCleanup');
    }

    /**
     * Handle both group and groupCollapsed
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function doGroup(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $collect = $debug->getCfg('collect', Debug::CONFIG_DEBUG);
        $this->groupStack->push($debug, $collect);
        if ($collect === false) {
            return;
        }
        if ($logEntry['args'] === []) {
            // give a default label
            $logEntry['args'] = ['group'];
            $caller = $this->debug->backtrace->getCallerInfo(0, Backtrace::INCL_ARGS);
            $args = $this->autoArgs($caller);
            if ($args) {
                $logEntry['args'] = $args;
                $logEntry->setMeta('isFuncName', true);
            }
        }
        $briefBak = $debug->abstracter->setCfg('brief', true);
        $debug->log($logEntry);
        $debug->abstracter->setCfg('brief', $briefBak);
    }

    /**
     * Handle debug's groupEnd method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function doGroupEnd(LogEntry $logEntry)
    {
        $haveOpen = $this->groupStack->haveOpenGroup();
        if ($haveOpen === 2) {
            // we're closing a summary group
            $this->groupEndSummary($logEntry);
        } elseif ($haveOpen === 1) {
            $this->groupEndMain($logEntry);
        }
        $errorCaller = $this->debug->errorHandler->get('errorCaller');
        if ($errorCaller && isset($errorCaller['groupDepth']) && $this->groupStack->getDepth() < $errorCaller['groupDepth']) {
            $this->debug->errorHandler->setErrorCaller(false);
        }
    }

    /**
     * Handle debug's groupSummary method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function doGroupSummary(LogEntry $logEntry)
    {
        $this->groupStack->pushPriority($logEntry['meta']['priority']);
        $this->debug->data->set('logDest', 'summary');
        $logEntry['appendLog'] = false;     // don't actually log
        $logEntry['forcePublish'] = true;   // publish the Debug::EVENT_LOG event (regardless of cfg.collect)
        // groupSummary's Debug::EVENT_LOG event should happen on the root instance
        $this->debug->rootInstance->log($logEntry);
    }

    /**
     * Handle debug's groupUncollapse method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function doGroupUncollapse(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $groups = $this->groupStack->getCurrentGroups();
        foreach ($groups as $groupLogEntry) {
            $groupLogEntry['method'] = 'group';
        }
        $logEntry['appendLog'] = false;     // don't actually log
        $logEntry['forcePublish'] = true;   // publish the Debug::EVENT_LOG event (regardless of cfg.collect)
        $debug->log($logEntry);
    }

    /**
     * Automatic group/groupCollapsed arguments
     *
     * @param array $caller Caller Info
     *
     * @return array
     */
    private function autoArgs($caller = array())
    {
        $args = array();
        if (isset($caller['function']) === false) {
            return $args;
        }
        $file = null;
        $function = $caller['function'];
        $functionStartLine = 1;
        if (\preg_match('/\{closure\}$/', $caller['function'])) {
            $function = '{closure}';
        } elseif ($caller['class']) {
            $refMethod = new ReflectionMethod($caller['class'], $caller['function']);
            $functionStartLine = $refMethod->getStartLine();
            $file = $refMethod->getFileName();
            $function = $caller['classCalled'] . $caller['type'] . $caller['function'];
        } elseif (\in_array($caller['function'], ['include', 'include_once', 'require', 'require_once'], true) === false) {
            $refFunction = new ReflectionFunction($caller['function']);
            $functionStartLine = $refFunction->getStartLine();
            $file = $refFunction->getFileName();
        }
        if ($this->autoArgsTest($file, $functionStartLine, $caller['line']) || $this->autoArgsTestClosure($caller)) {
            $args[] = $function;
            $args = \array_merge($args, $caller['args']);
            // php < 7.0 debug_backtrace args are references!
            $args = $this->debug->arrayUtil->copy($args, false);
        }
        return $args;
    }

    /**
     * Test if called group/groupCollapsed is the first statement of a function/method
     *
     * @param string $file              file path
     * @param int    $functionStartLine function start
     * @param int    $callerLine        line calling group()
     *
     * @return bool
     */
    private function autoArgsTest($file, $functionStartLine, $callerLine)
    {
        if ($file === null) {
            return false;
        }
        if ($callerLine <= $functionStartLine + 2) {
            /*
                function closeEnough()   // functionStartLine
                {                        //
                    \bdk\Debug::group(); // functionStartLine + 2
            */
            return true;
        }
        /*
            We could have a multi line function signature
            function multiLine (  // functionStartLine
                string $foo,
                array $bar
            ) {
                \bdk\Debug::group();
        */
        $length = $callerLine - $functionStartLine + 1;
        $lineFirstStatement = $this->getFirstStatementLine($file, $functionStartLine, $length);
        return $callerLine === $lineFirstStatement;
    }

    /**
     * Get the line number of the first statement within a function/method
     *
     * @param string $file              File path
     * @param int    $functionStartLine Line with function keyword
     * @param int    $length            Number of lines to search
     *
     * @return int|false
     */
    private function getFirstStatementLine($file, $functionStartLine, $length)
    {
        $lines = $this->debug->backtrace->getFileLines($file, $functionStartLine, $length);
        $lines = \implode('', $lines);
        $tokens = $this->debug->findExit->getTokens($lines, false, false, $functionStartLine);
        $foundOpen = false;
        foreach ($tokens as $token) {
            if ($token === '{') {
                $foundOpen = true;
            } elseif ($foundOpen && \is_array($token)) {
                return $token[2];
            }
        }
        return false;
    }

    /**
     * Perform a rudimentary test to check if group is first statement within {closure}
     *
     * @param array $caller caller info
     *
     * @return bool
     */
    private function autoArgsTestClosure($caller)
    {
        if (\preg_match('/\{closure\}$/', $caller['function']) !== 1) {
            return false;
        }
        $lines = $this->debug->backtrace->getFileLines($caller['file'], $caller['line'] - 1, 1);
        $lines = \implode('', $lines);
        $tokens = $this->debug->findExit->getTokens($lines, false, false, $caller['line'] - 1);
        return \end($tokens) === '{'
            && \count(\array_filter($tokens, static function ($token) {
                return \is_array($token) && $token[0] === T_FUNCTION;
            }));
    }

    /**
     * Close a regular group
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function groupEndMain(LogEntry $logEntry)
    {
        $this->groupStack->pop();
        $debug = $logEntry->getSubject();
        $returnValue = $logEntry['args'][0];
        if ($returnValue !== Abstracter::UNDEFINED) {
            $label = $logEntry->getMeta('label', 'return');
            $args = $label
                ? [$label, $returnValue]
                : [$returnValue];
            $logEntry->setMeta('label', null); // delete label meta
            $debug->log(new LogEntry(
                $debug,
                'groupEndValue',
                $args,
                $logEntry->getMeta()
            ));
        }
        $logEntry['args'] = [];
        $debug->log($logEntry);
    }

    /**
     * Close a summary group
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function groupEndSummary(LogEntry $logEntry)
    {
        $this->groupStack->popPriority();
        $debug = $logEntry->getSubject();
        $debug->data->set('logDest', 'auto');
        $logEntry['appendLog'] = false;     // don't actually log
        $logEntry['args'] = [];
        $logEntry['forcePublish'] = true;   // Publish the Debug::EVENT_LOG event (regardless of cfg.collect)
        $logEntry->setMeta('closesSummary', true);
        $debug->log($logEntry);
    }
}

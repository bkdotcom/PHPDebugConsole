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

use bdk\Backtrace;
use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\PubSub\SubscriberInterface;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;

/**
 * Group methods
 */
class Group implements SubscriberInterface
{
    public $debug;

    private $cleanupInfo = array(
        'stack' => array(),
        'stackCount' => 0,
    );

    protected $groupStack;

    private $inShutdown = false;

    protected $log = array();

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $debug->eventManager->addSubscriberInterface($this);
        $this->groupStack = new \bdk\Debug\Method\GroupStack($debug);
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
     * @throws RuntimeException
     */
    public function __call($method, $args)
    {
        $methods = array(
            'getCurrentGroups',
            'getCurrentPriority',
            'getDepth',
            'reset',
            'setLogDest',
        );
        if (\in_array($method, $methods, true) === false) {
            throw new RuntimeException(__CLASS__ . '::' . $method . ' is inaccessable');
        }
        return \call_user_func_array(array($this->groupStack, $method), $args);
    }

    /**
     * Handle both group and groupCollapsed
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function methodGroup(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $collect = $debug->getCfg('collect', Debug::CONFIG_DEBUG);
        $this->groupStack->push($debug, $collect);
        if ($collect === false) {
            return;
        }
        if ($logEntry['args'] === array()) {
            // give a default label
            $logEntry['args'] = array( 'group' );
            $caller = $this->debug->backtrace->getCallerInfo(0, Backtrace::INCL_ARGS);
            $args = $this->autoArgs($caller);
            if ($args) {
                $logEntry['args'] = $args;
                $logEntry->setMeta('isFuncName', true);
            }
        }
        $cfgAbsBak = $debug->abstracter->setCfg(array(
            'brief' => true,
        ));
        $debug->log($logEntry);
        $debug->abstracter->setCfg($cfgAbsBak);
    }

    /**
     * Handle debug's groupEnd method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function methodGroupEnd(LogEntry $logEntry)
    {
        $this->debug = $logEntry->getSubject();
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
    public function methodGroupSummary(LogEntry $logEntry)
    {
        $this->groupStack->pushPriority($logEntry['meta']['priority']);
        $this->debug->data->set('logDest', 'summary');
        $logEntry['appendLog'] = false;     // don't actually log
        $logEntry['forcePublish'] = true;   // publish the Debug::EVENT_LOG event (regardless of cfg.collect)
        // groupSumary's Debug::EVENT_LOG event should happen on the root instance
        $this->debug->rootInstance->log($logEntry);
    }

    /**
     * Handle debug's groupUncollapse method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function methodGroupUncollapse(LogEntry $logEntry)
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
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OUTPUT => array('onOutput', PHP_INT_MAX),
            EventManager::EVENT_PHP_SHUTDOWN => array('onShutdown', PHP_INT_MAX),
        );
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     *    close open groups
     *    remove "hide-if-empty" groups
     *    uncollapse errors
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onOutput(Event $event)
    {
        $handle = $event['isTarget'] || $event->getSubject()->parentInstance === null;
        if ($handle === false) {
            /*
                All channels share the same data.
                We only need to do this via the channel that called output
            */
            return;
        }
        $this->closeOpen();
        $data = $this->debug->data->get();
        $data['log'] = \array_values($data['log']);
        $this->log = &$data['log'];
        $this->onOutputCleanup();
        $this->uncollapseErrors();
        $summaryKeys = \array_keys($data['logSummary']);
        foreach ($summaryKeys as $key) {
            $this->log = &$data['logSummary'][$key];
            $this->onOutputCleanup();
            $this->uncollapseErrors();
        }
        $this->debug->data->set($data);
    }

    /**
     * EventManager::EVENT_PHP_SHUTDOWN subscriber
     *
     * @return void
     */
    public function onShutdown()
    {
        $this->closeOpen();
        $this->inShutdown = true;
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
        } elseif (\in_array($caller['function'], array('include', 'include_once', 'require', 'require_once'), true) === false) {
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
     * @param string $file              [description]
     * @param int    $functionStartLine [description]
     * @param int    $callerLine        [description]
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
        $lines = $this->debug->backtrace->getFileLines($file, $functionStartLine, $length);
        $lines = \implode('', $lines);
        $tokens = $this->debug->findExit->getTokens($lines, false, false, $functionStartLine);
        $foundOpen = false;
        $lineFirstStatement = null;
        foreach ($tokens as $token) {
            if ($token === '{') {
                $foundOpen = true;
                continue;
            }
            if ($foundOpen && \is_array($token)) {
                $lineFirstStatement = $token[2];
                break;
            }
        }
        return $callerLine === $lineFirstStatement;
    }

    /**
     * Perform a rudamentary test to check if group is first statement within {closure}
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
     * Close any unclosed groups
     *
     * We may have forgotten to end a group or the script may have exited
     *
     * @return void
     */
    private function closeOpen()
    {
        if ($this->inShutdown) {
            // we already closed
            return;
        }
        $priorityStack = $this->groupStack->get();
        while ($priorityStack) {
            $priority = \array_pop($priorityStack);
            $stack = $this->groupStack->get($priority);
            while ($stack) {
                $info = \array_pop($stack);
                $info['channel']->groupEnd();
            }
            if (\is_int($priority)) {
                // close the summary
                $this->debug->groupEnd();
            }
        }
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
            $debug->log(new LogEntry(
                $debug,
                'groupEndValue',
                array('return', $returnValue)
            ));
        }
        $logEntry['args'] = array();
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
        $logEntry['args'] = array();
        $logEntry['forcePublish'] = true;   // Publish the Debug::EVENT_LOG event (regardless of cfg.collect)
        $logEntry->setMeta('closesSummary', true);
        $debug->log($logEntry);
    }

    /**
     * Remove empty groups having 'hideIfEmpty' meta value
     * Convert empty groups having "ungroup" meta value to log entries
     *
     * @return void
     */
    private function onOutputCleanup()
    {
        $this->cleanupInfo = array(
            'stack' => array(
                array(
                    // dummy / root group
                    //  eliminates need to test if entry has parent group
                    'childCount' => 0,
                    'groupCount' => 0,
                    'depth' => 0,
                )
            ),
            'stackCount' => 1,
        );
        $reindex = false;
        for ($i = 0, $count = \count($this->log); $i < $count; $i++) {
            $reindex = $this->outputCleanupPLE($i) || $reindex;
        }
        if ($reindex) {
            $this->log = \array_values($this->log);
        }
    }

    /**
     * Handle group hideIfEmpty & ungroup meta options
     *
     * @param array $group Group info collected in onOutputCleanup
     *
     * @return bool Whether log needs re-indexed
     */
    private function outputCleanupGroup($group = array())
    {
        $parent = &$this->cleanupInfo['stack'][ $group['depth'] - 1 ];
        if (!empty($group['meta']['hideIfEmpty']) && $group['childCount'] === 0) {
            unset($this->log[$group['index']]);     // remove open entry
            unset($this->log[$group['indexEnd']]);  // remove end entry
            $parent['childCount']--;
            $parent['groupCount']--;
            return true;
        }
        if (empty($group['meta']['ungroup'])) {
            return false;
        }
        if ($group['childCount'] === 0) {
            $this->log[$group['index']]['method'] = 'log';
            unset($this->log[$group['indexEnd']]);  // remove end entry
            $parent['groupCount']--;
            return true;
        }
        if ($group['childCount'] === 1 && $group['groupCount'] === 0) {
            unset($this->log[$group['index']]);     // remove open entry
            unset($this->log[$group['indexEnd']]);  // remove end entry
            $parent['groupCount']--;
            return true;
        }
        return false;
    }

    /**
     * Update groupStack stats durring onOutputCleanup / Process LogEntry
     *
     * @param int $index Log entry index
     *
     * @return bool Whether log needs re-indexed
     */
    private function outputCleanupPLE($index)
    {
        $logEntry = $this->log[$index];
        $method = $logEntry['method'];
        $stackCount = $this->cleanupInfo['stackCount'];
        if (\in_array($method, array('group', 'groupCollapsed'), true)) {
            $this->cleanupInfo['stack'][] = array(
                'childCount' => 0,  // includes any child groups
                'groupCount' => 0,
                'index' => $index,
                'indexEnd' => null,
                'meta' => $logEntry['meta'],
                'depth' => $stackCount,
            );
            $this->cleanupInfo['stack'][$stackCount - 1]['childCount']++;
            $this->cleanupInfo['stack'][$stackCount - 1]['groupCount']++;
            $this->cleanupInfo['stackCount']++;
            return false;
        }
        if ($method === 'groupEnd') {
            $group = \array_pop($this->cleanupInfo['stack']);
            $group['indexEnd'] = $index;
            $this->cleanupInfo['stackCount']--;
            return $this->outputCleanupGroup($group);
        }
        $this->cleanupInfo['stack'][$stackCount - 1]['childCount']++;
        return false;
    }

    /**
     * Uncollapse groups containing errors.
     *
     * Occurs onOutput
     *
     * @return void
     */
    private function uncollapseErrors()
    {
        $groupStack = array();
        for ($i = 0, $count = \count($this->log); $i < $count; $i++) {
            switch ($this->log[$i]['method']) {
                case 'group':
                case 'groupCollapsed':
                    $groupStack[] = $this->log[$i];
                    break;
                case 'groupEnd':
                    \array_pop($groupStack);
                    break;
                case 'error':
                case 'warn':
                    $this->uncollapseError($this->log[$i], $groupStack);
                    break;
            }
        }
    }

    /**
     * Error encountered.  Uncollapse ancestor groups
     *
     * @param LogEntry   $logEntry   LogEntry instance (error or warn)
     * @param LogEntry[] $groupStack Ancestor groups
     *
     * @return void
     */
    private function uncollapseError(LogEntry $logEntry, $groupStack)
    {
        if ($logEntry->getMeta('uncollapse') === false) {
            return;
        }
        foreach ($groupStack as $logEntry) {
            $logEntry['method'] = 'group';
        }
    }
}

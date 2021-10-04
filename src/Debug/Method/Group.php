<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
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

/**
 * Group methods
 */
class Group implements SubscriberInterface
{

    public $debug;

    protected $log = array();

    private $groupPriorityStack = array(); // array of priorities
                                        //   used to return to the previous summary when groupEnd()ing out of a summary
                                        //   this allows calling groupSummary() while in a groupSummary
    private $groupStacks = array(
        'main' => array(),  // array('channel' => Debug instance, 'collect' => bool)[]
    );
    private $groupStacksRef = null;    // points to $this->data['groupStacks'][x] (where x = 'main' or (int) priority)

    private $inShutdown = false;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $debug->eventManager->addSubscriberInterface($this);
        $this->groupStacksRef = &$this->groupStacks['main'];
    }

    /**
     * Handle both group and groupCollapsed
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function doGroup(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $collect = $debug->getCfg('collect', Debug::CONFIG_DEBUG);
        \array_push($this->groupStacksRef, array(
            'channel' => $debug,
            'collect' => $collect,
        ));
        if ($collect === false) {
            return;
        }
        if (!$logEntry['args']) {
            // give a default label
            $logEntry['args'] = array( 'group' );
            $caller = $this->debug->backtrace->getCallerInfo(0, Backtrace::INCL_ARGS);
            $args = $this->autoArgs($caller);
            if ($args) {
                $logEntry['args'] = $args;
                $logEntry->setMeta('isFuncName', true);
            }
        }
        $this->stringifyArgs($logEntry);
        $this->debug->log($logEntry);
    }

    /**
     * Handle debug's groupEnd method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function groupEnd(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $haveOpen = $this->haveOpen($debug);
        $value = $logEntry['args'][0];
        $logEntry['args'] = array();
        if ($haveOpen === 2) {
            // we're closing a summary group
            $priorityClosing = \array_pop($this->groupPriorityStack);
            // not really necessary to remove this empty placeholder, but lets keep things tidy
            unset($this->groupStacks[$priorityClosing]);
            $debug->setData('logDest', 'auto');
            $logEntry['appendLog'] = false;     // don't actually log
            $logEntry['forcePublish'] = true;   // Publish the Debug::EVENT_LOG event (regardless of cfg.collect)
            $logEntry->setMeta('closesSummary', true);
            $debug->log($logEntry);
        } elseif ($haveOpen === 1) {
            \array_pop($this->groupStacksRef);
            if ($value !== Abstracter::UNDEFINED) {
                $debug->log(new LogEntry(
                    $debug,
                    'groupEndValue',
                    array('return', $value)
                ));
            }
            $debug->log($logEntry);
        }
        $errorCaller = $debug->errorHandler->get('errorCaller');
        if ($errorCaller && isset($errorCaller['groupDepth']) && $this->getDepth() < $errorCaller['groupDepth']) {
            $debug->errorHandler->setErrorCaller(false);
        }
    }

    /**
     * Handle debug's groupSummary method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function groupSummary(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        \array_push($this->groupPriorityStack, $logEntry['meta']['priority']);
        $debug->setData('logDest', 'summary');
        $logEntry['appendLog'] = false;     // don't actually log
        $logEntry['forcePublish'] = true;   // publish the Debug::EVENT_LOG event (regardless of cfg.collect)
        // groupSumary's Debug::EVENT_LOG event should happen on the root instance
        $debug->rootInstance->log($logEntry);
    }

    /**
     * Handle debug's groupUncollapse method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function groupUncollapse(LogEntry $logEntry)
    {
        $groups = $this->getCurrentGroups('auto');
        foreach ($groups as $groupLogEntry) {
            $groupLogEntry['method'] = 'group';
        }
        $logEntry['appendLog'] = false;     // don't actually log
        $logEntry['forcePublish'] = true;   // publish the Debug::EVENT_LOG event (regardless of cfg.collect)
        $this->debug->log($logEntry);
    }

    /**
     * Return the group & groupCollapsed ("ancestors")
     *
     * @param 'auto'|'main'|int $where 'auto', 'main' or summary priority
     *
     * @return LogEntry[] kwys are maintained
     */
    public function getCurrentGroups($where = 'auto')
    {
        if ($where === 'auto') {
            $where = $this->getCurrentPriority();
        }

        /*
            Determine current depth
        */
        $curDepth = 0;
        foreach ($this->groupStacks[$where] as $group) {
            $curDepth += (int) $group['collect'];
        }

        $entries = array();
        /*
            curDepth will fluctuate as we go back through log
            minDepth will decrease as we work our way down/up the groups
        */
        $logEntries = $where === 'main'
            ? $this->debug->getData(array('log'))
            : $this->debug->getData(array('logSummary', $where));
        $minDepth = $curDepth;
        for ($i = \count($logEntries) - 1; $i >= 0; $i--) {
            if ($curDepth < 1) {
                break;
            }
            $method = $logEntries[$i]['method'];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $curDepth--;
                if ($curDepth < $minDepth) {
                    $minDepth--;
                    $entries[$i] = $logEntries[$i];
                }
            } elseif ($method === 'groupEnd') {
                $curDepth++;
            }
        }
        return $entries;
    }

    /**
     * Get current group priority
     *
     * @return 'main'|int
     */
    public function getCurrentPriority()
    {
        $priority = \end($this->groupPriorityStack);
        return $priority !== false
            ? $priority
            : 'main';
    }

    /**
     * Calculate total group depth
     *
     * @return int
     */
    public function getDepth()
    {
        $depth = 0;
        foreach ($this->groupStacks as $stack) {
            $depth += \count($stack);
        }
        $depth += \count($this->groupPriorityStack);
        return $depth;
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
        $data = $this->debug->getData();
        $this->log = &$data['log'];
        $this->onOutputCleanup();
        $this->uncollapseErrors();
        $summaryKeys = \array_keys($data['logSummary']);
        foreach ($summaryKeys as $key) {
            $this->log = &$data['logSummary'][$key];
            $this->onOutputCleanup();
            $this->uncollapseErrors();
        }
        $this->debug->setData($data);
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
     * Clear specified stack
     *
     * @param string|ing $where 'main', 'summary', or `int`
     *
     * @return void
     */
    public function resetStack($where)
    {
        // typeCast to string so that 0 does not match 'main'
        switch ((string) $where) {
            case 'main':
                $this->groupStacks['main'] = array();
                $this->groupStacksRef = &$this->groupStacks['main'];
                return;
            case 'summary':
                $this->groupPriorityStack = array();
                $this->groupStacks = array(
                    'main' => $this->groupStacks['main'],
                );
                $this->groupStacksRef = &$this->groupStacks['main'];
                return;
            default:
                $this->groupStacks[$where] = array();
                return;
        }
    }

    /**
     * Point groupStacksRef to specified stack
     *
     * @param string $where 'main' or 'summary'
     *
     * @return void
     */
    public function setLogDest($where)
    {
        switch ($where) {
            case 'main':
                $this->groupStacksRef = &$this->groupStacks['main'];
                return;
            case 'summary':
                $priority = \end($this->groupPriorityStack);
                if (!isset($this->groupStacks[$priority])) {
                    $this->groupStacks[$priority] = array();
                }
                $this->groupStacksRef = &$this->groupStacks[$priority];
                return;
        }
    }

    /**
     * Automatic group/groupCollapsed arguments
     *
     * @param array $caller CallerInfo
     *
     * @return array
     */
    private function autoArgs($caller = array())
    {
        $args = array();
        if (isset($caller['function']) === false) {
            return $args;
        }
        // default args if first call inside function... and debugGroup is likely first call
        $function = null;
        $callerStartLine = 1;
        if ($caller['class']) {
            $refClass = new \ReflectionClass($caller['class']);
            $refMethod = $refClass->getMethod($caller['function']);
            $callerStartLine = $refMethod->getStartLine();
            $function = $caller['class'] . $caller['type'] . $caller['function'];
        } elseif (!\in_array($caller['function'], array('include', 'include_once', 'require', 'require_once'))) {
            $refFunction = new \ReflectionFunction($caller['function']);
            $callerStartLine = $refFunction->getStartLine();
            $function = $caller['function'];
        }
        if ($function && $caller['line'] <= $callerStartLine + 2) {
            $args[] = $function;
            $args = \array_merge($args, $caller['args']);
            // php < 7.0 debug_backtrace args are references!
            $args = $this->debug->arrayUtil->copy($args, false);
        }
        return $args;
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
        $groupPriorityStack = \array_merge(array('main'), $this->groupPriorityStack);
        while ($groupPriorityStack) {
            $priority = \array_pop($groupPriorityStack);
            $stack = $this->groupStacks[$priority];
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
     * Are we inside a group?
     *
     * @param Debug $debug Debug instance
     *
     * @return int 2: group summary, 1: regular group, 0: not in group
     */
    private function haveOpen(Debug $debug)
    {
        $groupStack = $this->groupStacksRef;
        if ($this->groupPriorityStack && !$groupStack) {
            // we're in top level of group summary
            return 2;
        }
        if ($groupStack && \end($groupStack)['collect'] === $debug->getCfg('collect', Debug::CONFIG_DEBUG)) {
            return 1;
        }
        return 0;
    }

    /**
     * Remove empty groups having 'hideIfEmpty' meta value
     * Convert empty groups having "ungroup" meta value to log entries
     *
     * @return void
     */
    private function onOutputCleanup()
    {
        $groupStack = array(
            array(
                // dummy / root group
                //  eliminates need to test if entry has parent group
                'childCount' => 0,
                'groupCount' => 0,
            )
        );
        $groupStackCount = 1;
        $reindex = false;
        for ($i = 0, $count = \count($this->log); $i < $count; $i++) {
            $logEntry = $this->log[$i];
            $method = $logEntry['method'];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[] = array(
                    'childCount' => 0,  // includes any child groups
                    'groupCount' => 0,
                    'i' => $i,
                    'iEnd' => null,
                    'meta' => $logEntry['meta'],
                    'parent' => null,
                );
                $groupStack[$groupStackCount - 1]['childCount']++;
                $groupStack[$groupStackCount - 1]['groupCount']++;
                $groupStack[$groupStackCount]['parent'] = &$groupStack[$groupStackCount - 1];
                $groupStackCount++;
                continue;
            }
            if ($method === 'groupEnd') {
                $group = \array_pop($groupStack);
                $group['iEnd'] = $i;
                $groupStackCount--;
                $reindex = $this->onOutputCleanupGroup($group) || $reindex;
                continue;
            }
            $groupStack[$groupStackCount - 1]['childCount']++;
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
    private function onOutputCleanupGroup(&$group = array())
    {
        if (!empty($group['meta']['hideIfEmpty'])) {
            if ($group['childCount'] === 0) {
                unset($this->log[$group['i']]);     // remove open entry
                unset($this->log[$group['iEnd']]);  // remove end entry
                $group['parent']['childCount']--;
                $group['parent']['groupCount']--;
                return true;
            }
        }
        if (!empty($group['meta']['ungroup'])) {
            if ($group['childCount'] === 0) {
                $this->log[$group['i']]['method'] = 'log';
                unset($this->log[$group['iEnd']]);  // remove end entry
                $group['parent']['groupCount']--;
                return true;
            } elseif ($group['childCount'] === 1 && $group['groupCount'] === 0) {
                unset($this->log[$group['i']]);     // remove open entry
                unset($this->log[$group['iEnd']]);  // remove end entry
                $group['parent']['groupCount']--;
                return true;
            }
        }
        return false;
    }

    /**
     * Use string representation for group args if available
     *
     * @param LogEntry $logEntry Log entry
     *
     * @return void
     */
    private function stringifyArgs(LogEntry $logEntry)
    {
        $abstracter = $this->debug->abstracter;
        $args = $logEntry['args'];
        foreach ($args as $k => $v) {
            /*
                doGroupStringify is called before appendLog.
                values have not yet been abstracted.
                abstract now
            */
            $typeInfo = $abstracter->getType($v);
            if ($typeInfo[0] !== Abstracter::TYPE_OBJECT) {
                continue;
            }
            $v = $abstracter->crate($v, $logEntry['method']);
            if ($v['stringified']) {
                $v = $v['stringified'];
            } elseif (isset($v['methods']['__toString']['returnValue'])) {
                $v = $v['methods']['__toString']['returnValue'];
            }
            $args[$k] = $v;
        }
        $logEntry['args'] = $args;
    }

    /**
     * Uncollapse groups containing errors.
     *
     * @return void
     */
    private function uncollapseErrors()
    {
        $groupStack = array();
        for ($i = 0, $count = \count($this->log); $i < $count; $i++) {
            $method = $this->log[$i]['method'];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[] = $i;
            } elseif ($method === 'groupEnd') {
                \array_pop($groupStack);
            } elseif (\in_array($method, array('error', 'warn'))) {
                if ($this->log[$i]->getMeta('uncollapse') === false) {
                    continue;
                }
                foreach ($groupStack as $i2) {
                    $this->log[$i2]['method'] = 'group';
                }
            }
        }
    }
}

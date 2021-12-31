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

    private $cleanupInfo = array(
        'stack' => array(),
        'stackCount' => 0,
    );
    private $currentInfo = array(
        'curDepth' => 0,
        'minDepth' => 0,
        'logEntries' => array(),
    );

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
    public function methodGroup(LogEntry $logEntry)
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
        $debug->log($logEntry);
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
        $haveOpen = $this->methodGroupEndGet();
        if ($haveOpen === 2) {
            // we're closing a summary group
            $this->groupEndSummary($logEntry);
        } elseif ($haveOpen === 1) {
            $this->groupEndMain($logEntry);
        }
        $errorCaller = $this->debug->errorHandler->get('errorCaller');
        if ($errorCaller && isset($errorCaller['groupDepth']) && $this->getDepth() < $errorCaller['groupDepth']) {
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
        \array_push($this->groupPriorityStack, $logEntry['meta']['priority']);
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
        $groups = $this->getCurrentGroups('auto');
        foreach ($groups as $groupLogEntry) {
            $groupLogEntry['method'] = 'group';
        }
        $logEntry['appendLog'] = false;     // don't actually log
        $logEntry['forcePublish'] = true;   // publish the Debug::EVENT_LOG event (regardless of cfg.collect)
        $debug->log($logEntry);
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

        $logEntries = $this->getCurrentGroupsInit($where);
        /*
            curDepth will fluctuate as we go back through log
            minDepth will decrease as we work our way down/up the groups
        */
        for ($i = \count($logEntries) - 1; $i >= 0; $i--) {
            if ($this->currentInfo['curDepth'] < 1) {
                break;
            }
            $this->getCurrentGroupsPLE($logEntries[$i], $i);
        }
        return $this->currentInfo['logEntries'];
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
        $data = $this->debug->data->get();
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
     * getCurrentGroups: initialize
     * sets `$this->currentInfo`
     * returns logEntries to process
     *
     * @param 'main'|int $where 'main' or summary priority
     *
     * @return LogEntry[]
     */
    private function getCurrentGroupsInit($where)
    {
        $curDepth = 0;
        foreach ($this->groupStacks[$where] as $group) {
            $curDepth += (int) $group['collect'];
        }
        $this->currentInfo = array(
            'curDepth' => $curDepth,
            'minDepth' => $curDepth,
            'logEntries' => array(),
        );
        return $where === 'main'
            ? $this->debug->data->get(array('log'))
            : $this->debug->data->get(array('logSummary', $where));
    }

    /**
     * getCurrentGroups: Process LogEntry
     *
     * @param LogEntry $logEntry LogEntry instance
     * @param int      $index    logEntry index
     *
     * @return void
     */
    private function getCurrentGroupsPLE(LogEntry $logEntry, $index)
    {
        $method = $logEntry['method'];
        if (\in_array($method, array('group', 'groupCollapsed'))) {
            $this->currentInfo['curDepth']--;
        } elseif ($method === 'groupEnd') {
            $this->currentInfo['curDepth']++;
        }
        if ($this->currentInfo['curDepth'] < $this->currentInfo['minDepth']) {
            $this->currentInfo['minDepth']--;
            $this->currentInfo['logEntries'][$index] = $logEntry;
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
        $debug = $logEntry->getSubject();
        $returnValue = $logEntry['args'][0];
        \array_pop($this->groupStacksRef);
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
        $debug = $logEntry->getSubject();
        $priorityClosing = \array_pop($this->groupPriorityStack);
        // not really necessary to remove this empty placeholder, but lets keep things tidy
        unset($this->groupStacks[$priorityClosing]);
        $debug->data->set('logDest', 'auto');
        $logEntry['appendLog'] = false;     // don't actually log
        $logEntry['args'] = array();
        $logEntry['forcePublish'] = true;   // Publish the Debug::EVENT_LOG event (regardless of cfg.collect)
        $logEntry->setMeta('closesSummary', true);
        $debug->log($logEntry);
    }

    /**
     * Are we inside a group?
     *
     * @return int 2: group summary, 1: regular group, 0: not in group
     */
    private function methodGroupEndGet()
    {
        $groupStack = $this->groupStacksRef;
        if ($this->groupPriorityStack && !$groupStack) {
            // we're in top level of group summary
            return 2;
        }
        if ($groupStack && \end($groupStack)['collect'] === $this->debug->getCfg('collect', Debug::CONFIG_DEBUG)) {
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
     * Update groupStack stats durring onOutputCleanup
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
        if (\in_array($method, array('group', 'groupCollapsed'))) {
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
     * Use string representation for group args if available
     *
     * @param LogEntry $logEntry LogEntry instance
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

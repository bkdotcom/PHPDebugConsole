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
use bdk\Debug\LogEntry;

/**
 * Keep track of group nesting
 */
class GroupStack
{
    private $currentInfo = array(
        'curDepth' => 0,
        'minDepth' => 0,
        'logEntries' => array(),
    );

    private $debug;

    /**
     * array of priorities
     * used to return to the previous summary when groupEnd()ing out of a summary
     * this allows calling groupSummary() while in a groupSummary
     *
     * @var int[]
     */
    private $priorityStack = array();

    private $groupStacks = array(
        'main' => array(),  // array('channel' => Debug instance, 'collect' => bool)[]
    );

    private $groupStacksRef = null;  // points to $this->data['groupStacks'][x] (where x = 'main' or (int) priority)

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->groupStacksRef = &$this->groupStacks['main'];
    }

    /**
     * Get group stack for the specified "priority"
     *
     * @param null|'main'|int $priority 'main' or summary priority integer
     *
     * @return array
     */
    public function get($priority = null)
    {
        if ($priority === null) {
            return \array_keys($this->groupStacks);
        }
        return $this->groupStacks[$priority];
    }

    /**
     * Return the group & groupCollapsed ("ancestors")
     *
     * @param 'auto'|'main'|int $where ('auto'), 'main' or summary priority
     *
     * @return LogEntry[] kwys are maintained
     */
    public function getCurrentGroups($where = 'auto')
    {
        if ($where === 'auto') {
            $where = $this->getCurrentPriority();
        }
        $this->getCurrentGroupsInit($where);
        $logEntries = $where === 'main'
            ? $this->debug->data->get(array('log'))
            : $this->debug->data->get(array('logSummary', $where));
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
        $priority = \end($this->priorityStack);
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
        $depth += \count($this->priorityStack);
        return $depth;
    }

    /**
     * Are we inside a group?
     *
     * @return int 2: group summary, 1: regular group, 0: not in group
     */
    public function haveOpenGroup()
    {
        $groupStack = $this->groupStacksRef;
        if ($this->priorityStack && !$groupStack) {
            // we're in top level of group summary
            return 2;
        }
        if ($groupStack && \end($groupStack)['collect'] === $this->debug->getCfg('collect', Debug::CONFIG_DEBUG)) {
            return 1;
        }
        return 0;
    }

    /**
     * Pop current group from stack
     *
     * @return array|int
     */
    public function pop()
    {
        return \array_pop($this->groupStacksRef);
    }

    /**
     * Pop summary prioirty off off summary stack
     *
     * @return int
     */
    public function popPriority()
    {
        $priorityClosing = \array_pop($this->priorityStack);
        // not really necessary to remove this empty placeholder, but lets keep things tidy
        if (empty($this->groupStacks[$priorityClosing])) {
            unset($this->groupStacks[$priorityClosing]);
        }
        return $priorityClosing;
    }

    /**
     * Push group info onto current stack
     *
     * @param Debug $channel Debug instance
     * @param bool  $collect Whether collect is on at time of push
     *
     * @return void
     */
    public function push(Debug $channel, $collect)
    {
        \array_push($this->groupStacksRef, array(
            'channel' => $channel,
            'collect' => $collect,
        ));
    }

    /**
     * Push priority onto priorityStack
     *
     * @param int $priority Priority
     *
     * @return void
     */
    public function pushPriority($priority)
    {
        \array_push($this->priorityStack, $priority);
    }

    /**
     * Clear specified stack
     *
     * @param string|int $where 'main', 'summary', or `int`
     *
     * @return void
     */
    public function reset($where)
    {
        // typeCast to string so that 0 does not match 'main'
        switch ((string) $where) {
            case 'main':
                $this->groupStacks['main'] = array();
                $this->groupStacksRef = &$this->groupStacks['main'];
                return;
            case 'summary':
                $this->priorityStack = array();
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
                break;
            case 'summary':
                $priority = \end($this->priorityStack);
                if (!isset($this->groupStacks[$priority])) {
                    $this->groupStacks[$priority] = array();
                }
                $this->groupStacksRef = &$this->groupStacks[$priority];
                break;
        }
    }

    /**
     * getCurrentGroups: initialize
     * sets `$this->currentInfo`
     *
     * @param 'main'|int $where 'main' or summary priority
     *
     * @return void
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
        if (\in_array($method, array('group', 'groupCollapsed'), true)) {
            $this->currentInfo['curDepth']--;
        } elseif ($method === 'groupEnd') {
            $this->currentInfo['curDepth']++;
        }
        if ($this->currentInfo['curDepth'] < $this->currentInfo['minDepth']) {
            $this->currentInfo['minDepth']--;
            $this->currentInfo['logEntries'][$index] = $logEntry;
        }
    }
}

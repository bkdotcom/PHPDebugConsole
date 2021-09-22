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
use bdk\PubSub\SubscriberInterface;

/**
 * Group methods
 */
class Group implements SubscriberInterface
{

    public $debug;

    /**
     * duplicate/store frequently used cfg vals
     *
     * @var array
     */
    private $cfg = array(
        'collect' => false,
    );

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->cfg['collect'] = $debug->getCfg('collect');
        $debug->eventManager->addSubscriberInterface($this);
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
        $this->debug->setData('groupStacksRef/__push__', array(
            'channel' => $logEntry->getSubject(),
            'collect' => $this->cfg['collect'],
        ));
        if ($this->cfg['collect'] === false) {
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
        $groupStacks = $this->debug->getData(array('groupStacks', $where));
        foreach ($groupStacks as $group) {
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
     * Calculate total group depth
     *
     * @return int
     */
    public function getDepth()
    {
        $depth = 0;
        $groupStacks = $this->debug->getData('groupStacks');
        $priorityStack = $this->debug->getData('groupPriorityStack');
        foreach ($groupStacks as $stack) {
            $depth += \count($stack);
        }
        $depth += \count($priorityStack);
        return $depth;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CONFIG => 'onConfig',
        );
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
        $haveOpenGroup = $this->haveOpenGroup();
        $value = $logEntry['args'][0];
        $logEntry['args'] = array();
        if ($haveOpenGroup === 2) {
            // we're closing a summary group
            $priorityClosing = $debug->getData('groupPriorityStack/__pop__');
            // not really necessary to remove this empty placeholder, but lets keep things tidy
            $debug->setData('groupStacks/' . $priorityClosing, '__unset__');
            $debug->setData('logDest', 'auto');
            $logEntry['appendLog'] = false;     // don't actually log
            $logEntry['forcePublish'] = true;   // Publish the Debug::EVENT_LOG event (regardless of cfg.collect)
            $logEntry->setMeta('closesSummary', true);
            $debug->log($logEntry);
        } elseif ($haveOpenGroup === 1) {
            $debug->getData('groupStacksRef/__pop__');
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
        $debug->setData('groupPriorityStack/__push__', $logEntry['meta']['priority']);
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
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event->getValues();
        if (isset($cfg['debug']['collect'])) {
            $this->cfg['collect'] = $cfg['debug']['collect'];
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
     * Get current group priority
     *
     * @return 'main'|int
     */
    private function getCurrentPriority()
    {
        $priorityStack = $this->debug->getData('groupPriorityStack');
        $priority = \end($priorityStack);
        return $priority !== false
            ? $priority
            : 'main';
    }

    /**
     * Are we inside a group?
     *
     * @return int 2: group summary, 1: regular group, 0: not in group
     */
    private function haveOpenGroup()
    {
        $groupStackWas = $this->debug->getData('groupStacksRef');
        $priorityStack = $this->debug->getData('groupPriorityStack');
        if ($priorityStack && !$groupStackWas) {
            // we're in top level of group summary
            return 2;
        }
        if ($groupStackWas && \end($groupStackWas)['collect'] === $this->cfg['collect']) {
            return 1;
        }
        return 0;
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
}

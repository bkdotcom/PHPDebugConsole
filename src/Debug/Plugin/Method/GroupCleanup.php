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
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\Method\GroupStack;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\PubSub\SubscriberInterface;

/**
 * Group methods
 */
class GroupCleanup implements SubscriberInterface
{
    /** @var GroupStack */
    protected $groupStack;

    /** @var LogEntry[] */
    protected $log = array();

    /** @var array<string,mixed> */
    private $cleanupInfo = array(
        'stack' => array(),
        'stackCount' => 0,
    );

    /** @var Debug|null */
    private $debug;

    /** @var bool */
    private $inShutdown = false;

    /**
     * Constructor
     *
     * @param GroupStack $groupStack Group nesting manager
     */
    public function __construct(GroupStack $groupStack)
    {
        $this->groupStack = $groupStack;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OUTPUT => ['onOutput', PHP_INT_MAX],
            Debug::EVENT_PLUGIN_INIT => 'onPluginInit',
            EventManager::EVENT_PHP_SHUTDOWN => ['onShutdown', PHP_INT_MAX],
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
     * Debug::EVENT_PLUGIN_INIT subscriber
     *
     * @param Event $event Debug::EVENT_PLUGIN_INIT Event instance
     *
     * @return void
     */
    public function onPluginInit(Event $event)
    {
        $this->debug = $event->getSubject();
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
                    'depth' => 0,
                    'groupCount' => 0,
                ),
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
    private function outputCleanupGroup(array $group = array())
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
     * Update groupStack stats during onOutputCleanup / Process LogEntry
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
        if (\in_array($method, ['group', 'groupCollapsed'], true)) {
            $this->cleanupInfo['stack'][] = array(
                'childCount' => 0,  // includes any child groups
                'depth' => $stackCount,
                'groupCount' => 0,
                'index' => $index,
                'indexEnd' => null,
                'meta' => $logEntry['meta'],
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
    private function uncollapseError(LogEntry $logEntry, array $groupStack)
    {
        if ($logEntry->getMeta('uncollapse') === false) {
            return;
        }
        foreach ($groupStack as $logEntry) {
            $logEntry['method'] = 'group';
        }
    }
}

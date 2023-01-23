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

namespace bdk\Debug;

use bdk\Debug;

/**
 * Maintain log data and other runtime info
 */
class Data
{
    private $debug;
    private $arrayUtil;

    protected $data = array(
        'alerts'            => array(), // alert entries.  alerts will be shown at top of output when possible
        'entryCountInitial' => 0,       // store number of log entries created during init
        'headers'           => array(), // headers that need to be output (ie chromeLogger & firePhp)
        'isObCache'         => false,
        'log'               => array(),
        'logSummary'        => array(), // summary log entries grouped by priority
        'outputSent'        => false,
        'requestId'         => '',      // set in bootstrap
        'runtime'           => array(
            // memoryPeakUsage, memoryLimit, & memoryLimit get stored here
        ),
    );

    protected $logRef;          // points to either log or logSummary[priority]

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->arrayUtil = $debug->arrayUtil;
        $this->logRef = &$this->data['log'];
    }

    /**
     * Advanced usage
     *
     * @param string $path path
     *
     * @return mixed
     */
    public function get($path = null)
    {
        if (!$path) {
            $data = $this->arrayUtil->copy($this->data, false);
            $data['logSummary'] = $this->arrayUtil->copy($data['logSummary'], false);
            return $data;
        }
        $data = $this->arrayUtil->pathGet($this->data, $path);
        return \is_array($data) && \in_array($path, array('logSummary'), true)
            ? $this->arrayUtil->copy($data, false)
            : $data;
    }

    /**
     * Advanced usage
     *
     *    setCfg('key', 'value')
     *    setCfg('level1.level2', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string|array $path  path or array of values to merge
     * @param mixed        $value value
     *
     * @return void
     */
    public function set($path, $value = null)
    {
        if ($path === 'logDest') {
            $this->setLogDest($value);
            return;
        }
        if (\is_string($path)) {
            $this->arrayUtil->pathSet($this->data, $path, $value);
        } elseif (\is_array($path)) {
            $this->data = \array_merge($this->data, $path);
        }
        if (!$this->data['log']) {
            $this->debug->methodGroup->reset('main');
        }
        if (!$this->data['logSummary']) {
            $this->debug->methodGroup->reset('summary');
        }
        $this->setLogDest();
    }

    /**
     * Add a log entry to the log
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function appendLog(LogEntry $logEntry)
    {
        $id = $logEntry->getMeta('appendGroup');
        if ($id && $this->appendGroup($logEntry, $id)) {
            return;
        }
        $attribs = $logEntry->getMeta('attribs');
        if (isset($attribs['id'])) {
            $id = $attribs['id'];
            $this->logRef[$id] = $logEntry;
            return;
        }
        $this->logRef[] = $logEntry;
    }

    /**
     * Append log entry to the specified group
     *
     * @param LogEntry   $logEntry LogEntry instance
     * @param int|string $groupId  id/index of group LogEntry
     *
     * @return bool
     */
    private function appendGroup(LogEntry $logEntry, $groupId)
    {
        $where = $this->findLogEntry($groupId);
        if ($where === false) {
            return false;
        }
        $attribs = $logEntry->getMeta('attribs');
        $insertId = isset($attribs['id'])
            ? $attribs['id']
            : 0;
        $insert = array(
            $insertId => $logEntry,
        );
        if (\is_int($where)) {
            $closingId = $this->findGroupEnd($groupId, $this->data['logSummary'][$where]);
            $this->arrayUtil->spliceAssoc($this->data['logSummary'][$where], $closingId, 0, $insert);
            return true;
        }
        $closingId = $this->findGroupEnd($groupId, $this->data[$where]);
        $this->arrayUtil->spliceAssoc($this->data[$where], $closingId, 0, $insert);
        return true;
    }

    /**
     * Find the groupEnd logEntry for the given group open id
     *
     * @param int|string $id         id/index of group LogEntry
     * @param LogEntry[] $logEntries log entries
     *
     * @return int|false
     */
    private function findGroupEnd($id, $logEntries)
    {
        $depth = 0;
        $inGroup = false;
        foreach ($logEntries as $key => $logEntry) {
            $inGroup = $inGroup || $key === $id;
            if ($inGroup === false) {
                continue;
            }
            $method = $logEntry['method'];
            if (\in_array($method, array('group', 'groupCollapsed'), true)) {
                $depth++;
            } elseif ($method === 'groupEnd') {
                $depth--;
            }
            if ($depth === 0) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Search for key'd logEntry in 'log', 'logSummary', & 'alerts'
     *
     * @param string $id logEntry id
     *
     * @return false|int|'log'|'alerts'
     */
    private function findLogEntry($id)
    {
        if (isset($this->data['log'][$id])) {
            return 'log';
        }
        $priorities = \array_keys($this->data['logSummary']);
        foreach ($priorities as $priority) {
            if (isset($this->data['logSummary'][$priority][$id])) {
                return $priority;
            }
        }
        if (isset($this->data['alerts'][$id])) {
            return 'alerts';
        }
        return false;
    }

    /**
     * Set where appendLog appends to
     *
     * @param string $where ('auto'), 'alerts', 'main', 'summary'
     *
     * @return void
     */
    private function setLogDest($where = 'auto')
    {
        $priority = $this->debug->methodGroup->getCurrentPriority();
        if ($where === 'auto') {
            $where = $priority === 'main'
                ? 'main'
                : 'summary';
        }
        switch ($where) {
            case 'alerts':
                $this->logRef = &$this->data['alerts'];
                break;
            case 'main':
                $this->logRef = &$this->data['log'];
                $this->debug->methodGroup->setLogDest('main');
                break;
            case 'summary':
                if (!isset($this->data['logSummary'][$priority])) {
                    $this->data['logSummary'][$priority] = array();
                }
                $this->logRef = &$this->data['logSummary'][$priority];
                $this->debug->methodGroup->setLogDest('summary');
        }
    }
}

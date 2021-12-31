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
        return \is_array($data) && \in_array($path, array('logSummary'))
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
            $this->debug->methodGroup->resetStack('main');
        }
        if (!$this->data['logSummary']) {
            $this->debug->methodGroup->resetStack('summary');
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
        $this->logRef[] = $logEntry;
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

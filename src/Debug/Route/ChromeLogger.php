<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 *
 * @see https://craig.is/writing/chrome-logger/techspecs
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Output log as via ChromeLogger headers
 *
 * ChromeLogger supports the following methods/log-types:
 * log, warn, error, info, group, groupEnd, groupCollapsed, and table
 */
class ChromeLogger extends Base
{

    const HEADER_NAME = 'X-ChromeLogger-Data';

    protected $appendsHeaders = true;

    protected $cfg = array(
        'channels' => array('*'),
        'channelsExclude' => array(
            'events',
            'files',
        ),
        'group' => true, // contain/wrap log in a group?
    );

    protected $consoleMethods = array(
        'assert',
        // 'count',    // output as log
        'error',
        'group',
        'groupCollapsed',
        'groupEnd',
        'info',
        'log',
        'table',
        // 'time',     // output as log
        'timeEnd',  // PHPDebugConsole never generates a timeEnd entry
        'trace',
        'warn',
    );

    /**
     * @var array header data
     */
    protected $jsonData = array(
        'version' => Debug::VERSION,
        'columns' => array('log', 'backtrace', 'type'),
        'rows' => array()
    );

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->dump = $debug->getDump('base');
    }

    /**
     * Output the log as chromelogger headers
     *
     * @param Event $event Debug::EVENT_OUTPUT Event object
     *
     * @return void
     */
    public function processLogEntries(Event $event)
    {
        $this->dump->crateRaw = false;
        $this->data = $this->debug->getData();
        $this->buildJsonData();
        if ($this->jsonData['rows']) {
            $max = $this->getMaxLength();
            $encoded = $this->encode($this->jsonData);
            if ($max) {
                if (\strlen($encoded) > $max) {
                    $this->reduceData($max);
                    $this->buildJsonData();
                    $encoded = $this->encode($this->jsonData);
                }
                if (\strlen($encoded) > $max) {
                    $this->jsonData['rows'] = array(
                        array(
                            array('chromeLogger: unable to abridge log to ' . $this->debug->utility->getBytes($max)),
                            null,
                            'warn',
                        )
                    );
                    $encoded = $this->encode($this->jsonData);
                }
            }
            $event['headers'][] = array(self::HEADER_NAME, $encoded);
        }
        $this->data = array();
        $this->jsonData['rows'] = array();
        $this->dump->crateRaw = true;
    }

    /**
     * {@inheritDoc}
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $this->dump->processLogEntry($logEntry);
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        if ($method === 'assert') {
            \array_unshift($args, false);
        } elseif (!\in_array($method, $this->consoleMethods)) {
            $method = 'log';
        }
        $this->jsonData['rows'][] = array(
            $args,
            isset($meta['file']) ? $meta['file'] . ': ' . $meta['line'] : null,
            $method === 'log' ? '' : $method,
        );
    }

    /**
     * Build Chromelogger JSON
     *
     * @return void
     */
    protected function buildJsonData()
    {
        $this->jsonData['rows'] = array();
        $this->processAlerts();
        $this->processSummary();
        $this->processLog();
        if ($this->jsonData) {
            $request = $this->debug->request;
            $serverParams = $request->getServerParams();
            $info = array('PHP', isset($serverParams['REQUEST_METHOD'])
                ? $serverParams['REQUEST_METHOD'] . ' ' . $this->debug->redact((string) $request->getUri())
                : '$: ' . \implode(' ', $serverParams['argv'])
            );
            if (!$this->cfg['group']) {
                \array_unshift($this->jsonData['rows'], array(
                    $info,
                    null,
                    'info',
                ));
                return;
            }
            \array_unshift($this->jsonData['rows'], array(
                $info,
                null,
                'groupCollapsed',
            ));
            \array_push($this->jsonData['rows'], array(
                array(),
                null,
                'groupEnd',
            ));
        }
    }

    /**
     * Calculate header size
     *
     * @return int
     */
    protected function calcHeaderSize()
    {
        $this->buildJsonData();
        $encoded = $this->encode($this->jsonData);
        return \strlen(self::HEADER_NAME . ': ') + \strlen($encoded);
    }

    /**
     * Encode data for header
     *
     * @param array $data log data
     *
     * @return string encoded data for header
     */
    protected function encode($data)
    {
        $data = \json_encode($data, JSON_UNESCAPED_SLASHES);
        $data = \str_replace(
            array(
                \json_encode(Abstracter::TYPE_FLOAT_INF),
                \json_encode(Abstracter::TYPE_FLOAT_NAN),
                \json_encode(Abstracter::UNDEFINED),
            ),
            array(
                '"INF"',
                '"NaN"',
                'null',
            ),
            $data
        );
        return \base64_encode($data);
    }

    /**
     * Get maximum allowed header length
     *
     * @return int
     */
    protected function getMaxLength()
    {
        $maxVals = \array_filter(array(
            $this->debug->utility->getBytes($this->debug->getCfg('headerMaxAll', Debug::CONFIG_DEBUG), true),
            $this->debug->utility->getBytes($this->debug->getCfg('headerMaxPer', Debug::CONFIG_DEBUG), true),
        ));
        $maxVals[] = 0;
        return \min($maxVals);
    }

    /**
     * Attempt to remove log entries to get header length < max
     *
     * @param int $max maximum header length
     *
     * @return void
     */
    protected function reduceData($max)
    {
        \array_unshift($this->data['alerts'], new LogEntry(
            $this->debug,
            'alert',
            array('Log abridged due to header size constraint'),
            array('level' => 'info')
        ));
        /*
            Remove non-essential summary entries
        */
        $summaryRemove = array(
            '$_COOKIE',
            '$_POST',
            'Built In',
            'ini location',
            'git branch',
            'memory_limit',
            'Peak Memory Usage',
            'PHP Version',
            'php://input',
            'session.cache_limiter',
            'session_save_path',
        );
        $summaryRemoveRegex = '/^(' . \implode('|', \array_map(function ($val) {
            return \preg_quote($val, '/');
        }, $summaryRemove)) . ')/';
        foreach ($this->data['logSummary'] as $priority => $logEntries) {
            foreach ($logEntries as $i => $logEntry) {
                if ($logEntry['args'] && \preg_match($summaryRemoveRegex, $logEntry['args'][0])) {
                    unset($logEntries[$i]);
                }
            }
            $this->data['logSummary'][$priority] = \array_values($logEntries);
        }
        /*
            Remove all log entries sans assert, error, & warn
        */
        $logBack = array();
        foreach ($this->data['log'] as $i => $logEntry) {
            if (!\in_array($logEntry['method'], array('assert','error','warn'))) {
                unset($this->data['log'][$i]);
                $logBack[$i] = $logEntry;
            }
        }
        /*
            Data is now just alerts, summary, and errors
        */
        $strlen = $this->calcHeaderSize();
        $avail = $max - $strlen;
        if ($avail > 2048) {
            // we've got enough room to fill with additional entries
            $this->reduceDataFill($max, $logBack);
        }
    }

    /**
     * Add back log entries until we're out of space
     *
     * @param int   $max     maximum header length
     * @param array $logBack logEntries removed in initial pass
     *
     * @return void
     */
    protected function reduceDataFill($max, $logBack = array())
    {
        $indexes = \array_reverse(\array_keys($logBack));
        $depth = 0;
        $groupOnly = false;
        /*
            work our way backwards through the log until we fill the avail header length
        */
        foreach ($indexes as $i) {
            $logEntry = $logBack[$i];
            $method = $logEntry['method'];
            if ($method === 'groupEnd') {
                $depth++;
            } elseif (\in_array($method, array('group', 'groupCollapsed'))) {
                $depth--;
            } elseif ($groupOnly) {
                continue;
            }
            $this->data['log'][$i] = $logEntry;
            $strlen = $this->calcHeaderSize();
            if ($groupOnly && $depth === 0) {
                break;
            }
            if ($strlen + (40 * $depth) > $max) {
                unset($this->data['log'][$i]);
                $groupOnly = true;
            }
        }
        \ksort($this->data['log']);
        $this->data['log'] = \array_values($this->data['log']);
    }
}

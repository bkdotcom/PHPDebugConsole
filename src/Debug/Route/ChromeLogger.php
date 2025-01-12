<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 *
 * @see https://craig.is/writing/chrome-logger/techspecs
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Output log as via ChromeLogger headers
 *
 * ChromeLogger supports the following methods/log-types:
 * log, warn, error, info, group, groupEnd, groupCollapsed, and table
 */
class ChromeLogger extends AbstractRoute
{
    const HEADER_NAME = 'X-ChromeLogger-Data';

    /** @var bool */
    protected $appendsHeaders = true;

    /** @var array<string,mixed> */
    protected $cfg = array(
        'channels' => ['*'],
        'channelsExclude' => [
            'events',
            'files',
        ],
        'group' => true, // contain/wrap log in a group?
    );

    /** @var list<string> */
    protected $consoleMethods = [
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
    ];

    /** @var int Current group depth  */
    protected $depth = 0;

    /** @var bool Whether we're only collecting groups due to header size limit */
    protected $groupOnly = false;

    /**
     * @var array header data
     */
    // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
    protected $jsonData = array(
        'version' => Debug::VERSION,
        'columns' => ['log', 'backtrace', 'type'],
        'rows' => [],
    );

    /** @var int Maximum header length */
    protected $max = 0;

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->dumper = $debug->getDump('base');
    }

    /**
     * Output the log as chromelogger headers
     *
     * @param Event|null $event Debug::EVENT_OUTPUT Event object
     *
     * @return void
     */
    public function processLogEntries($event = null)
    {
        $this->debug->utility->assertType($event, 'bdk\PubSub\Event');

        $this->dumper->crateRaw = false;
        $this->data = $this->debug->data->get();
        $this->data['log']  = \array_values($this->data['log']);
        $this->buildJsonData();
        $this->max = $this->getMaxLength();
        $encoded = $this->encode($this->jsonData);
        if ($this->max && \strlen($encoded) > $this->max) {
            $this->reduceData();
            $this->buildJsonData();
            $encoded = $this->encode($this->jsonData);
            $encoded = $this->assertEncodedLength($encoded);
        }
        if ($this->jsonData['rows']) {
            $event['headers'][] = [self::HEADER_NAME, $encoded];
        }
        $this->data = array();
        $this->jsonData['rows'] = [];
        $this->dumper->crateRaw = true;
    }

    /**
     * {@inheritDoc}
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $this->dumper->processLogEntry($logEntry);
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        if ($method === 'assert') {
            \array_unshift($args, false);
        } elseif (\in_array($method, $this->consoleMethods, true) === false) {
            $method = 'log';
        }
        $this->jsonData['rows'][] = [
            $args,
            isset($meta['file']) ? $meta['file'] . ': ' . $meta['line'] : null,
            $method === 'log' ? '' : $method,
        ];
    }

    /**
     * Test that our header's length is less than max
     *
     * @param string $encoded ChromeLogger header value
     *
     * @return string header
     */
    private function assertEncodedLength($encoded)
    {
        if (\strlen($encoded) <= $this->max) {
            return $encoded;
        }
        $this->jsonData['rows'] = [
            [
                ['chromeLogger: unable to abridge log to ' . $this->debug->utility->getBytes($this->max)],
                null,
                'warn',
            ],
        ];
        return $this->encode($this->jsonData);
    }

    /**
     * Build Chromelogger JSON
     *
     * @return void
     */
    protected function buildJsonData()
    {
        $this->jsonData['rows'] = [];
        $this->processAlerts();
        $this->processSummary();
        $this->processLog();
        $heading = ['PHP', $this->getRequestMethodUri()];
        if (!$this->cfg['group']) {
            \array_unshift($this->jsonData['rows'], [$heading, null, 'info']);
            return;
        }
        \array_unshift($this->jsonData['rows'], [$heading, null, 'groupCollapsed']);
        \array_push($this->jsonData['rows'], [[], null, 'groupEnd']);
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
        $data = $this->translateJsonValues($data);
        return \base64_encode($data);
    }

    /**
     * Get maximum allowed header length
     *
     * @return int
     */
    protected function getMaxLength()
    {
        $maxVals = \array_filter([
            $this->debug->utility->getBytes($this->debug->getCfg('headerMaxAll', Debug::CONFIG_DEBUG), true),
            $this->debug->utility->getBytes($this->debug->getCfg('headerMaxPer', Debug::CONFIG_DEBUG), true),
        ]);
        return \min($maxVals);
    }

    /**
     * Attempt to remove log entries to get header length < max
     *
     * @return void
     */
    protected function reduceData()
    {
        \array_unshift($this->data['alerts'], new LogEntry(
            $this->debug,
            'alert',
            ['Log abridged due to header size constraint'],
            array(
                'level' => 'info',
            )
        ));
        $this->reduceDataSummary();
        /*
            Remove all log entries sans assert, error, & warn
        */
        $logBack = array();
        foreach ($this->data['log'] as $i => $logEntry) {
            if (\in_array($logEntry['method'], ['assert', 'error', 'warn'], true) === false) {
                unset($this->data['log'][$i]);
                $logBack[$i] = $logEntry;
            }
        }
        /*
            Data is now just alerts, summary, and errors
        */
        $strlen = $this->calcHeaderSize();
        $avail = $this->max - $strlen;
        if ($avail > 128) {
            // we've got enough room to fill with additional entries
            $this->reduceDataFill($logBack);
        }
    }

    /**
     * Abridge the summary
     *
     * @return void
     */
    private function reduceDataSummary()
    {
        /*
            Remove non-essential summary entries
        */
        $summaryRemove = [
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
        ];
        $summaryRemoveRegex = '/^(' . \implode('|', \array_map(static function ($val) {
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
    }

    /**
     * Add back log entries until we're out of space
     *
     * @param array $logBack logEntries removed in initial pass
     *
     * @return void
     */
    protected function reduceDataFill($logBack = array())
    {
        $indexes = \array_reverse(\array_keys($logBack));
        $this->depth = 0;
        $this->groupOnly = false;
        /*
            work our way backwards through the log until we fill the avail header length
        */
        foreach ($indexes as $i) {
            $logEntry = $logBack[$i];
            $continue = $this->reduceDataFillWalk($logEntry, $i);
            if ($continue === false) {
                break;
            }
        }
        \ksort($this->data['log']);
        $this->data['log'] = \array_values($this->data['log']);
    }

    /**
     * Add back log entries until we're out of space
     *
     * @param LogEntry   $logEntry LogEntry instance
     * @param int|string $index    LogEntry index
     *
     * @return bool whether to continue
     */
    private function reduceDataFillWalk(LogEntry $logEntry, $index)
    {
        $method = $logEntry['method'];
        if ($method === 'groupEnd') {
            $this->depth++;
            // https://bugs.xdebug.org/view.php?id=2095
            // phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
        } elseif (in_array($method, ['group', 'groupCollapsed'], true)) {
            $this->depth--;
        } elseif ($this->groupOnly) {
            return true;
        }
        $this->data['log'][$index] = $logEntry;
        $strlen = $this->calcHeaderSize();
        if ($this->groupOnly && $this->depth === 0) {
            return false;
        }
        if ($strlen + (40 * $this->depth) > $this->max) {
            unset($this->data['log'][$index]);
            $this->groupOnly = true;
        }
        return true;
    }

    /**
     * Handle INF, Nan, & "undefined"
     *
     * @param string $json Json string
     *
     * @return string
     */
    protected function translateJsonValues($json)
    {
        return \str_replace(
            [
                \json_encode(Type::TYPE_FLOAT_INF),
                \json_encode(Type::TYPE_FLOAT_NAN),
                \json_encode(Abstracter::UNDEFINED),
            ],
            [
                '"INF"',
                '"NaN"',
                'null',
            ],
            $json
        );
    }
}

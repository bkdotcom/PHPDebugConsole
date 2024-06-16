<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Collector\StatementInfo;
use CLogger;
use CLogRoute;
use Exception;
use Yii;

/**
 * Yii v1.1 log router
 */
class LogRoute extends CLogRoute
{
    /** @var string specify levels handled by route */
    public $levels = 'error, info, profile, trace, warning';

    /** @var Debug */
    private $debug;

    private $levelMap = array(
        CLogger::LEVEL_ERROR => 'error',
        CLogger::LEVEL_INFO => 'log',
        CLogger::LEVEL_PROFILE => 'time',
        CLogger::LEVEL_TRACE => 'trace',
        CLogger::LEVEL_WARNING => 'warn',
    );

    /** @var LogEntryMeta */
    protected $meta;

    /** @var array stack of yii begin-profile log entries */
    private $stack;

    /**
     * @var array $except An array of categories to exclude from logging.
     *                      Regex pattern matching is supported
     *                      We exclude system.db categories... handled via pdo wrapper
     */
    protected $except = array(
        '/^system\.db\./',
    );

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     * @param array $opts  Route options
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(Debug $debug = null, $opts = array())
    {
        if (!$debug) {
            $debug = Debug::getChannel('Yii');
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Yii');
        }
        $this->meta = new LogRouteMeta($debug);
        foreach ($opts as $k => $v) {
            $setter = 'set' . \ucfirst($k);
            if (\method_exists($this, $setter)) {
                $this->{$setter}($v);
                continue;
            }
            $this->{$k} = $v;
        }
        $debug->backtrace->addInternalClass(array(
            'CLogger',
            'CLogRoute',
            'YiiBase',
        ));
        $this->debug = $debug;
    }

    /**
     * Retrieves filtered log messages from logger for further processing.
     *
     * @param CLogger $logger      logger instance
     * @param bool    $processLogs whether to process the logs after they are collected from the logger. ALWAYS TRUE NOW!
     *
     * @return void
     */
    #[\Override]
    public function collectLogs($logger, $processLogs = false)
    {
        $processLogs = true;
        parent::collectLogs($logger, $processLogs);
    }

    /**
     * Get instance of this route
     *
     * @return Yii11LogRoute
     */
    public static function getInstance()
    {
        $routes = Yii::app()->log->routes;  // CMap obj
        foreach ($routes as $route) {
            if ($route instanceof static) {
                return $route;
            }
        }
        $route = new static();
        $route->init();
        $routes['phpDebugConsole'] = $route;
        Yii::app()->log->routes = $routes;
        return $route;
    }

    /**
     * Initialize component
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        // send each entry to debugger immediately
        Yii::getLogger()->autoFlush = 1;
    }

    /**
     * Are we excluding category?
     *
     * @param array $logEntry raw/indexed Yii log entry
     *
     * @return bool
     */
    protected function isExcluded(array $logEntry)
    {
        $category = $logEntry[2];
        if (\strpos($category, 'system.db') === 0 && \preg_match('/^(Opening|Closing)/', $logEntry[0])) {
            return false;
        }
        foreach ($this->except as $except) {
            if ($this->isExcludedTest($except, $category)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Test except string against category
     *
     * @param string $except   category or regex to match against category
     * @param string $category logEntry category
     *
     * @return bool
     */
    private function isExcludedTest($except, $category)
    {
        //  If found, we skip
        if (\trim(\strtolower($except)) === \trim(\strtolower($category))) {
            return true;
        }
        //  Check for regex
        return $except[0] === '/' && \preg_match($except, $category);
    }

    /**
     * Convert Yii's list to key/value'd array
     *
     * @param array $logEntry raw/indexed Yii log entry
     *
     * @return array key=>value
     */
    protected function normalizeMessage(array $logEntry)
    {
        $logEntry = \array_combine(
            array('message', 'level', 'category', 'time'),
            $logEntry
        );
        $logEntry = \array_merge($logEntry, array(
            'channel' => $this->debug,
            'meta' => array(),
            'trace' => array(),
        ));
        $haveTrace = $logEntry['level'] === CLogger::LEVEL_TRACE || (YII_DEBUG && YII_TRACE_LEVEL > 0);
        return $haveTrace
            ? $this->parseTrace($logEntry)
            : $logEntry;
    }

    /**
     * Yii's logger appends trace info to log message as a string
     * extract it and move to 'trace'
     *
     * @param array $logEntry key/valued logEntry
     *
     * @return array
     */
    private function parseTrace(array $logEntry)
    {
        // if YII_DEBUG is on, we may have trace info
        $regex = '#^in (.+) \((\d+)\)$#m';
        $matches = array();
        \preg_match_all($regex, $logEntry['message'], $matches, PREG_SET_ORDER);
        // remove the trace info from the message
        $logEntry['message'] = \rtrim(\preg_replace($regex, '', $logEntry['message']));
        foreach ($matches as $line) {
            $file = $line[1];
            if (\strpos($file, __DIR__) === 0) {
                continue;
            }
            $logEntry['trace'][] = array(
                'file' => $file,
                'line' => (int) $line[2],
            );
        }
        return $logEntry;
    }

    /**
     * Route log messages to PHPDebugConsole
     *
     * Extends CLogRoute
     *
     * @param array $logs list of log messages
     *
     * @return void
     */
    #[\Override]
    protected function processLogs($logs = array())
    {
        try {
            foreach ($logs as $message) {
                if ($this->isExcluded($message)) {
                    continue;
                }
                $this->processLogEntry($message);
            }
            //  Processed, clear!
            $this->logs = null;
        } catch (Exception $e) {
            \trigger_error(__METHOD__ . ': Exception processing application logs: ' . $e->getMessage());
        }
    }

    /**
     * Handle Yii log entry
     *
     * @param array $logEntry our key/value'd log entry
     *
     * @return void
     */
    protected function processLogEntry(array $logEntry)
    {
        $logEntry = $this->normalizeMessage($logEntry);
        $logEntry = $this->meta->messageMeta($logEntry);
        if (\strpos($logEntry['category'], 'system.caching') === 0 && \preg_match('/^(Saving|Serving) "yii:dbquery/', $logEntry['message'])) {
            return $this->processSqlCachingLogEntry($logEntry);
        }
        $method = 'processLogEntry' . \ucfirst($logEntry['level']);
        $method = \method_exists($this, $method)
            ? $method
            : 'processLogEntryDefault';
        $this->{$method}($logEntry);
    }

    /**
     * Convert SQL caching log entry to a statementInfo log entry
     *
     * @param array $logEntry our key/value'd log entry
     *
     * @return void
     */
    private function processSqlCachingLogEntry(array $logEntry)
    {
        // this is an accurate way to get channel for saved to cache... not so much for from cache
        //  we have no connectionString to channel mapping
        $groupId = StatementInfo::lastGroupId();
        $debug = $this->debug->data->get('log.' . $groupId)->getSubject();
        $returnValue = 'saved to cache';

        if (\strpos($logEntry['message'], 'Serving') === 0) {
            $regEx = '/^Serving "yii:dbquery:\S+:\S*:\S+:(.*?)(?::(a:\d+:\{.+\}))?" from cache$/s';
            \preg_match($regEx, $logEntry['message'], $matches);
            $statementInfo = new StatementInfo($matches[1], $matches[2] ? \unserialize($matches[2]) : array());
            $statementInfo->appendLog($debug);
            $groupId = StatementInfo::lastGroupId();
            $returnValue = 'from cache';
        }

        $debug->log(new LogEntry(
            $debug,
            'groupEndValue',
            array($returnValue),
            array(
                'appendGroup' => $groupId,
                'icon' => 'fa fa-cube',
                'level' => 'info',
            )
        ));
    }

    /**
     * Process Yii log entry
     *
     * @param array $logEntry our key/value'd log entry
     *
     * @return void
     */
    private function processLogEntryDefault(array $logEntry)
    {
        $debug = $logEntry['channel'];
        $method = $this->levelMap[$logEntry['level']];
        $args = \array_filter(array(
            \ltrim($logEntry['category'] . ':', ':'),
            $logEntry['message'],
        ));
        if ($logEntry['meta']) {
            $args[] = $debug->meta($logEntry['meta']);
        }
        \call_user_func_array(array($debug, $method), $args);
    }

    /**
     * Handle Yii profile log entry
     *
     * @param array $logEntry our key/value'd log entry
     *
     * @return void
     */
    private function processLogEntryProfile(array $logEntry)
    {
        if (\strpos($logEntry['message'], 'begin:') === 0) {
            // add to stack
            $logEntry['message'] = \substr($logEntry['message'], 6);
            $this->stack[] = $logEntry;
            return;
        }
        $logEntryBegin = \array_pop($this->stack);
        $message = $logEntryBegin['category']
            ? $logEntryBegin['category'] . ': ' . $logEntryBegin['message']
            : $logEntryBegin['message'];
        $duration = $logEntry['time'] - $logEntryBegin['time'];
        $debug = $logEntry['channel'];
        $method = $this->levelMap[$logEntry['level']];
        $args = array($message, $duration);
        \call_user_func_array(array($debug, $method), $args);
    }

    /**
     * Handle Yii trace log entry
     *
     * @param array $logEntry our key/value'd log entry
     *
     * @return void
     */
    private function processLogEntryTrace(array $logEntry)
    {
        if (\count($logEntry['trace']) <= 1) {
            $logEntry['level'] = CLogger::LEVEL_INFO;
            return $this->processLogEntryDefault($logEntry);
        }

        $caption = $logEntry['category']
            ? $logEntry['category'] . ': ' . $logEntry['message']
            : $logEntry['message'];
        $logEntry['meta']['columns'] = array('file', 'line');
        $logEntry['meta']['trace'] = $logEntry['trace'];
        $debug = $logEntry['channel'];
        $method = $this->levelMap[$logEntry['level']];
        $args = array(false, $caption, $debug->meta($logEntry['meta']));
        \call_user_func_array(array($debug, $method), $args);
    }
}

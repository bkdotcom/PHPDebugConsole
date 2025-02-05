<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use bdk\Debug\Collector\StatementInfo;
use bdk\Debug\Collector\StatementInfoLogger;
use bdk\Debug\LogEntry;
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

    /** @var array<non-empty-string,string> */
    private $levelMap = array(
        CLogger::LEVEL_ERROR => 'error',
        CLogger::LEVEL_INFO => 'log',
        CLogger::LEVEL_PROFILE => 'time',
        CLogger::LEVEL_TRACE => 'trace',
        CLogger::LEVEL_WARNING => 'warn',
    );

    /** @var LogEntryMeta */
    protected $meta;

    /** @var array<string,bool> */
    private $messageHashes = array();

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
     * @param Debug|null $debug Debug instance
     * @param array      $opts  Route options
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($debug = null, $opts = array())
    {
        \bdk\Debug\Utility::assertType($debug, 'bdk\Debug');

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
    #[\Override]
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
        $level = $logEntry[1];
        if (\strpos($category, 'system.db.') === 0 && \preg_match('/^(Opening|Closing)/', $logEntry[0])) {
            return false;
        }
        if ($category === 'application' && $level === CLogger::LEVEL_TRACE && \preg_match('/^(Begin|Commit|Rollback) transaction/', $logEntry[0])) {
            // we will log these via our PDO collector
            return true;
        }
        if ($level === CLogger::LEVEL_WARNING) {
            $hash = \md5($logEntry[0]);
            if (isset($this->messageHashes[$hash])) {
                // we've already logged this warning
                return true;
            }
            $this->messageHashes[$hash] = true;
        }
        return $this->isExcludedTest($category);
    }

    /**
     * Test except string against category
     *
     * @param string $category logEntry category
     *
     * @return bool
     */
    private function isExcludedTest($category)
    {
        $category = \trim(\strtolower($category));
        $isMatch = false;
        foreach ($this->except as $except) {
            if (\trim(\strtolower($except)) === $category) {
                $isMatch = true;
            } elseif ($except[0] === '/' && \preg_match($except, $category)) {
                $isMatch = true;
            }
        }
        return $isMatch;
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
        $keys = ['message', 'level', 'category', 'time'];
        return \array_merge(array(
            'channel' => $this->debug,
            'meta' => array(),
        ), \array_combine($keys, $logEntry));
    }

    /**
     * Route log messages to PHPDebugConsole
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
            $this->logs = array();
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
        $handled = false;
        if (\strpos((string) $logEntry['category'], 'system.caching') === 0 && \preg_match('/^(Saving|Serving) "yii:dbquery/', $logEntry['message'])) {
            $handled = $this->processSqlCachingLogEntry($logEntry);
        }
        if ($handled) {
            return;
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
     * @return bool
     */
    private function processSqlCachingLogEntry(array $logEntry)
    {
        $returnValue = 'saved to cache';
        if (\strpos($logEntry['message'], 'Serving') === 0) {
            $this->processSqlCachingLogEntryServe($logEntry);
            $returnValue = 'from cache';
        }

        $groupId = StatementInfoLogger::lastGroupId();
        $groupLogEntry = $this->debug->data->get('log.' . $groupId);
        if (empty($groupLogEntry)) {
            // collect is/was off?
            return true;
        }

        $debug = $groupLogEntry->getSubject();
        $debug->log(new LogEntry(
            $debug,
            'groupEndValue',
            array($this->debug->abstracter->crateWithVals($returnValue, array(
                'attribs' => array('class' => 'badge bg-info fw-bold'),
            ))),
            array(
                'appendGroup' => $groupId,
                'icon' => ':cache:',
                'level' => 'info',
            )
        ));

        return true;
    }

    /**
     * If we have a "Serving" log entry, process it as a statementInfo log entry
     *
     * @param array $logEntry our key/value'd log entry
     *
     * @return void
     */
    private function processSqlCachingLogEntryServe(array $logEntry)
    {
        $regEx = '/^Serving\ "
            yii:dbquery:[^:]+:
            (?P<connectionString>\S+:\S+):
            (?P<userName>\S+):
            (?P<sql>.*?)
            (?::(?P<params>a:\d+:\{.*\}))?
            "\ from\ cache$/sx';
        \preg_match($regEx, $logEntry['message'], $matches);
        $statementInfo = new StatementInfo(
            $matches['sql'],
            $matches['params'] ? \unserialize($matches['params']) : array()
        );
        $pdo = Yii::app()->phpDebugConsole->pdoCollector->getInstance($matches['connectionString']);
        $pdo->getStatementInfoLogger()->log($statementInfo, array(
            'attribs' => array('class' => 'logentry-muted'),
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
        $args = \array_filter([
            \ltrim($logEntry['category'] . ':', ':'),
            $logEntry['message'],
        ]);
        if ($logEntry['meta']) {
            if (!empty($logEntry['meta']['trace'])) {
                $logEntry['meta']['columns'] = ['file', 'line'];
            }
            $args[] = $debug->meta($logEntry['meta']);
        }
        \call_user_func_array([$debug, $method], $args);
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
        $debug = $logEntry['channel'];
        $method = $this->levelMap[$logEntry['level']];
        $logEntryBegin = \array_pop($this->stack);
        $message = $logEntryBegin['category']
            ? $logEntryBegin['category'] . ': ' . $logEntryBegin['message']
            : $logEntryBegin['message'];
        $duration = $logEntry['time'] - $logEntryBegin['time'];
        $args = [$message, $duration];
        \call_user_func_array([$debug, $method], $args);
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
        if (empty($logEntry['meta']['trace'])) {
            $logEntry['level'] = CLogger::LEVEL_INFO;
            $this->processLogEntryDefault($logEntry);
            return;
        }
        $debug = $logEntry['channel'];
        $method = $this->levelMap[$logEntry['level']];
        $caption = $logEntry['category']
            ? $logEntry['category'] . ': ' . $logEntry['message']
            : $logEntry['message'];
        $logEntry['meta']['columns'] = ['file', 'line'];
        $args = [false, $caption, $debug->meta($logEntry['meta'])];
        \call_user_func_array([$debug, $method], $args);
    }
}

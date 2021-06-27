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

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use CLogger;
use CLogRoute;
use Exception;
use Yii;

/**
 * Yii v1.1 log router
 */
class LogRoute extends CLogRoute
{
    public $levels = 'error, info, profile, trace, warning';

    private $debug;

    private $levelMap = array(
        CLogger::LEVEL_INFO => 'log',
        CLogger::LEVEL_WARNING => 'warn',
        CLogger::LEVEL_ERROR => 'error',
        CLogger::LEVEL_TRACE => 'trace',
        CLogger::LEVEL_PROFILE => 'time',
    );

    /**
     * @var array stack of yii begin-profile log entries
     */
    private $stack;

    /**
     * @var array $except An array of categories to exclude from logging.
     *                                  Regex pattern matching is supported
     *                                  We exclude system.db categories... handled via pdo wrapper
     */
    protected $except = array(
        '/^system\.db\./'
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
            $debug = Debug::_getChannel('Yii');
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Yii');
        }
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
     * Extends CLogRoute
     *
     * @param CLogger $logger      logger instance
     * @param bool    $processLogs whether to process the logs after they are collected from the logger. ALWAYS TRUE NOW!
     *
     * @return void
     */
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
     * Find backtrace frame that called log()
     *
     * @return array backtrace frame or empty array
     */
    private function getCallerInfo()
    {
        $callerInfo = array();
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 13);
        foreach ($backtrace as $i => $frame) {
            $method = $frame['class'] . '::' . $frame['function'];
            if (!\in_array($method, array('CLogger::log', 'YiiBase::log'))) {
                continue;
            }
            $callerInfo = $frame;
            // check if log called by some other wrapper method
            if (\in_array($backtrace[$i + 1]['function'], array('log','error','warn','warning'))) {
                $callerInfo = $backtrace[$i + 1];
            }
        }
        return $callerInfo;
    }

    /**
     * Are we excluding category?
     *
     * @param string $category log category
     *
     * @return bool
     */
    protected function isExcluded($category)
    {
        foreach ($this->except as $exceptCat) {
            //  If found, we skip
            if (\trim(\strtolower($exceptCat)) === \trim(\strtolower($category))) {
                return true;
            }
            //  Check for regex
            if ($exceptCat[0] === '/' && \preg_match($exceptCat, $category)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Yii's logger appends trace info to log message as a string
     *
     * @param array $logEntry raw/indexed Yii log entry
     *
     * @return array key=>value
     */
    protected function normalizeMessage(array $logEntry)
    {
        $logEntry = \array_combine(
            array('message','level','category','time'),
            $logEntry
        );
        $logEntry = \array_merge($logEntry, array(
            'channel' => $this->debug,
            'meta' => array(),
            'trace' => array(),
        ));
        $haveTrace = $logEntry['level'] === CLogger::LEVEL_TRACE || YII_DEBUG && YII_TRACE_LEVEL > 0;
        if ($haveTrace === false) {
            return $logEntry;
        }
        // if YII_DEBUG is on, we may have trace info
        $regex = '#^in (.+) \((\d+)\)$#m';
        \preg_match_all($regex, $logEntry['message'], $matches, PREG_SET_ORDER);
        // remove the trace info from the message
        $logEntry['message'] = \rtrim(\preg_replace($regex, '', $logEntry['message']));
        foreach ($matches as $line) {
            $logEntry['trace'][] = array(
                'file' => $line[1],
                'line' => $line[2] * 1,
            );
        }
        if (!$logEntry['trace']) {
            $logEntry['level'] = CLogger::LEVEL_INFO;
        }
        return $logEntry;
    }

    /**
     * Determine channel
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     */
    protected function messageMeta(array $logEntry)
    {

        if ($logEntry['trace']) {
            $logEntry['meta']['file'] = $logEntry['trace'][0]['file'];
            $logEntry['meta']['line'] = $logEntry['trace'][0]['line'];
            if ($logEntry['level'] === CLogger::LEVEL_ERROR) {
                $logEntry['meta']['trace'] = $logEntry['trace'];
                unset($logEntry['trace']);
            }
        }

        if (\in_array($logEntry['level'], array(CLogger::LEVEL_ERROR, CLogger::LEVEL_WARNING))) {
            $callerInfo = $this->getCallerInfo();
            if ($callerInfo) {
                $logEntry['meta']['file'] = $callerInfo['file'];
                $logEntry['meta']['line'] = $callerInfo['line'];
            }
        }

        if (\strpos($logEntry['category'], 'system.') === 0) {
            if (\strpos($logEntry['category'], 'system.caching.') === 0) {
                $category = \str_replace('system.caching.', '', $logEntry['category']);
                $icon = 'fa fa-cube';
                $logEntry['category'] = $category;
                $logEntry['channel'] = $this->debug->getChannel($category, array(
                    'channelIcon' => $icon,
                ));
                $logEntry['message'] = \preg_replace('# (to|from) cache$#', '', $logEntry['message']);
                $logEntry['meta']['icon'] = $icon;
                return $logEntry;
            }
            if ($logEntry['category'] === 'system.CModule') {
                $icon = 'fa fa-puzzle-piece';
                $logEntry['channel'] = $this->debug->getChannel('CModule', array(
                    'channelIcon' => $icon,
                    'channelShow' => false,
                ));
                $logEntry['meta']['icon'] = $icon;
                return $logEntry;
            }
            $icon = 'fa fa-cogs';
            $logEntry['channel'] = $this->debug->getChannel('system misc', array(
                'channelIcon' => $icon,
            ));
            $logEntry['meta']['icon'] = $icon;
            return $logEntry;
        }
        if ($logEntry['category'] === 'application') {
            $logEntry['category'] = null;
            $logEntry['channel'] = $this->debug->getChannel('app');
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
    protected function processLogs($logs = array())
    {
        try {
            foreach ($logs as $message) {
                if ($this->isExcluded($message[2])) {
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
        $logEntry = $this->messageMeta($logEntry);
        $args = array();
        $debug = $logEntry['channel'];
        $method = $this->levelMap[$logEntry['level']];
        if ($logEntry['level'] === CLogger::LEVEL_PROFILE) {
            if (\strpos($logEntry['message'], 'begin:') === 0) {
                // add to stack
                $this->stack[] = $logEntry;
                return;
            }
            $logEntryBegin = \array_pop($this->stack);
            $message = $logEntryBegin['category']
                ? $logEntryBegin['category'] . ': ' . $logEntryBegin['message']
                : $logEntryBegin['message'];
            $duration = $logEntry['time'] - $logEntryBegin['time'];
            $args = array($message, $duration);
        }
        if ($logEntry['level'] === CLogger::LEVEL_TRACE) {
            $caption = $logEntry['category']
                ? $logEntry['category'] . ': ' . $logEntry['message']
                : $logEntry['message'];
            $logEntry['meta']['columns'] = array('file','line');
            $logEntry['meta']['trace'] = $logEntry['trace'];
            $args = array(false, $caption);
        }
        if (empty($args)) {
            if ($logEntry['category']) {
                $args[] = $logEntry['category'] . ':';
            }
            $args[] = $logEntry['message'];
        }
        if ($logEntry['meta']) {
            $args[] = $debug->meta($logEntry['meta']);
        }
        \call_user_func_array(array($debug, $method), $args);
    }
}

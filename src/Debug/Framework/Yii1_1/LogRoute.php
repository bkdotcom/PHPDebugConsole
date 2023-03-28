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
            if (\in_array($method, array('CLogger::log', 'YiiBase::log'), true) === false) {
                continue;
            }
            $callerInfo = $frame;
            // check if log called by some other wrapper method
            if (\in_array($backtrace[$i + 1]['function'], array('log','error','warn','warning'), true)) {
                $callerInfo = $backtrace[$i + 1];
            }
            break;
        }
        return $callerInfo;
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
     * Convert Yii's list to key/value'd array
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
        return $this->parseTrace($logEntry);
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
        $logEntry = $this->messageMetaTrace($logEntry);
        $logEntry = $this->messageMetaCaller($logEntry);
        $categoryFuncs = array(
            'application' => 'messageMetaApplication',
            'system.CModule' => 'messageMetaSystemCmodule',
            '/^system\\.caching/' => 'messageMetaSystemCaching',
            '/^system\\./' => 'messageMetaSystem',
        );
        foreach ($categoryFuncs as $match => $method) {
            $isMatch = $match[0] === '/'
                ? \preg_match($match, $logEntry['category'])
                : $match === $logEntry['category'];
            if ($isMatch) {
                return $this->{$method}($logEntry);
            }
        }
        return $logEntry;
    }

    /**
     * Add file & line meta to error and warning
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     */
    private function messageMetaCaller(array $logEntry)
    {
        if (\in_array($logEntry['level'], array(CLogger::LEVEL_ERROR, CLogger::LEVEL_WARNING), true) === false) {
            return $logEntry;
        }
        if (\array_intersect_key($logEntry['meta'], \array_flip(array('file','line')))) {
            return $logEntry;
        }
        $callerInfo = $this->getCallerInfo();
        if ($callerInfo) {
            $logEntry['meta']['file'] = $callerInfo['file'];
            $logEntry['meta']['line'] = $callerInfo['line'];
        }
        return $logEntry;
    }

    /**
     * Handle category: application
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaApplication(array $logEntry)
    {
        $logEntry['category'] = null;
        $logEntry['channel'] = $this->debug->getChannel('app');
        return $logEntry;
    }

    /**
     * Handle category: system.xxx
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaSystem(array $logEntry)
    {
        $channelName = 'system misc';
        $icon = 'fa fa-cogs';
        $logEntry['channel'] = $this->debug->getChannel($channelName, array(
            'icon' => $icon,
        ));
        $logEntry['meta']['icon'] = $icon;
        return $logEntry;
    }

    /**
     * Handle category: system.caching
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaSystemCaching(array $logEntry)
    {
        $channelName = \str_replace('system.caching.', '', $logEntry['category']);
        $icon = 'fa fa-cube';
        $logEntry['category'] = $channelName;
        $logEntry['channel'] = $this->debug->getChannel($channelName, array(
            'channelIcon' => $icon,
            'channelShow' => false,
        ));
        $logEntry['message'] = \preg_replace('# (to|from) cache$#', '', $logEntry['message']);
        $logEntry['meta']['icon'] = $icon;
        $logEntry['trace'] = array();
        if ($logEntry['level'] === CLogger::LEVEL_TRACE) {
            $logEntry['level'] = CLogger::LEVEL_INFO;
        }
        return $logEntry;
    }

    /**
     * Handle category: system.CModule
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function messageMetaSystemCmodule(array $logEntry)
    {
        $channelName = 'CModule';
        $icon = 'fa fa-puzzle-piece';
        $logEntry['channel'] = $this->debug->getChannel($channelName, array(
            'channelIcon' => $icon,
            'channelShow' => false,
        ));
        $logEntry['meta']['icon'] = $icon;
        return $logEntry;
    }

    /**
     * If trace is pressent, set file & line meta
     * If CLogger::LEVEL_ERROR, move trace to meta
     *
     * @param array $logEntry key/valued Yii log entry
     *
     * @return array
     */
    private function messageMetaTrace(array $logEntry)
    {
        if ($logEntry['trace']) {
            $logEntry['meta']['file'] = $logEntry['trace'][0]['file'];
            $logEntry['meta']['line'] = $logEntry['trace'][0]['line'];
            if ($logEntry['level'] === CLogger::LEVEL_ERROR) {
                $logEntry['meta']['trace'] = $logEntry['trace'];
                unset($logEntry['trace']);
            }
        }
        return $logEntry;
    }

    /**
     * Yii's logger appends trace info to log message as a string
     * extract it and move to 'trace'
     *
     * @param array $logEntry key/valued logentry
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
        $logEntry = $this->messageMeta($logEntry);
        if ($logEntry['level'] === CLogger::LEVEL_PROFILE) {
            $this->processLogEntryProfile($logEntry);
            return;
        }
        if ($logEntry['level'] === CLogger::LEVEL_TRACE) {
            if (\count($logEntry['trace']) > 1) {
                $this->processLogEntryTrace($logEntry);
                return;
            }
            $logEntry['level'] = CLogger::LEVEL_INFO;
        }
        $args = array();
        $debug = $logEntry['channel'];
        if ($logEntry['category']) {
            $args[] = $logEntry['category'] . ':';
        }
        $args[] = $logEntry['message'];
        if ($logEntry['meta']) {
            $args[] = $debug->meta($logEntry['meta']);
        }
        $method = $this->levelMap[$logEntry['level']];
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
        $caption = $logEntry['category']
            ? $logEntry['category'] . ': ' . $logEntry['message']
            : $logEntry['message'];
        $logEntry['meta']['columns'] = array('file','line');
        $logEntry['meta']['trace'] = $logEntry['trace'];
        $debug = $logEntry['channel'];
        $method = $this->levelMap[$logEntry['level']];
        $args = array(false, $caption, $debug->meta($logEntry['meta']));
        \call_user_func_array(array($debug, $method), $args);
    }
}

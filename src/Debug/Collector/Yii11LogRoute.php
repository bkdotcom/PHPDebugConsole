<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use Yii;
use CLogger;
use CLogRoute;
use Exception;
use bdk\Debug;
use bdk\Debug\LogEntry;

/**
 * Yii v1.1 log router
 */
class Yii11LogRoute extends CLogRoute
{

    private $debug;

    /**
     * @var array stack of yii begin-profile log entries
     */
    private $stack;

    public $levels = 'error, info, profile, trace, warning';

    /**
     * @var array $excludeCategories An array of categories to exclude from logging.
     *                                  Regex pattern matching is supported
     *                                  We exclude system.db categories... handled via pdo wrapper
     */
    protected $excludeCategories = array();

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     * @param array $opts  Route options
     */
    public function __construct(Debug $debug = null, $opts = array())
    {
        if (!$debug) {
            $debug = \bdk\Debug::_getChannel('Yii');
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Yii');
        }
        foreach ($opts as $k => $v) {
            $setter = 'set'.\ucfirst($k);
            if (\method_exists($this, $setter)) {
                $this->{$setter}($v);
            } else {
                $this->{$k} = $v;
            }
        }
        $this->debug = $debug;
    }

    /**
     * Retrieves filtered log messages from logger for further processing.
     *
     * Extends CLogRoute
     *
     * @param CLogger $logger      logger instance
     * @param boolean $processLogs whether to process the logs after they are collected from the logger. ALWAYS TRUE NOW!
     *
     * @return void
     */
    public function collectLogs($logger, $processLogs = false)
    {
        $processLogs = true;
        parent::collectLogs($logger, $processLogs);
    }

    /**
     * Initialize component
     *
     * @return void
     */
    public function init()
    {
        $this->setExcludeCategories(array());
        parent::init();
        // send each entry to debugger immediately
        Yii::getLogger()->autoFlush = 1;
    }

    /**
     * Enable/Disable route
     *
     * If route isn't currently one of the log routes, it will be added
     *
     * @param boolean $enable enable/disable this route
     *
     * @return void
     */
    public static function toggle($enable = true)
    {
        $route = self::getInstance();
        $route->enabled = $enable;
    }

    /**
     * Yii's logger appends trace info to log message as a string
     *
     * @param array $logEntry key/value'd Yii log entry
     *
     * @return array
     */
    protected function buildLogEntry($logEntry = array())
    {
        $logEntry = \array_combine(
            array('message','level','category','time'),
            $logEntry
        );
        $logEntry['trace'] = array();
        $logEntry['file'] = null;
        $logEntry['line'] = null;
        if ($logEntry['level'] == CLogger::LEVEL_TRACE || YII_DEBUG && YII_TRACE_LEVEL>0) {
            // if YII_DEBUG is on, we may have trace info
            $regex = '#^in (.+) \((\d+)\)$#m';
            \preg_match_all($regex, $logEntry['message'], $matches, PREG_SET_ORDER);
            $logEntry['message'] = \rtrim(\preg_replace($regex, '', $logEntry['message']));
            foreach ($matches as $line) {
                $logEntry['trace'][] = array(
                    'file' => $line[1],
                    'line' => $line[2] * 1,
                );
            }
        }
        if (!$logEntry['trace'] && \in_array($logEntry['level'], array(CLogger::LEVEL_ERROR, CLogger::LEVEL_WARNING))) {
            $backtrace = $this->debug->errorHandler->backtrace();
            foreach ($backtrace as $i => $frame) {
                if (\strpos($frame['file'], YII_PATH) === false && $frame['file'] !== __FILE__) {
                    $logEntry['trace'] = \array_slice($backtrace, $i);
                    break;
                }
            }
        }
        if ($logEntry['trace']) {
            $logEntry['file'] = $logEntry['trace'][0]['file'];
            $logEntry['line'] = $logEntry['trace'][0]['line'];
        }
        return $logEntry;
    }

    /**
     * Getter method
     *
     * @return array
     */
    protected function getExcludeCategories()
    {
        return $this->excludeCategories;
    }

    /**
     * Get instance of this route
     *
     * @return [type] [description]
     */
    protected static function getInstance()
    {
        $routes = Yii::app()->log->routes;  // CMap obj
        foreach ($routes as $route) {
            if ($route instanceof static) {
                return $route;
            }
        }
        $route = new static(Debug::getInstance());
        $route->init();
        $routes[] = $route;
        Yii::app()->log->routes = $routes;
        return $route;
    }

    /**
     * Are we excluding category?
     *
     * @param string $category log category
     *
     * @return boolean
     */
    protected function isExcluded($category)
    {
        foreach ($this->excludeCategories as $excludedCat) {
            //  If found, we skip
            if (\trim(\strtolower($excludedCat)) == \trim(\strtolower($category))) {
                return true;
            }
            //  Check for regex
            if ('/' == $excludedCat[0] && \preg_match($excludedCat, $category)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Yii log type/level to PHPDebugConsole method
     *
     * @param string $type error, info, profile, trace, warning
     *
     * @return string
     */
    protected function levelToMethod($type)
    {
        $method = 'log';
        if ($type == CLogger::LEVEL_INFO) {
            $method = 'info';
        } elseif ($type == CLogger::LEVEL_ERROR) {
            $method = 'error';
        } elseif ($type == CLogger::LEVEL_WARNING) {
            $method = 'warn';
        }
        return $method;
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
            foreach ($logs as $logEntry) {
                if ($this->isExcluded($logEntry[2])) {
                    continue;
                }
                $logEntry = $this->buildLogEntry($logEntry);
                $this->processLogEntry($logEntry);
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
    protected function processLogEntry($logEntry)
    {
        $debug = $this->debug;
        if (\strpos($logEntry['category'], 'system.caching.') === 0) {
            $logEntry['category'] = \str_replace('system.caching.', '', $logEntry['category']);
            $logEntry['message'] = \preg_replace('# (to|from) cache$#', '', $logEntry['message']);
            $debug = \bdk\Debug::_getChannel('cache');
        }
        if ($logEntry['level'] == CLogger::LEVEL_TRACE) {
            $debug->log(new LogEntry(
                $debug,
                'trace',
                array(
                    $logEntry['trace'],
                ),
                array(
                    'caption' => $logEntry['category'].': '.$logEntry['message'],
                    'columns' => array('file','line')
                )
            ));
            return;
        }
        if ($logEntry['level'] == CLogger::LEVEL_PROFILE) {
            if (\strpos($logEntry['message'], 'begin:') === 0) {
                // add to stack
                $logEntry['message'] = \substr($logEntry['message'], 6);
                $this->stack[] = $logEntry;
            } else {
                $begin = \array_pop($this->stack);
                $duration = $logEntry['time'] - $begin['time'];
                $debug->time($begin['category'].': '.$begin['message'], $duration);
            }
            return;
        }
        $method = $this->levelToMethod($logEntry['level']);
        $args = array(
            $logEntry['category'].':',
            $logEntry['message']
        );
        if (\in_array($method, array('error','warn'))) {
            $args[] = $debug->meta(array(
                'file' => $logEntry['file'],
                'line' => $logEntry['line'],
            ));
            if ($method == 'warn') {
                $args[] = $debug->meta('backtrace', $logEntry['trace']);
            }
        }
        \call_user_func_array(array($debug, $method), $args);
    }

    /**
     * Setter method
     *
     * @param array $value array of categories
     *
     * @return void
     */
    protected function setExcludeCategories($value)
    {
        $this->excludeCategories = $value;
        $this->excludeCategories[] = '/^system\.db/';
    }
}

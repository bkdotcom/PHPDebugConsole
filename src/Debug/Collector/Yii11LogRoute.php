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
            /*
            LogEntry
                0: message
                1: level
                2: category
                3: time
            */
            foreach ($logs as $logEntry) {
                $logEntry = \array_combine(
                    array('message','level','category','time'),
                    $logEntry
                );
                if ($this->isExcluded($logEntry['category'])) {
                    continue;
                }

                if ($logEntry['level'] == CLogger::LEVEL_TRACE) {
                    $regex = '#^in (.+) \((\d+)\)$#m';
                    \preg_match_all($regex, $logEntry['message'], $matches, PREG_SET_ORDER);
                    $title = \rtrim(\preg_replace($regex, '', $logEntry['message']));
                    $trace = array();
                    foreach ($matches as $line) {
                        $trace[] = array(
                            'file' => $line[1],
                            'line' => $line[2] * 1,
                        );
                    }
                    $this->debug->log(new LogEntry(
                        $this->debug,
                        'trace',
                        array(
                            $trace,
                        ),
                        array(
                            'caption' => $logEntry['category'].': '.$title,
                            'columns' => array('file','line')
                        )
                    ));
                    continue;
                }
                if ($logEntry['level'] == CLogger::LEVEL_PROFILE) {
                    if (\strpos($logEntry['message'], 'begin:') === 0) {
                        // add to stack
                        $logEntry['message'] = \substr($logEntry['message'], 6);
                        $this->stack[] = $logEntry;
                    } else {
                        $begin = \array_pop($this->stack);
                        $duration = $logEntry['time'] - $begin['time'];
                        $this->debug->time($begin['category'].': '.$begin['message'], $duration);
                    }
                    continue;
                }
                $method = $this->typeToDebugMethod($logEntry['level']);
                \call_user_func_array(
                    array($this->debug, $method),
                    array(
                        $logEntry['category'].':',
                        $logEntry['message']
                    )
                );
            }
            //  Processed, clear!
            $this->logs = null;
        } catch (Exception $e) {
            \trigger_error(__METHOD__ . ': Exception processing application logs: ' . $e->getMessage());
        }
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
    protected function typeToDebugMethod($type)
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
}

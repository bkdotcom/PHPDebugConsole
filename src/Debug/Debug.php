<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.1.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk;

use bdk\Debug\ErrorHandler;
use bdk\PubSub\SubscriberInterface;
use bdk\PubSub\Manager as EventManager;
use ReflectionClass;
use ReflectionMethod;

/**
 * Web-browser/javascript like console class for PHP
 *
 * @property Abstracter   $abstracter   lazy-loaded abstracter obj
 * @property ErrorEmailer $errorEmailer lazy-loaded errorEmailer obj
 * @property Output       $output       lazy-loaded output obj
 * @property Utf8         $utf8         lazy-loaded utf8 obj
 */
class Debug
{

    private static $instance;
    private static $publicMethods = array();
    protected $cfg = array();
    protected $data = array();
    protected $groupDepthRef;   // points to groupDepth or groupSummaryStack[x]['groupDepth']
    protected $logRef;          // points to either log or logSummary
    protected $config;          // config instance
    public $errorHandler;
    public $eventManager;
    public $internal;
    public $utilities;

    const META = "\x00meta\x00";
    const VERSION = "2.1.0";
    const PRIORITY_HIGH = PHP_INT_MAX;
    const PRIORITY_LOW = -PHP_INT_MAX;

    /**
     * Constructor
     *
     * @param array        $cfg          config
     * @param EventManager $eventManager optional - specify EventManager instance
     *                                      will use new instance if not specified
     * @param ErrorHandler $errorHandler optional - specify ErrorHandler instance
     *                                      if not specified, will use singleton or new instance
     */
    public function __construct($cfg = array(), EventManager $eventManager = null, ErrorHandler $errorHandler = null)
    {
        $this->cfg = array(
            'collect'   => false,
            'file'      => null,            // if a filepath, will receive log data
            'key'       => null,
            'output'    => false,           // output the log?
            // which error types appear as "error" in debug console... all other errors are "warn"
            'errorMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR
                            | E_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR,
            'emailFunc' => 'mail',          // callable
            'emailLog'  => false,   // Whether to email a debug log.  (requires 'collect' to also be true)
                                    //
                                    //   false:   email will not be sent
                                    //   true or 'onError':   email will be sent (if log is not output)
                                    //   'always':  email sent regardless of whether error occured or log output
            'emailTo'   => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'logEnvInfo' => true,
            'logServerKeys' => array('REQUEST_URI','REQUEST_TIME','HTTP_HOST','SERVER_NAME','SERVER_ADDR','REMOTE_ADDR'),
            'onLog' => null,
        );
        $this->data = array(
            'alerts'            => array(), // array of alerts.  alerts will be shown at top of output when possible
            'counts'            => array(), // count method
            'entryCountInitial' => 0,   // store number of log entries created during init
            'groupDepth'        => array(0, 0),     // 1st: ignores cfg['collect'], 2nd: when cfg['collect']
            'groupSummaryStack' => array(
                /*
                    this stack is used to return to the previous summary when groupEnd()ing out of a summary
                    this allows calling groupSummary() while in a groupSummary
                    when opening a groupSummary, we start with a new groupDepth (1, x)

                    array( 'groupDepth' => array(x,x), 'priority' => x ),
                */
            ),
            'log'               => array(),
            'logSummary'        => array(), // summary log entries subgrouped by priority
            'outputSent'        => false,
            'requestId'         => null,
            'runtime'           => array(),
            'timers' => array(      // timer method
                'labels' => array(
                    // label => array(accumulatedTime, lastStartedTime|null)
                    'debugInit' => array(
                        0,
                        isset($_SERVER['REQUEST_TIME_FLOAT'])
                            ? $_SERVER['REQUEST_TIME_FLOAT']
                            : microtime(true)
                    ),
                ),
                'stack' => array(),
            ),
        );
        if (!isset(self::$instance)) {
            /*
               self::getInstance() will always return initial/first instance
            */
            self::$instance = $this;
            /*
                Only call spl_autoload_register on initial instance
                (even though re-registering function does't re-register)
            */
            spl_autoload_register(array($this, 'autoloader'));
        }
        /*
            Initialize child objects
            (abstracter, errorEmailer, output, & utf8 are lazyloaded)
        */
        $this->eventManager = $eventManager
            ? $eventManager
            : new EventManager();
        if ($errorHandler) {
            $this->errorHandler = $errorHandler;
        } elseif (ErrorHandler::getInstance()) {
            $this->errorHandler = ErrorHandler::getInstance();
        } else {
            $this->errorHandler = new ErrorHandler($this->eventManager);
        }
        $this->utilities = new Debug\Utilities();
        $this->config = new Debug\Config($this, $this->cfg);
        $this->internal = new Debug\Internal($this);
        /*
            Init config and properties
        */
        $this->config->setCfg($cfg);
        $this->data['requestId'] = $this->utilities->requestId();
        $this->groupDepthRef = &$this->data['groupDepth'];
        $this->logRef = &$this->data['log'];
        self::setPublicMethods();
        /*
            Publish bootstrap event
        */
        $this->eventManager->publish('debug.bootstrap', $this);
        $this->data['entryCountInitial'] = count($this->data['log']);
        return;
    }

    /**
     * Magic method... inaccessible method called.
     *
     * Treat as a custom method
     *
     * @param string $methodName Inaccessible method name
     * @param array  $args       Arguments passed to method
     *
     * @return void
     */
    public function __call($methodName, $args)
    {
        $this->appendLog(
            $methodName,
            $args,
            array('isCustomMethod' => true)
        );
    }

    /**
     * Magic method to allow us to call instance methods statically
     *
     * Prefix the instance method with an underscore ie
     *    \bdk\Debug::_log('logged via static method');
     *
     * @param string $methodName Inaccessible method name
     * @param array  $args       Arguments passed to method
     *
     * @return mixed
     */
    public static function __callStatic($methodName, $args)
    {
        $methodName = ltrim($methodName, '_');
        if (in_array($methodName, self::$publicMethods)) {
            return call_user_func_array(array(self::$instance, $methodName), $args);
        }
        if (empty(self::$publicMethods)) {
            /*
                Initializing debug with \bdk\Debug::_setCfg()?
            */
            self::setPublicMethods();
            return self::__callStatic($methodName, $args);
        }
        self::$instance->appendLog(
            $methodName,
            $args,
            array('isCustomMethod' => true)
        );
    }

    /**
     * Magic method to get inaccessible / undefined properties
     * Lazy load child classes
     *
     * @param string $property property name
     *
     * @return property value
     */
    public function __get($property)
    {
        switch ($property) {
            case 'abstracter':
                $val = new Debug\Abstracter($this->eventManager, $this->config->getCfgLazy('abstracter'));
                break;
            case 'errorEmailer':
                $val = new Debug\ErrorEmailer($this->config->getCfgLazy('errorEmailer'));
                break;
            case 'output':
                $val = new Debug\Output($this, $this->config->getCfgLazy('output'));
                break;
            case 'utf8':
                $val = new Debug\Utf8();
                break;
            case 'groupDepth':
                // calculate the total group depth
                $depth = $this->data['groupDepth'][0];
                foreach ($this->data['groupSummaryStack'] as $group) {
                    $depth += $group['groupDepth'][0];
                }
                return $depth;
            default:
                return null;
        }
        if ($val) {
            $this->{$property} = $val;
        }
        return $val;
    }

    /*
        Debugging Methods
    */

    /**
     * Add an alert to top of log
     *
     * @param string  $message     message
     * @param string  $class       (danger), info, success, warning
     * @param boolean $dismissible (false)
     *
     * @return void
     */
    public function alert($message, $class = 'danger', $dismissible = false)
    {
        $this->appendLog(
            'alert',
            array($message),
            array(
                'class' => $class,
                'dismissible' => $dismissible,
            )
        );
    }

    /**
     * Log a message and stack trace to console if first argument is false.
     *
     * Only appends log when assertation fails
     *
     * @return void
     */
    public function assert()
    {
        $args = func_get_args();
        $test = array_shift($args);
        if (!$test) {
            if (!$args) {
                $callerInfo = $this->utilities->getCallerInfo();
                $args[] = 'assertation failed in '.$callerInfo['file'].' on line '.$callerInfo['line'];
            }
            $this->appendLog('assert', $args);
        }
    }

    /**
     * Log the number of times this has been called with the given label.
     *
     * If `label` is omitted, logs the number of times `count()` has been called at this particular line.
     *
     * @param mixed $label label
     *
     * @return integer The count
     */
    public function count($label = null)
    {
        if (isset($label)) {
            $dataLabel = $label;
        } else {
            // determine calling file & line
            $label = 'count';
            $callerInfo = $this->utilities->getCallerInfo();
            $dataLabel = $callerInfo['file'].': '.$callerInfo['line'];
        }
        if (!isset($this->data['counts'][$dataLabel])) {
            $this->data['counts'][$dataLabel] = 1;
        } else {
            $this->data['counts'][$dataLabel]++;
        }
        $count = $this->data['counts'][$dataLabel];
        $this->appendLog('count', array(
            $label,
            $count,
        ));
        return $count;
    }

    /**
     * Log an error message.
     *
     * @param mixed $label,... label
     *
     * @return void
     */
    public function error()
    {
        $this->appendLog(
            'error',
            func_get_args(),
            $this->internal->getErrorCaller()
        );
    }

    /**
     * Create a new inline group
     *
     * @return void
     */
    public function group()
    {
        $this->doGroup('group', func_get_args());
    }

    /**
     * Create a new inline group
     *
     * @return void
     */
    public function groupCollapsed()
    {
        $this->doGroup('groupCollapsed', func_get_args());
    }

    /**
     * Close current group
     *
     * @return void
     */
    public function groupEnd()
    {
        $args = func_get_args();
        $meta = array();
        $this->groupDepthRef[0] = max(0, --$this->groupDepthRef[0]);
        if ($this->cfg['collect']) {
            if ($this->groupDepthRef[1] === 0) {
                // nothing to end
                return;
            }
            $this->groupDepthRef[1]--;
        }
        $errorCaller = $this->errorHandler->get('errorCaller');
        if ($errorCaller && isset($errorCaller['groupDepth']) && $this->groupDepth < $errorCaller['groupDepth']) {
            $this->errorHandler->setErrorCaller(false);
        }
        if ($this->data['groupSummaryStack'] && $this->groupDepthRef[0] === 0) {
            array_pop($this->data['groupSummaryStack']);
            $count = count($this->data['groupSummaryStack']);
            if ($count) {
                // still in a group
                $curPriority = $this->data['groupSummaryStack'][$count-1]['priority'];
                $this->logRef = &$this->data['logSummary'][$curPriority];
                $this->groupDepthRef = &$this->data['groupSummaryStack'][$count-1]['groupDepth'];
            } else {
                // we've popped out of all the summary groups
                $this->logRef = &$this->data['log'];
                $this->groupDepthRef = &$this->data['groupDepth'];
            }
            $meta['closesSummary'] = true;
            /*
                Publish the debug.log event (regardless of cfg.collect)
                don't actually log
            */
            $this->eventManager->publish(
                'debug.log',
                $this,
                array(
                    'method' => 'groupEnd',
                    'args' => array(),
                    'meta' => $meta,
                )
            );
        } else {
            $this->appendLog('groupEnd', $args, $meta);
        }
    }

    /**
     * Initiate the beginning of "summary" log entries
     *
     * Debug methods called while a groupSummary is open will appear at the top of the log
     * call groupEnd() to close summary
     *
     * groupSummary can be used multiple times
     * All groupSummary groups will appear together in a single group
     *
     * @param integer $priority (0) The higher the priority, the ealier it will appear.
     *
     * @return void
     */
    public function groupSummary($priority = 0)
    {
        $this->data['groupSummaryStack'][] = array(
            'groupDepth' => array(1, $this->cfg['collect'] ? 1 : 0),
            'priority' => $priority
        );
        $stackCount = count($this->data['groupSummaryStack']);
        if (!isset($this->data['logSummary'][$priority])) {
            $this->data['logSummary'][$priority] = array();
        }
        $this->logRef = &$this->data['logSummary'][$priority];
        $this->groupDepthRef = &$this->data['groupSummaryStack'][$stackCount-1]['groupDepth'];
        /*
            Publish the debug.log event (regardless of cfg.collect)
            don't actually log
        */
        $this->eventManager->publish(
            'debug.log',
            $this,
            array(
                'method' => 'groupSummary',
                'args' => array(),
                'meta' => array(
                    'priority' => $priority,
                ),
            )
        );
    }

    /**
     * Set ancestor groups to uncollapsed
     *
     * This will only occur if `cfg['collect']` is currently true
     *
     * @return void
     */
    public function groupUncollapse()
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $curDepth = $this->groupDepthRef[1];   // will fluctuate as we go through log
        $minDepth = $this->groupDepthRef[1];   // decrease as we work our way down
        for ($i = count($this->logRef) - 1; $i >=0; $i--) {
            if ($curDepth < 1) {
                break;
            }
            $method = $this->logRef[$i][0];
            if (in_array($method, array('group', 'groupCollapsed'))) {
                $curDepth--;
                if ($curDepth < $minDepth) {
                    $minDepth--;
                    $this->logRef[$i][0] = 'group';
                }
            } elseif ($method == 'groupEnd') {
                $curDepth++;
            }
        }
        /*
            Publish the debug.log event (regardless of cfg.collect)
            don't actually log
        */
        $this->eventManager->publish(
            'debug.log',
            $this,
            array(
                'method' => 'groupUncollapse',
                'args' => array(),
                'meta' => array(),
            )
        );
    }

    /**
     * Log some informative information
     *
     * @return void
     */
    public function info()
    {
        $this->appendLog('info', func_get_args());
    }

    /**
     * Log general information
     *
     * @return void
     */
    public function log()
    {
        $this->appendLog('log', func_get_args());
    }

    /**
     * Output array as a table
     *
     * Accepts array of arrays or array of objects
     *
     * Arguments:
     *   1st encountered array is the data
     *   2nd encountered array (optional) specifies columns to output
     *   1st encountered string is a label/caption
     *
     * @return void
     */
    public function table()
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $args = func_get_args();
        $meta = array_merge(array(
            'caption' => null,
            'columns' => array(),
        ), $this->internal->getMetaVals($args));
        $argCount = count($args);
        $data = null;
        for ($i = 0; $i < $argCount; $i++) {
            if (is_array($args[$i])) {
                if ($data === null) {
                    $data = $args[$i];
                } elseif (empty($meta['columns'])) {
                    $meta['columns'] = $args[$i];
                }
            } elseif (is_string($args[$i]) && !$meta['caption']) {
                $meta['caption'] = $args[$i];
            }
            unset($args[$i]);
        }
        if ($data) {
            $this->appendLog('table', array($data), $meta);
        } else {
            $args = is_array($data) && $meta['caption']
                ? array($meta['caption'], $data)    // empty array()
                : func_get_args();                  // no array passed
            $this->appendLog('log', $args);
        }
    }

    /**
     * Start a timer identified by label
     *
     * Label passed
     *    if doesn't exist: starts timer
     *    if does exist: unpauses (does not reset)
     * Label not passed
     *    timer will be added to a no-label stack
     *
     * Does not append log.  Use timeEnd or timeGet to get time
     *
     * @param string $label unique label
     *
     * @return void
     */
    public function time($label = null)
    {
        if (isset($label)) {
            $timers = &$this->data['timers']['labels'];
            if (!isset($timers[$label])) {
                // new label
                $timers[$label] = array(0, microtime(true));
            } elseif (!isset($timers[$label][1])) {
                // no microtime -> the timer is currently paused -> unpause
                $timers[$label][1] = microtime(true);
            }
        } else {
            $this->data['timers']['stack'][] = microtime(true);
        }
        return;
    }

    /**
     * Behaves like a stopwatch.. returns running time
     *    If label is passed, timer is "paused"
     *    If label is not passed, timer is removed from timer stack
     *
     * @param string         $label            unique label
     * @param string|boolean $returnOrTemplate string: "%label: %time"
     *                                         boolean:  If true, only return time, rather than log it
     * @param integer        $precision        rounding precision (pass null for no rounding)
     *
     * @return float|string (numeric)
     */
    public function timeEnd($label = null, $returnOrTemplate = false, $precision = 4)
    {
        if (is_bool($label) || strpos($label, '%time') !== false) {
            $returnOrTemplate = $label;
            $label = null;
        }
        $ret = $this->timeGet($label, true, null); // get non-rounded running time
        if (isset($label)) {
            if (isset($this->data['timers']['labels'][$label])) {
                $this->data['timers']['labels'][$label] = array(
                    $ret,  // store the new "running" time
                    null,  // "pause" the timer
                );
            }
        } else {
            $label = 'time';
            array_pop($this->data['timers']['stack']);
        }
        if (is_int($precision)) {
            // use number_format rather than round(), which may still run decimals-a-plenty
            $ret = number_format($ret, $precision, '.', '');
        }
        $this->timeLog($ret, $returnOrTemplate, $label);
        return $ret;
    }

    /**
     * Get the running time without stopping/pausing the timer
     *
     * @param string         $label            (optional) unique label
     * @param string|boolean $returnOrTemplate string: "%label: %time"
     *                                         boolean:  If true, only return time, rather than log it
     * @param integer        $precision        rounding precision (pass null for no rounding)
     *
     * @return float|string (numeric)
     */
    public function timeGet($label = null, $returnOrTemplate = false, $precision = 4)
    {
        if (is_bool($label) || strpos($label, '%time') !== false) {
            $precision = $returnOrTemplate;
            $returnOrTemplate = $label;
            $label = null;
        }
        $microT = 0;
        $ellapsed = 0;
        if (!isset($label)) {
            $label = 'time';
            if (empty($this->data['timers']['stack'])) {
                list($ellapsed, $microT) = $this->data['timers']['labels']['debugInit'];
            } else {
                $microT = end($this->data['timers']['stack']);
            }
        } elseif (isset($this->data['timers']['labels'][$label])) {
            list($ellapsed, $microT) = $this->data['timers']['labels'][$label];
        }
        if ($microT) {
            $ellapsed += microtime(true) - $microT;
        }
        if (is_int($precision)) {
            // use number_format rather than round(), which may still run decimals-a-plenty
            $ellapsed = number_format($ellapsed, $precision, '.', '');
        }
        $this->timeLog($ellapsed, $returnOrTemplate, $label);
        return $ellapsed;
    }

    /**
     * Log a stack trace
     *
     * @return void
     */
    public function trace()
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $backtrace = $this->errorHandler->backtrace();
        // toss "internal" frames
        for ($i = 1, $count=count($backtrace)-1; $i < $count; $i++) {
            $frame = $backtrace[$i];
            $function = isset($frame['function']) ? $frame['function'] : '';
            if (!preg_match('/^'.preg_quote(__CLASS__).'(::|->)/', $function)) {
                break;
            }
        }
        $backtrace = array_slice($backtrace, $i-1);
        // keep the calling file & line, but toss ->trace or ::_trace
        unset($backtrace[0]['function']);
        $this->appendLog('trace', array($backtrace));
    }

    /**
     * Log a warning
     *
     * @return void
     */
    public function warn()
    {
        $this->appendLog(
            'warn',
            func_get_args(),
            $this->internal->getErrorCaller()
        );
    }

    /*
        "Non-Console" Methods
    */

    /**
     * Extend debug with a plugin
     *
     * @param SubscriberInterface $plugin object implementing SubscriberInterface
     *
     * @return void
     */
    public function addPlugin(SubscriberInterface $plugin)
    {
        $this->eventManager->addSubscriberInterface($plugin);
    }

    /**
     * Retrieve a configuration value
     *
     * @param string $path what to get
     *
     * @return mixed
     */
    public function getCfg($path = null)
    {
        return $this->config->getCfg($path);
    }

    /**
     * Advanced usage
     *
     * @param string $path path
     *
     * @return mixed
     */
    public function getData($path = null)
    {
        if ($path == 'entryCount') {
            return count($this->data['log']);
        }
        $path = array_filter(preg_split('#[\./]#', $path), 'strlen');
        $ret = $this->data;
        foreach ($path as $k) {
            if (isset($ret[$k])) {
                $ret = $ret[$k];
            } else {
                $ret = null;
                break;
            }
        }
        return $ret;
    }

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @param array $cfg optional config
     *
     * @return object
     */
    public static function getInstance($cfg = array())
    {
        if (!isset(self::$instance)) {
            // self::$instance set in __construct
            new static($cfg);
        } elseif ($cfg) {
            self::$instance->setCfg($cfg);
        }
        return self::$instance;
    }

    /**
     * "metafy" value/values
     *
     * @param array|string $values value or values
     *
     * @return array
     */
    public function meta($values = array())
    {
        if (!is_array($values)) {
            $values = array($values=>true);
        }
        $values['debug'] = self::META;
        return $values;
    }

    /**
     * Publishes debug.output event and returns result
     *
     * @return string|null
     */
    public function output()
    {
        if (!$this->cfg['output']) {
            return null;
        }
        $outputAs = $this->output->getCfg('outputAs');
        $this->output->setCfg('outputAs', $outputAs);
        $this->closeOpenGroups();
        $return = $this->eventManager->publish(
            'debug.output',
            $this,
            array('return'=>'')
        )['return'];
        $this->data['alerts'] = array();
        $this->data['counts'] = array();
        $this->data['groupDepth'][0] = 0;
        $this->data['log'] = array();
        $this->data['logSummary'] = array();
        $this->data['outputSent'] = true;
        return $return;
    }

    /**
     * Set one or more config values
     *
     * If setting a value via method a or b, old value is returned
     *
     * Setting/updating 'key' will also set 'collect' and 'output'
     *
     *    setCfg('key', 'value')
     *    setCfg('level1.level2', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string|array $path   path
     * @param mixed        $newVal value
     *
     * @return mixed
     */
    public function setCfg($path, $newVal = null)
    {
        return $this->config->setCfg($path, $newVal);
    }

    /**
     * Advanced usage
     *
     *    setCfg('key', 'value')
     *    setCfg('level1.level2', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string|array $path  path
     * @param mixed        $value value
     *
     * @return void
     */
    public function setData($path, $value = null)
    {
        if (is_string($path)) {
            $path = preg_split('#[\./]#', $path);
            $ref = &$this->data;
            foreach ($path as $k) {
                $ref = &$ref[$k];
            }
            $ref = $value;
        } else {
            $this->data = array_merge($this->data, $path);
        }
    }

    /**
     * A wrapper for errorHandler->setErrorCaller
     *
     * @param array $caller (optional) null (default) determine automatically
     *                      empty value (false, "", 0, array()) clear
     *                      array manually set
     *
     * @return void
     */
    public function setErrorCaller($caller = null)
    {
        if ($caller === null) {
            $caller = $this->utilities->getCallerInfo(1);
            $caller = array(
                'file' => $caller['file'],
                'line' => $caller['line'],
            );
        }
        if (!empty($caller)) {
            // groupEnd will check depth and potentially clear errorCaller
            $caller['groupDepth'] = $this->groupDepth;
        }
        $this->errorHandler->setErrorCaller($caller);
    }

    /*
        Non-Public methods
    */

    /**
     * Debug class autoloader
     *
     * @param string $className classname to attempt to load
     *
     * @return void
     */
    protected function autoloader($className)
    {
        $className = ltrim($className, '\\'); // leading backslash _shouldn't_ have been passed
        if (preg_match('/^(.*?)\\\\([^\\\\]+)$/', $className, $matches)) {
            $namespace = $matches[1];
            if ($namespace === 'bdk\\Debug') {
                $filePath = __DIR__.'/'.$matches[2].'.php';
                require $filePath;
            }
            if ($namespace === 'bdk\\PubSub') {
                $filePath = __DIR__.'/../PubSub/'.$matches[2].'.php';
                require $filePath;
            }
        }
    }

    /**
     * Store the arguments
     * will be output when output method is called
     *
     * @param string $method error, info, log, warn, etc
     * @param array  $args   arguments passed to method
     * @param array  $meta   meta data
     *
     * @return void
     */
    protected function appendLog($method, $args = array(), $meta = array())
    {
        if (!$this->cfg['collect']) {
            return;
        }
        if ($method == 'table') {
            $args[0] = $this->abstracter->getAbstractionTable($args[0]);
        } else {
            $meta = array_merge($meta, $this->internal->getMetaVals($args));
            foreach ($args as $i => $v) {
                if ($this->abstracter->needsAbstraction($v)) {
                    $args[$i] = $this->abstracter->getAbstraction($v);
                }
            }
        }
        $event = $this->eventManager->publish(
            'debug.log',
            $this,
            array(
                'method' => $method,
                'args' => $args,
                'meta' => $meta,
            )
        );
        if ($event->isPropagationStopped()) {
            return;
        }
        if ($method == 'alert') {
            $this->data['alerts'][] = array(
                $event->getValue('args')[0],
                $event->getValue('meta')
            );
        } else {
            $this->logRef[] = array(
                $event->getValue('method'),
                $event->getValue('args'),
                $event->getValue('meta')
            );
        }
        return;
    }

    /**
     * Close any unclosed groups
     *
     * We may have forgotten to end a group or the script may have exited
     *
     * @return void
     */
    protected function closeOpenGroups()
    {
        foreach ($this->data['groupSummaryStack'] as $i => $group) {
            for ($i = 0; $i < $group['groupDepth'][1]; $i++) {
                $this->data['logSummary'][$group['priority']][] = array('groupEnd', array(), array());
            }
            unset($this->data['groupSummaryStack'][$i]);
        }
        while ($this->data['groupDepth'][1] > 0) {
            $this->data['groupDepth'][1]--;
            $this->data['log'][] = array('groupEnd', array(), array());
        }
    }

    /**
     * Append group or groupCollapsed to log
     *
     * @param string $method 'group' or 'groupCollapsed'
     * @param array  $args   arguments passed to group or groupCollapsed
     *
     * @return void
     */
    private function doGroup($method, $args)
    {
        $this->groupDepthRef[0]++;
        if (!$this->cfg['collect']) {
            return;
        }
        $this->groupDepthRef[1]++;
        /*
            Extract/remove meta so we can check if args are empty after extracting
        */
        $meta = $this->internal->getMetaVals($args);
        if (empty($args)) {
            // give a default label
            $caller = $this->utilities->getCallerInfo();
            if (isset($caller['class'])) {
                $args[] = $caller['class'].$caller['type'].$caller['function'];
                $meta['isMethodName'] = true;
            } elseif (isset($caller['function'])) {
                $args[] = $caller['function'];
            } else {
                $args[] = 'group';
            }
        }
        $this->appendLog($method, $args, $meta);
    }

    /**
     * Set/cache this class' public methods
     *
     * Generated list is used when calling methods statically
     *
     * @return void
     */
    private static function setPublicMethods()
    {
        $reflection = new ReflectionClass(get_called_class());
        self::$publicMethods = array_map(function (ReflectionMethod $refMethod) {
            return $refMethod->name;
        }, $reflection->getMethods(ReflectionMethod::IS_PUBLIC));
        self::$publicMethods = array_diff(self::$publicMethods, array(
            '__construct',
            '__call',
            '__callStatic',
            '__get',
        ));
    }

    /**
     * Log time
     *
     * @param float  $seconds          seconds
     * @param mixed  $returnOrTemplate false: log the time with default template (default)
     *                                  true: do not log
     *                                  string: log using passed template
     * @param string $label            label
     *
     * @return void
     */
    protected function timeLog($seconds, $returnOrTemplate = false, $label = 'time')
    {
        if (is_string($returnOrTemplate)) {
            $str = $returnOrTemplate;
            $str = str_replace('%label', $label, $str);
            $str = str_replace('%time', $seconds, $str);
        } elseif ($returnOrTemplate === true) {
            return;
        } else {
            $str = $label.': '.$seconds.' sec';
        }
        $this->appendLog('time', array($str));
    }
}

<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk;

use bdk\Debug\ErrorHandler;
use bdk\PubSub\SubscriberInterface;
use bdk\PubSub\Manager as EventManager;

/**
 * Web-browser/javascript like console class for PHP
 */
class Debug
{

    private static $instance;
    private static $publicMethods = array();
    protected $cfg = array();
    protected $data = array();
    protected $groupDepthRef;   // points to groupDepth or groupDepthSummary
    protected $logRef;          // points to either log or logSummary
    protected $config;          // config instance
    public $abstracter;
    public $errorHandler;
    public $internal;
    public $output;
    public $utf8;
    public $utilities;
    public $eventManager;

    const META = "\x00meta\x00";
    const VERSION = "1.4.0";

    /**
     * Constructor
     *
     * @param array        $cfg          config
     * @param ErrorHandler $errorHandler optional - uses \bdk\PubSub\Manager if not passed
     * @param EventManager $eventManager optional - uses \bdk\Debug\EventManager if not passed
     */
    public function __construct($cfg = array(), ErrorHandler $errorHandler = null, EventManager $eventManager = null)
    {
        $this->cfg = array(
            'collect'   => false,
            'file'      => null,            // if a filepath, will receive log data
            'key'       => null,
            'output'    => false,           // output the log?
            // errorMask = errors that appear as "error" in debug console... all other errors are "warn"
            'errorMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR
                            | E_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR,
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
            'emailFunc' => 'mail',          // callable
            // 'onLog' => null,
        );
        spl_autoload_register(array($this, 'autoloader'));
        $this->utilities = new Debug\Utilities();
        $this->eventManager = $eventManager
            ? $eventManager
            : new EventManager();
        if ($errorHandler) {
            $this->errorHandler = $errorHandler;
        } elseif (Debug\ErrorHandler::getInstance()) {
            $this->errorHandler = Debug\ErrorHandler::getInstance();
        } else {
            $this->errorHandler = new Debug\ErrorHandler($this->eventManager);
        }
        $this->data = array(
            'alerts'        => array(), // array of alerts.  alerts will be shown at top of output when possible
            'counts'        => array(), // count method
            'entryCountInitial' => 0,   // store number of log entries created during init
            'groupDepth'    => 0,
            'groupDepthSummary' => 0,
            'log'           => array(),
            'logSummary'    => array(),
            'outputSent'    => false,
            'requestId'     => $this->utilities->requestId(),
            'timers' => array(      // timer method
                'labels' => array(
                    // label => array(accumulatedTime, lastStartedTime|null)
                    'debugInit' => array(
                        0,
                        isset($_SERVER['REQUEST_TIME_FLOAT']) // php 5.4
                            ? $_SERVER['REQUEST_TIME_FLOAT']
                            : microtime(true)
                    ),
                ),
                'stack' => array(),
            ),
        );
        // Initialize self::$instance if not set
        //    so that self::getInstance() will always return original instance
        //    as opposed the the last instance created with new Debug()
        if (!isset(self::$instance)) {
            self::$instance = $this;
        }
        $this->setPublicMethods();
        $this->logRef = &$this->data['log'];
        $this->groupDepthRef = &$this->data['groupDepth'];
        $this->abstracter = new Debug\Abstracter($this->eventManager);
        $this->config = new Debug\Config($this, $this->cfg);
        $this->errorEmailer = new Debug\ErrorEmailer();
        $this->internal = new Debug\Internal($this);
        $this->output = new Debug\Output($this);
        $this->utf8 = new Debug\Utf8();
        $this->config->setCfg($cfg);
        $this->errorHandler->eventManager->subscribe('errorHandler.error', array($this->errorEmailer, 'onErrorAddEmailData'), 1);
        $this->errorHandler->eventManager->subscribe('errorHandler.error', array($this->errorEmailer, 'onErrorEmail'), -1);
        $this->eventManager->publish('debug.bootstrap', $this);
        $this->data['entryCountInitial'] = count($this->data['log']);
        return;
    }

    /**
     * Magic method to allow us to call instance methods statically
     *
     * Prefix the method with an underscore ie
     *    \bdk\Debug::_log('logged via static method');
     *
     * @param string $methodName Inaccessible method name
     * @param array  $args       Arguments passed to method
     *
     * @return mixed
     */
    public static function __callStatic($methodName, $args)
    {
        $instance = self::getInstance();
        $methodName = ltrim($methodName, '_');
        if (in_array($methodName, self::$publicMethods)) {
            return call_user_func_array(array($instance, $methodName), $args);
        }
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
        if ($this->cfg['collect']) {
            $this->appendLog('alert', array(
                'message' => $message,
                'class' => $class,
                'dismissible' => $dismissible,
            ));
        }
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
        if ($this->cfg['collect']) {
            $args = func_get_args();
            $test = array_shift($args);
            if (!$test) {
                $this->appendLog('assert', $args);
            }
        }
    }

    /**
     * Log the number of times this has been called with the given label.
     *
     * @param mixed $label label
     *
     * @return integer
     */
    public function count($label = null)
    {
        $return = 0;
        if ($this->cfg['collect']) {
            $args = array();
            if (isset($label)) {
                $args[] = $label;
            } else {
                // determine calling file & line
                $args[] = 'count';
                $backtrace = version_compare(PHP_VERSION, '5.4.0', '>=')
                    ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)
                    : debug_backtrace(false);   // don't provide object
                $label = $backtrace[0]['file'].': '.$backtrace[0]['line'];
            }
            if (!isset($this->data['counts'][$label])) {
                $this->data['counts'][$label] = 1;
            } else {
                $this->data['counts'][$label]++;
            }
            $args[] = $this->data['counts'][$label];
            $this->appendLog('count', $args);
            $return = $this->data['counts'][$label];
        }
        return $return;
    }

    /**
     * Outputs an error message.
     *
     * @param mixed $label,... label
     *
     * @return void
     */
    public function error()
    {
        if ($this->cfg['collect']) {
            $this->appendLog(
                'error',
                func_get_args(),
                $this->internal->getErrorCaller()
            );
        }
    }

    /**
     * Creates a new inline group
     *
     * @return void
     */
    public function group()
    {
        $this->groupDepthRef++;
        if ($this->cfg['collect']) {
            $args = func_get_args();
            $meta = array();
            foreach ($args as $i => $v) {
                if (is_array($v) && array_search(self::META, $v, true) !== false) {
                    $meta = array_merge($meta, $v);
                    unset($args[$i]);
                }
            }
            $args = array_values($args);
            if (empty($args)) {
                // give a default label
                $args[] = 'group';
            }
            $this->appendLog('group', $args, $meta);
        }
    }

    /**
     * Creates a new inline group
     *
     * @return void
     */
    public function groupCollapsed()
    {
        $this->groupDepthRef++;
        if ($this->cfg['collect']) {
            $args = func_get_args();
            $meta = array();
            foreach ($args as $i => $v) {
                if (is_array($v) && array_search(self::META, $v, true) !== false) {
                    $meta = array_merge($meta, $v);
                    unset($args[$i]);
                }
            }
            $args = array_values($args);
            if (empty($args)) {
                // give a default label
                $args[] = 'group';
            }
            $this->appendLog('groupCollapsed', $args, $meta);
        }
    }

    /**
     * Close current group
     *
     * @return void
     */
    public function groupEnd()
    {
        $closesSummary = false;
        $args = func_get_args();
        $meta = array();
        if ($this->data['groupDepthSummary'] > 0) {
            // currently in summaryGroup
            $this->data['groupDepthSummary']--;
            $closesSummary = $this->data['groupDepthSummary'] == 0;
            if ($closesSummary) {
                $meta = array(
                    'closesSummary' => true,
                );
            }
        } elseif ($this->data['groupDepth'] > 0) {
            $this->data['groupDepth']--;
        }
        $errorCaller = $this->errorHandler->get('errorCaller');
        if ($errorCaller && isset($errorCaller['depth']) && $this->data['groupDepth'] < $errorCaller['depth']) {
            $this->errorHandler->setErrorCaller(null);
        }
        if ($this->cfg['collect']) {
            $this->appendLog('groupEnd', $args, $meta);
        }
        if ($closesSummary) {
            $this->logRef = &$this->data['log'];
            $this->groupDepthRef = &$this->data['groupDepth'];
            $this->cfg['collect'] = json_decode(str_replace('summary:', '', $this->cfg['collect']), true);
        }
    }

    /**
     * Initiate the beginning of "summary" log entries
     *
     * When possible summary entries will be displayed at the beginning of the log
     * call groupEnd() (at matching group depth) to end summary
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
        if (!is_string($this->cfg['collect']) || strpos($this->cfg['collect'], 'summary') === false) {
            $this->cfg['collect'] = 'summary:'.json_encode($this->cfg['collect']);
            $this->data['groupDepthSummary']++;
        }
        if (!isset($this->data['logSummary'][$priority])) {
            $this->data['logSummary'][$priority] = array();
        }
        $this->logRef = &$this->data['logSummary'][$priority];
        $this->groupDepthRef = &$this->data['groupDepthSummary'];
        $this->appendLog('groupSummary', array($priority));
    }

    /**
     * Sets ancestor groups to uncollapsed
     *
     * @return void
     */
    public function groupUncollapse()
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $curDepth = $this->groupDepthRef;   // will fluctuate as we go through log
        $minDepth = $this->groupDepthRef;   // decrease as we work our way down
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
        $this->appendLog('groupUncollapse');   // want to dispatch event, but not actually log
    }

    /**
     * Informative logging information
     *
     * @return void
     */
    public function info()
    {
        if ($this->cfg['collect']) {
            $this->appendLog('info', func_get_args());
        }
    }

    /**
     * For logging general information
     *
     * @return void
     */
    public function log()
    {
        if ($this->cfg['collect']) {
            $this->appendLog('log', func_get_args());
        }
    }

    /**
     * Output array as a table
     * accepts
     *    array[, string]
     *    string, array
     *
     * @return void
     */
    public function table()
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $args = func_get_args();
        $argCount = count($args);
        $data = null;
        $caption = $this->abstracter->UNDEFINED;
        $columns = array();
        /*
            find first array in args
            find caption arg
            find columns arg
        */
        for ($i = 0; $i < $argCount; $i++) {
            if (is_array($args[$i])) {
                if ($data === null) {
                    $data = $args[$i];
                } elseif (empty($columns)) {
                    $columns = $args[$i];
                }
            } elseif ($caption === $this->abstracter->UNDEFINED) {
                $caption = $args[$i];
            }
            unset($args[$i]);
        }
        if (is_array($data)) {
            if ($caption === $this->abstracter->UNDEFINED) {
                $caption = null;
            }
            if (empty($data)) {
                $args = $caption !== null
                    ? array($caption, $data)
                    : array($data);
                $this->appendLog('log', $args);
            } else {
                $this->appendLog('table', array($data, $caption, $columns));
            }
        } else {
            $this->appendLog('log', func_get_args());
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
     * Does not append log.   use timeEnd or timeGet to get time
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
     *
     * @return float
     */
    public function timeEnd($label = null, $returnOrTemplate = false)
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
        $ret = round($ret, 4);
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
     * @return float
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
            $ellapsed = round($ellapsed, $precision);
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
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $backtraceNew = array();
        $keysShiftDefault = array_fill_keys(array('class','function','type'), $this->abstracter->UNDEFINED);
        $funcsSkip = array('call_user_func','call_user_func_array');
        for ($i = 0, $count=count($backtrace); $i < $count; $i++) {
            $frameNext = $i+1 < $count
                ? $backtrace[$i+1]
                : array();
            $keysShift = array_intersect_key($frameNext, $keysShiftDefault);    // toss non-keysShift
            $frame = array_merge($backtrace[$i], $keysShiftDefault, $keysShift);
            $skip = $frame['class'] == __CLASS__ || in_array($frame['function'], $funcsSkip);
            if ($skip) {
                continue;
            }
            $frame['function'] = str_replace(
                $this->abstracter->UNDEFINED,
                '',
                $frame['class'].$frame['type'].$frame['function']
            );
            if (!$frame['function']) {
                unset($frame['function']);
            }
            unset($frame['class'], $frame['type']);
            $backtraceNew[] = $frame;
        }
        $this->appendLog('trace', array($backtraceNew));
    }

    /**
     * Log a warning
     *
     * @return void
     */
    public function warn()
    {
        if ($this->cfg['collect']) {
            $this->appendLog(
                'warn',
                func_get_args(),
                $this->internal->getErrorCaller()
            );
        }
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
            $className = __CLASS__;
            // self::$instance set in __construct
            new $className($cfg);
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
    public static function meta($values = array())
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
     * @return string or void
     */
    public function output()
    {
        $return = null;
        if ($this->cfg['output']) {
            while ($this->data['groupDepth'] > 0) {
                $this->data['groupDepth']--;
                $this->data['log'][] = array('groupEnd');
            }
            $outputAs = $this->output->getCfg('outputAs');
            $this->output->setCfg('outputAs', $outputAs);
            $return = $this->eventManager->publish('debug.output', $this, array('output'=>''))['output'];
            $this->data['outputSent'] = true;
            $this->data['log'] = array();
            $this->data['logSummary'] = array();
            $this->data['alerts'] = array();
        } else {
            $this->eventManager->publish('debug.output', $this, array('output'=>''));
        }
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
     * @param string|array $path  path
     * @param mixed        $value value
     *
     * @return void
     */
    public function setData($path, $value)
    {
        if (is_string($path)) {
            $path = preg_split('#[\./]#', $path);
            $ref = &$this->data;
            foreach ($path as $k) {
                $ref = &$ref[$k];
            }
            $ref = $value;
        } else {
            $this->data = $this->utilities->arrayMergeDeep($this->data, $path);
        }
    }

    /**
     * A wrapper for errorHandler->setErrorCaller
     *
     * @param array $caller optional. pass null or array() to clear
     *
     * @return void
     */
    public function setErrorCaller($caller = 'notPassed')
    {
        if (!empty($caller) && $caller != 'notPassed') {
            $caller['depth'] = $this->data['groupDepth'];
        }
        $this->errorHandler->setErrorCaller($caller, 2);
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
     * @param string $method error, info, log, warn
     * @param array  $args   arguments passed to method
     * @param array  $meta   meta data
     *
     * @return void
     */
    protected function appendLog($method, $args = array(), $meta = array())
    {
        foreach ($args as $i => $v) {
            if ($this->abstracter->needsAbstraction($v)) {
                if ($method == 'table' && is_array($v)) {
                    // handle separately... could be an array of \Traversable objects
                    $args[$i] = $this->abstracter->getAbstractionTable($v);
                } else {
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
        $method = $event->getValue('method');
        $args = $event->getValue('args');
        $meta = $event->getValue('meta');
        if ($method == 'alert') {
            $this->data['alerts'][] = $args;
        } else {
            array_unshift($args, $method);
            if ($meta) {
                $meta['debug'] = self::META;
                $args[] = $meta;
            }
            $this->logRef[] = $args;
        }
        return;
    }

    /**
     * Set/cache this class' public methods
     *
     * @return void
     */
    protected function setPublicMethods()
    {
        $refObj = new \ReflectionObject($this);
        self::$publicMethods = array_map(function (\ReflectionMethod $refMethod) {
            return $refMethod->name;
        }, $refObj->getMethods(\ReflectionMethod::IS_PUBLIC));
    }

    /**
     * Log time
     *
     * @param float  $seconds          seconds
     * @param mixed  $returnOrTemplate false: log the time (default)
     *                                 true: do not log
     *                                 string: log using passed template
     * @param string $label            label
     *
     * @return void
     */
    protected function timeLog($seconds, $returnOrTemplate = false, $label = 'time')
    {
        if (!is_string($returnOrTemplate)) {
            if (!$returnOrTemplate) {
                $this->appendLog('time', array($label.': '.$seconds.' sec'));
            }
        } else {
            $str = $returnOrTemplate;
            $str = str_replace('%label', $label, $str);
            $str = str_replace('%time', $seconds, $str);
            $this->appendLog('time', array($str));
        }
    }
}

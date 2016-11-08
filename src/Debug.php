<?php
/**
 * Web-browser/javascript like console class for PHP
 *
 * @package PHPDebugConsole
 * @author  Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.3.3
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk\Debug;

/**
 * Web-browser/javascript like console class for PHP
 */
class Debug
{

    private static $instance;
    protected $cfg = array();
    protected $config;
    protected $data = array();
    protected $outputSent = false;
    protected $state = null;  // 'output' while in output()
    public $errorHandler;
    public $output;
    public $utilities;
    public $varDump;

    const META = "\x00meta\x00";

    /**
     * Constructor
     *
     * @param array  $cfg          config
     * @param object $errorHandler optional - uses \bdk\Debug\ErrorHandler if not passed
     */
    public function __construct($cfg = array(), $errorHandler = null)
    {
        $cfg = array_merge_recursive(array(
            'objectsExclude' => array(
                __CLASS__,                  // don't inspect the debug object when encountered
            ),
        ), $cfg);
        $this->cfg = array(
            'collect'   => false,
            'file'      => null,            // if a filepath, will receive log data
            'key'       => null,
            'output'    => false,           // should output() actually output to browser (either as html or firephp)
            // errorMask = errors that appear as "error" in debug console... all other errors are "warn"
            'errorMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR
                            | E_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR,
            'emailLog'  => false,           // whether to email a debug log. false, 'onError' (true), or 'always'
                                            //   requires 'collect' to also be true
            'emailTo'   => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'emailFunc' => 'mail',          // callable
            'onLog' => null,
        );
        $this->data = array(
            'alerts'         => array(),    // array of alerts.  alerts will be shown at top of output when possible
            'collectToggleCount' => 0,  // used to guess if collection was turned on to collect "environment" info
                                        //    then turned off..  log will no be emailed in this condition
            'counts'        => array(), // count method
            'fileHandle'    => null,
            'groupDepth'    => 0,
            'groupDepthFile'=> 0,
            'log'           => array(),
            'recursion'     => false,
            'timers' => array(      // timer method
                'labels' => array(
                    // label => array(accumulatedTime, lastStartedTime|null)
                ),
                'stack' => array(),
            ),
        );
        $microT = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? $_SERVER['REQUEST_TIME_FLOAT']
            : microtime(true);
        $this->data['timers']['labels']['debugInit'] = array(0, $microT);
        // Initialize self::$instance if not set
        //    so that self::getInstance() will always return original instance
        //    as opposed the the last instance created with new Debug()
        if (!isset(self::$instance)) {
            self::$instance = $this;
        }
        spl_autoload_register(array($this, 'autoloader'));
        $this->config = new Config($this, $this->cfg);
        $this->utilities = new Utilities();
        $this->output = new Output($this, array(), $this->data);
        $this->varDump = new VarDump($this, array(), $this->utilities);
        if ($errorHandler) {
            $this->errorHandler = $errorHandler;
        } else {
            $this->errorHandler = ErrorHandler::getInstance();
        }
        $this->errorHandler->registerOnErrorFunction(array($this,'onError'));
        $this->set($cfg);
        register_shutdown_function(array($this, 'shutdownFunction'));
        return;
    }

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
        if (preg_match('/^(.*?)\\\\([^\\\\]+)$/', $className, $matches) && $matches[1] === __NAMESPACE__) {
            $filePath = __DIR__.'/'.$matches[2].'.php';
            require $filePath;
        }
    }

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
     * Send an email
     *
     * @param string $emailTo to
     * @param string $subject subject
     * @param string $body    body
     *
     * @return void
     */
    public function email($emailTo, $subject, $body)
    {
        call_user_func($this->cfg['emailFunc'], $emailTo, $subject, $body);
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
            $args = func_get_args();
            $this->appendLog('error', $args);
        }
    }

    /**
     * Retrieve a config value, lastError, or css
     *
     * @param string $path what to get
     *
     * @return mixed
     */
    public function get($path)
    {
        return $this->config->get($path);
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
            self::$instance->set($cfg);
        }
        return self::$instance;
    }

    /**
     * Creates a new inline group
     *
     * @return void
     */
    public function group()
    {
        $this->data['groupDepth']++;
        if ($this->cfg['collect']) {
            $args = func_get_args();
            if (empty($args)) {
                $args[] = 'group';
            }
            $this->appendLog('group', $args);
        }
    }

    /**
     * Creates a new inline group
     *
     * @return void
     */
    public function groupCollapsed()
    {
        $this->data['groupDepth']++;
        if ($this->cfg['collect']) {
            $args = func_get_args();
            if (empty($args)) {
                $args[] = 'group';
            }
            $this->appendLog('groupCollapsed', $args);
        }
    }

    /**
     * Close current group
     *
     * @return void
     */
    public function groupEnd()
    {
        if ($this->data['groupDepth'] > 0) {
            $this->data['groupDepth']--;
        }
        $errorCaller = $this->errorHandler->get('errorCaller');
        if ($errorCaller && isset($errorCaller['depth']) && $this->data['groupDepth'] < $errorCaller['depth']) {
            $this->errorHandler->setErrorCaller(null);
        }
        if ($this->cfg['collect']) {
            $args = func_get_args();
            $this->appendLog('groupEnd', $args);
        }
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
        $curDepth = $this->data['groupDepth'];   // will fluctuate as go through log
        $minDepth = $this->data['groupDepth'];   // decrease as we work our way down
        for ($i = count($this->data['log']) - 1; $i >=0; $i--) {
            if ($curDepth < 1) {
                break;
            }
            $method = $this->data['log'][$i][0];
            if (in_array($method, array('group', 'groupCollapsed'))) {
                $curDepth--;
                if ($curDepth < $minDepth) {
                    $minDepth--;
                    $this->data['log'][$i][0] = 'group';
                }
            } elseif ($method == 'groupEnd') {
                $curDepth++;
            }
        }
        $this->appendLog('groupUncollapse', array());
    }

    /**
     * Informative logging information
     *
     * @return void
     */
    public function info()
    {
        if ($this->cfg['collect']) {
            $args = func_get_args();
            $this->appendLog('info', $args);
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
            $args = func_get_args();
            $this->appendLog('log', $args);
        }
    }

    /**
     * Return the log (formatted as html), or send to FirePHP
     *
     *  If outputAs == null -> determined automatically
     *  If outputAs == 'html' -> returns html string
     *  If outputAs == 'firephp' -> returns null
     *
     * @return string or void
     */
    public function output()
    {
        $return = null;
        $this->state = 'output';
        if ($this->cfg['output']) {
            array_unshift($this->data['log'], array('info','Built In '.$this->timeEnd('debugInit', true).' sec'));
            $return = $this->output->output();
            $this->outputSent = true;
            $this->data['log'] = array();
            $this->data['alerts'] = array();
        } elseif ($this->get('output/onOutput')) {
            call_user_func($this->get('output/onOutput'), $this);
        }
        $this->state = null;
        return $return;
    }

    /**
     * Set one or more config values
     *
     * If setting a value via method a or b, old value is returned
     *
     * Setting/updating 'key' will also set 'collect' and 'output'
     *
     *    set('key', 'value')
     *    set('level1.level2', 'value')
     *    set(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string|array $path   path
     * @param mixed        $newVal value
     *
     * @return mixed
     */
    public function set($path, $newVal = null)
    {
        return $this->config->set($path, $newVal);
    }

    /**
     * Advanced usage
     *
     * @param string $path path
     *
     * @return mixed
     */
    public function dataGet($path)
    {
        $path = preg_split('#[\./]#', $path);
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
     * Advanced usage
     *
     * @param array $data array to merge with $this->data
     *
     * @return void
     */
    public function dataSet($data)
    {
        if (isset($data['collectToggleCount']) && is_bool($data['collectToggleCount'])) {
            $data['collectToggleCount'] = $data['collectToggleCount'] + 1;
        }
        $this->data = array_merge($this->data, $data);
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
        $this->errorHandler->setErrorCaller($caller, 2);
        if (!empty($caller)) {
            $this->errorHandler->set('data/errorCaller/depth', $this->data['groupDepth']);
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
        if ($this->cfg['collect']) {
            $args = func_get_args();
            $args_label = array();
            $have_array = false;
            foreach ($args as $k => $v) {
                if (!is_array($v) || $have_array) {
                    if (!is_array($v)) {
                        $args_label[] = (string) $v;
                    }
                    unset($args[$k]);
                } else {
                    $have_array = true;
                }
            }
            $method = 'table';
            if ($have_array) {
                if (!empty($args_label)) {
                    $args[] = implode(' ', $args_label);
                }
            } else {
                $method = 'log';
                $args = $args_label;
                if (count($args) == 2 && !is_string($args[0])) {
                    $args[] = array_shift($args);
                }
            }
            $this->appendLog($method, $args);
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
     *    If label is not passed, timer is removed from no-label stack
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

    /**
     * Log a warning
     *
     * @return void
     */
    public function warn()
    {
        if ($this->cfg['collect']) {
            $args = func_get_args();
            $this->appendLog('warn', $args);
        }
    }

    /**
     * "Non-Public" methods
     */

    /**
     * Store the arguments
     * will be output when output method is called
     *
     * @param string $method error, info, log, warn
     * @param array  $args   arguments passed to method
     *
     * @return void
     */
    protected function appendLog($method, $args)
    {
        foreach ($args as $i => $v) {
            if (is_array($v) || is_object($v) || is_resource($v)) {
                $args[$i] = $this->varDump->getAbstraction($v);
            }
        }
        array_unshift($args, $method);
        /*
            if logging an error or warn, also log originating file/line
        */
        if (in_array($method, array('error','warn'))) {
            $args[] = $this->getErrorCaller();
        }
        if (isset($this->cfg['onLog'])) {
            $return = call_user_func($this->cfg['onLog'], $args);
            if ($return === false) {
                return;
            }
        }
        if (!empty($this->cfg['file']) && $method !== 'groupUncollapse') {
            $this->appendLogFile($args);
        }
        if ($method == 'alert') {
            unset($args[0]);
            $this->data['alerts'][] = $args;
        } else {
            $this->data['log'][] = $args;
        }
        return;
    }

    /**
     * Appends log entry to $this->cfg['file']
     *
     * @param array $args args
     *
     * @return void
     */
    protected function appendLogFile($args)
    {
        if (!isset($this->data['fileHandle'])) {
            $fileExists = file_exists($this->cfg['file']);
            $this->data['fileHandle'] = fopen($this->cfg['file'], 'a');
            if ($this->data['fileHandle']) {
                fwrite($this->data['fileHandle'], '***** '.date('Y-m-d H:i:s').' *****'."\n");
                if (!$fileExists) {
                    chmod($this->cfg['file'], 0660);
                }
            } else {
                // failed to open file
                $this->set('file', null);
            }
        }
        if ($this->data['fileHandle']) {
            $method = array_shift($args);
            if ($args) {
                $str = $this->output->getLogEntryAsText($method, $args, $this->data['groupDepthFile']);
                fwrite($this->data['fileHandle'], $str."\n");
            }
            if (in_array($method, array('group','groupCollapsed'))) {
                $this->data['groupDepthFile']++;
            } elseif ($method == 'groupEnd' && $this->data['groupDepthFile'] > 0) {
                $this->data['groupDepthFile']--;
            }
        }
        return;
    }

    /**
     * get calling line/file for error and warn
     *
     * @return array
     */
    protected function getErrorCaller()
    {
        $meta = array();
        $backtrace = version_compare(PHP_VERSION, '5.4.0', '>=')
            ? debug_backtrace(0, 6)
            : debug_backtrace(false);   // don't provide object
        // path if via ErrorHandler :
        //    0: here we are
        //    1: self::appendLog
        //    2: self::warn
        //    3: self::onError
        //    4: call_user_function
        //    5: ErrorHandler::handleUnsuppressed
        $viaErrorHandler = isset($backtrace[5]['class'])
            && $backtrace[5]['class'] == get_class($this->errorHandler)
            && $backtrace[5]['function'] == 'handleUnsuppressed'
            && $backtrace[4]['function'] == 'call_user_func'
            && isset($backtrace[4]['args'][0][1])
            && $backtrace[4]['args'][0][1] === 'onError';
        if ($viaErrorHandler) {
            // no need to store originating file/line... it's part of error message
            // store errorCat -> can output as a css class
            $lastError = $this->errorHandler->get('lastError');
            $meta = array(
                'debug' => self::META,
                'errorType' => $lastError['type'],
                'errorCat' => $lastError['category'],
            );
        } else {
            foreach ($backtrace as $frame) {
                if (isset($frame['file']) && $frame['file'] !== __FILE__) {
                    if (in_array($frame['function'], array('call_user_func','call_user_func_array'))) {
                        continue;
                    }
                    $meta = array(
                        'debug' => self::META,
                        'file' => $frame['file'],
                        'line' => $frame['line'],
                    );
                    break;
                }
            }
        }
        return $meta;
    }

    /**
     * onError callback
     * called by $this->errorHandler
     * adds error to console as error or warn
     *
     * @param array $error array containing error details
     *
     * @return mixed
     */
    public function onError($error)
    {
        $return = null;
        if ($this->cfg['collect']) {
            $return = false;    // no need to error_log or email this error
            $errStr = $error['typeStr'].': '.$error['file'].' (line '.$error['line'].'): '.$error['message'];
            if ($error['type'] & $this->cfg['errorMask']) {
                $this->error($errStr);
            } else {
                $this->warn($errStr);
            }
            $this->errorHandler->set('data/errors/'.$error['hash'].'/inConsole', true);
            if (in_array($this->cfg['emailLog'], array('always','onError'))) {
                // Don't let errorHandler email error.  our shutdownFunction will email log
                $this->errorHandler->set('data/currentError/allowEmail', false);
            }
        } elseif (!isset($error['inConsole'])) {
            $this->errorHandler->set('data/errors/'.$error['hash'].'/inConsole', false);
        }
        return $return;
    }

    /**
     * Email Log if emailLog is 'always' or 'onError'
     * output log if not already output
     *
     * @return void
     */
    public function shutdownFunction()
    {
        $email = false;
        // data['log']  will likely be non-empty... initial debug info is always collected
        $toggledOnOff = $this->data['collectToggleCount'] == 2 && !$this->cfg['collect'];
        $haveLog = !empty($this->data['log']) && !$toggledOnOff;
        if ($this->cfg['emailTo'] && !$this->cfg['output'] && $haveLog) {
            if ($this->cfg['emailLog'] === 'always') {
                $email = true;
            } elseif ($this->cfg['emailLog'] === 'onError') {
                $unsuppressedError = false;
                $emailableError = false;
                $errors = $this->errorHandler->get('errors');
                $emailMask = $this->errorHandler->get('emailMask');
                foreach ($errors as $error) {
                    if (!$error['suppressed']) {
                        $unsuppressedError = true;
                    }
                    if ($error['type'] & $emailMask) {
                        $emailableError = true;
                    }
                }
                if ($unsuppressedError && $emailableError) {
                    $email = true;
                }
            }
        }
        if ($email) {
            $this->output->emailLog();
        }
        if (!$this->outputSent) {
            echo $this->output();
        }
        return;
    }
}

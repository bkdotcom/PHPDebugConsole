<?php
/**
 * Web-browser/javascript like console class for PHP
 *
 * @package PHPDebugConsole
 * @author  Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.3b
 *
 * @link    http://www.github.com/bkdotcom/PHPDebugConsole
 * @link    https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk\Debug;

/**
 * Web-browser/javascript like console class for PHP
 */
class Debug
{

    private static $instance;
    protected $state = null;  // 'output' while in output()
    protected $cfg = array();
    protected $data = array();
    protected $collect;
    protected $outputSent = false;
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
        );
        $this->data = array(
            'alert'         => '',
            'counts'        => array(),    // count method
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
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            list($whole, $dec) = explode('.', $_SERVER['REQUEST_TIME_FLOAT']);
            $microT = '.'.$dec.' '.$whole;
            $this->data['timers']['labels']['debugInit'] = array(0, $microT);
        } else {
            $this->data['timers']['labels']['debugInit'] = array(0, microtime());
        }
        // Initialize self::$instance if not set
        //    so that self::getInstance() will always return original instance
        //    as opposed the the last instance created with new Debug()
        if (!isset(self::$instance)) {
            self::$instance = $this;
        }
        $files = array(
            'Output.php',
            'Utilities.php',
            'VarDump.php',
            'VarDumpArray.php',
            'VarDumpObject.php',
        );
        foreach ($files as $file) {
            require_once dirname(__FILE__).'/'.$file;
        }
        $this->utilities = new Utilities();
        $this->output = new Output(array(), $this->data);
        $this->varDump = new VarDump(array(), $this->utilities);
        if ($errorHandler) {
            $this->errorHandler = $errorHandler;
        } else {
            require_once dirname(__FILE__).'/ErrorHandler.php';
            $this->errorHandler = ErrorHandler::getInstance();
        }
        $this->errorHandler->registerOnErrorFunction(array($this,'onError'));
        $this->set($cfg);
        $this->collect = &$this->cfg['collect'];
        register_shutdown_function(array($this, 'shutdownFunction'));
        return;
    }

    /**
     * Log a message and stack trace to console if first argument is false.
     *
     * @return void
     */
    public function assert()
    {
        if ($this->collect) {
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
        if ($this->collect) {
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
     * @param string $emailAddr to
     * @param string $subject   subject
     * @param string $body      body
     *
     * @return void
     */
    public function email($emailAddr, $subject, $body)
    {
        call_user_func($this->cfg['emailFunc'], $emailAddr, $subject, $body);
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
        if ($this->collect) {
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
        $path = $this->utilities->translateCfgKeys($path);
        $path = preg_split('#[\./]#', $path);
        if (isset($this->{$path[0]}) && is_object($this->{$path[0]}) && isset($path[1])) {
            // child class config value
            $path_rel = implode('/', array_slice($path, 1));
            $ret = $this->{$path[0]}->get($path_rel);
        } else {
            if ($path[0] == 'data') {
                $ret = $this->data;
                array_shift($path);
            } else {
                if ($path[0] == 'debug') {
                    array_shift($path);
                }
                $ret = $this->cfg;
            }
            foreach ($path as $k) {
                if (isset($ret[$k])) {
                    $ret = $ret[$k];
                } else {
                    $ret = null;
                    break;
                }
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
        if ($this->collect) {
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
        if ($this->collect) {
            $args = func_get_args();
            if (empty($args)) {
                $args[] = 'group';
            }
            $this->appendLog('groupCollapsed', $args);
        }
    }

    /**
     * Sets ancestor groups to uncollapsed
     *
     * @return void
     */
    public function groupUncollapse()
    {
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
        if ($this->collect) {
            $args = func_get_args();
            $this->appendLog('groupEnd', $args);
        }
    }

    /**
     * Informative logging information
     *
     * @return void
     */
    public function info()
    {
        if ($this->collect) {
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
        if ($this->collect) {
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
     * @param string $path   path
     * @param mixed  $newVal value
     *
     * @return mixed
     */
    public function set($path, $newVal = null)
    {
        $ret = null;
        $new = array();
        $path = $this->utilities->translateCfgKeys($path);
        if (is_string($path)) {
            $ret = $this->get($path);
            // build $new array from the passed string
            $path = preg_split('#[\./]#', $path);
            $ref = &$new;
            foreach ($path as $k) {
                $ref[$k] = array(); // initialize this level
                $ref = &$ref[$k];
            }
            $ref = $newVal;
        } elseif (is_array($path)) {
            $new = $path;
        }
        if (isset($new['debug']['key'])) {
            // update 'collect and output'
            $requestKey = null;
            if (isset($_REQUEST['debug'])) {
                $requestKey = $_REQUEST['debug'];
            } elseif (isset($_COOKIE['debug'])) {
                $requestKey = $_COOKIE['debug'];
            }
            $validKey = $requestKey == $new['debug']['key'];
            if ($validKey) {
                // only enable collect / don't disable it
                $new['debug']['collect'] = true;
            }
            $new['debug']['output'] = $validKey;
        }
        if (isset($new['debug']['emailLog']) && $new['debug']['emailLog'] === true) {
            $new['debug']['emailLog'] = 'onError';
        }
        foreach (array('emailFunc','emailTo') as $key) {
            if (isset($new['debug'][$key]) && !isset($new['errorHandler'][$key])) {
                // also set for errorHandler
                $new['errorHandler'][$key] = $new['debug'][$key];
            }
        }
        if (isset($new['data'])) {
            $this->data = array_merge($this->data, $new['data']);
        }
        if (isset($new['debug'])) {
            $this->cfg = $this->utilities->arrayMergeDeep($this->cfg, $new['debug']);
        }
        foreach ($new as $k => $v) {
            if (is_array($v) && isset($this->{$k}) && is_object($this->{$k})) {
                $ret = $this->{$k}->set($v);
                unset($new[$k]);
            }
        }
        return $ret;
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
        if ($this->collect) {
            $args = func_get_args();
            $args_not_array = array();
            $have_array = false;
            foreach ($args as $k => $v) {
                if (!is_array($v) || $have_array) {
                    $args_not_array[] = $v;
                    unset($args[$k]);
                } else {
                    $have_array = true;
                }
            }
            $method = 'table';
            if ($have_array) {
                if (!empty($args_not_array)) {
                    $args[] = implode(' ', $args_not_array);
                }
            } else {
                $method = 'log';
                $args = $args_not_array;
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
                $timers[$label] = array(0, microtime());
            } elseif (!isset($timers[$label][1])) {
                // no microtime -> the timer is currently paused -> unpause
                $timers[$label][1] = microtime();
            }
        } else {
            $this->data['timers']['stack'][] = microtime();
        }
        return;
    }

    /**
     * Behaves like a stopwatch.. returns running time
     *    If label is passed, timer is "paused"
     *    If label is not passed, timer is removed from no-label stack
     *
     * @param string  $label  unique label
     * @param boolean $return = false. If true, only return time, rather than log it
     *
     * @return float
     */
    public function timeEnd($label = null, $return = false)
    {
        if (is_bool($label)) {
            $return = $label;
            $label = null;
        }
        $ret = $this->timeGet($label, true, null); // get not-rounded running time
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
        if (!$return) {
            $this->appendLog('time', array($label, $ret.' sec'));
        }
        return $ret;
    }

    /**
     * Get the running time without stopping/pausing the timer
     *
     * @param string  $label     unique label
     * @param boolean $return    = false. If true, only return time, rather than log it
     * @param integer $precision rounding precision (pass null for no rounding)
     *
     * @return float
     */
    public function timeGet($label = null, $return = false, $precision = 4)
    {
        if (is_bool($label)) {
            $precision = $return;
            $return = $label;
            $label = null;
        }
        $microT = 0;
        $ret = 0;
        if (isset($label)) {
            if (isset($this->data['timers']['labels'][$label])) {
                list($ret, $microT) = $this->data['timers']['labels'][$label];
            }
        } else {
            $label = 'time';
            $microT = end($this->data['timers']['stack']);
        }
        if ($microT) {
            // compute time ellapsed since started
            list($a_dec, $a_sec) = explode(' ', $microT);
            list($b_dec, $b_sec) = explode(' ', microtime());
            $ellapsed = (float)$b_sec - (float)$a_sec + (float)$b_dec - (float)$a_dec;
            $ret += $ellapsed;
        }
        if (is_int($precision)) {
            $ret = round($ret, $precision);
        }
        if (!$return) {
            $this->appendLog('time', array($label, $ret.' sec'));
        }
        return $ret;
    }

    /**
     * Log a warning
     *
     * @return void
     */
    public function warn()
    {
        if ($this->collect) {
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
        if (!empty($this->cfg['file'])) {
            $this->appendLogFile($args);
        }
        /*
            if logging an error or warn, also log originating file/line
        */
        if (in_array($method, array('error','warn'))) {
            $args[] = $this->getErrorCaller();
        }
        $this->data['log'][] = $args;
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
            $this->data['fileHandle'] = fopen($this->cfg['file'], 'a');
            if ($this->data['fileHandle']) {
                fwrite($this->data['fileHandle'], '***** '.date('Y-m-d H:i:s').' *****'."\n");
            } else {
                // failed to open file
                $this->cfg['file'] = null;
            }
        }
        if ($this->data['fileHandle']) {
            $method = array_shift($args);
            if ($method == 'table' && count($args) == 2) {
                $caption = array_pop($args);
                array_unshift($args, $caption);
            }
            if ($args) {
                if (count($args) == 1 && is_string($args[0])) {
                    $args[0] = strip_tags($args[0]);
                }
                foreach ($args as $k => $v) {
                    if ($k > 0 || !is_string($v)) {
                        $args[$k] = $this->varDump->dump($v, 'text');
                    }
                }
                $num_args = count($args);
                if ($method == 'time') {
                    $glue = ': ';
                } else {
                    $glue = ', ';
                    if ($num_args == 2) {
                        $glue = preg_match('/[=:] ?$/', $args[0])   // ends with "=" or ":"
                            ? ''
                            : ' = ';
                    }
                }
                $strIndent = str_repeat('    ', $this->data['groupDepthFile']);
                $str = implode($glue, $args);
                $str = $strIndent.str_replace("\n", "\n".$strIndent, $str);
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
        if ($this->collect) {
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
        if ($this->cfg['emailTo'] && !$this->cfg['output'] && $this->data['log']) {
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

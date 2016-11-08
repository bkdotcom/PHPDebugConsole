<?php
/**
 * Stand-alone, general-purpose error handler
 *
 * @package PHPDebugConsole
 * @author  Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.3.3
 */

namespace bdk\Debug;

if (!defined('E_STRICT')) {
    define('E_STRICT', 2048);               // PHP 5.0.0
}
if (!defined('E_RECOVERABLE_ERROR')) {
    define('E_RECOVERABLE_ERROR', 4096);    // PHP 5.2.0
}
if (!defined('E_DEPRECATED')) {
    define('E_DEPRECATED', 8192);           // PHP 5.3.0
}
if (!defined('E_USER_DEPRECATED')) {
    define('E_USER_DEPRECATED', 16384);     // PHP 5.3.0
}

/**
 * Stand-Alone general-purpose error handler class that supports fatal errors
 *
 * Able to register multiple onError "callback" functions
 * Can email an error report on error and throttles said email so does not excessively send email
 */
class ErrorHandler
{

    protected $cfg = array();
    protected $data = array();
    protected $errTypes = array(
        E_ERROR             => 'Fatal Error',       // handled via shutdown function
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parsing Error',     // handled via shutdown function
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',        // handled via shutdown function
        E_CORE_WARNING      => 'Core Warning',      // handled?
        E_COMPILE_ERROR     => 'Compile Error',     // handled via shutdown function
        E_COMPILE_WARNING   => 'Compile Warning',   // handled?
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_ALL               => 'E_ALL',             // listed here for completeness
        E_STRICT            => 'Runtime Notice (E_STRICT)',
        E_RECOVERABLE_ERROR => 'Fatal Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'User Deprecated',
    );
    protected $errCategories = array(
        'deprecated'    => array( E_DEPRECATED, E_USER_DEPRECATED ),
        'error'         => array( E_USER_ERROR, E_RECOVERABLE_ERROR ),
        'notice'        => array( E_NOTICE, E_USER_NOTICE ),
        'strict'        => array( E_STRICT ),
        'warning'       => array( E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING ),
        'fatal'         => array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR ),
    );
    protected $onErrorFunctions = array();
    protected $registered = false;
    protected $prevErrorHandler = null;
    protected $throttleData = array();
    private static $instance;

    /**
     * Constructor
     *
     * @param array $cfg config
     */
    public function __construct($cfg = array())
    {
        $this->cfg = array(
            'emailFunc' => 'mail',
            'emailMin' => 15,
            'emailTo' => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'emailMask'         => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_WARNING | E_USER_ERROR | E_USER_NOTICE,
            'emailTraceMask'    => E_WARNING | E_USER_ERROR | E_USER_NOTICE,
            'emailThrottledSummary' => true,    // if errors have been throttled, should we email a summary email of throttled errors?
                                                //    (first occurance of error is never throttled)
            'emailThrottleFile' => __DIR__.'/error_emails.json',
            'continueToPrevHandler' => true,    // if there was a prev error handler
            // set onError to something callable, will receive error array
            //     shortcut for registerOnErrorFunction()
            'onError' => null,
        );
        $this->data = array(
            'errorCaller'   => array(),
            'errors'        => array(),
            'lastError'     => array(),
            'currentError'  => array(
                'allowEmail' => true,   // error email will be sent if
                                        //    emailTo is not empty
                                        //    errType matches emailMask
                                        //    no onError function returns false
                                        //    throttle conditions met
                                        //    and 'allowEmail' is true
            ),
        );
        // Initialize self::$instance if not set
        //    so that self::getInstance() will always return original instance
        //    as opposed the the last instance created with new ErrorHandler()
        if (!isset(self::$instance)) {
            self::$instance = $this;
        }
        $this->set($cfg);
        $this->register();
        ini_set('display_errors', 0);
        error_reporting(-1);    // report every possible error ( E_ALL | E_STRICT )
                                // not actually necessary as all errors get sent to custom error handler
        register_shutdown_function(array($this,'shutdownFunction'));
        return;
    }

    /**
     * Send an email
     *
     * @param string $to      to
     * @param string $subject subject
     * @param string $body    body
     *
     * @return void
     */
    protected function email($to, $subject, $body)
    {
        call_user_func($this->cfg['emailFunc'], $to, $subject, $body);
    }

    /**
     * Email this error
     *
     * @param array $error error array
     *
     * @return void
     */
    protected function emailErr($error)
    {
        $dateTimeFmt = 'Y-m-d H:i:s (T)';
        $errMsg     = preg_replace('/ \[<a.*?\/a>\]/i', '', $error['message']);   // remove links from errMsg
        $countSince = $error['stats']['countSince'];
        $subject    = 'Website Error: '.$_SERVER['SERVER_NAME'].': '.$errMsg.($countSince ? ' ('.$countSince.'x)' : '');
        $emailBody  = '';
        if (!empty($countSince)) {
            $dateTimePrev = date($dateTimeFmt, $error['stats']['tsEmailed']);
            $emailBody .= 'Error has occurred '.$countSince.' times since last email ('.$dateTimePrev.').'."\n\n";
        }
        $emailBody .= ''
            .'datetime: '.date($dateTimeFmt)."\n"
            .'errormsg: '.$errMsg."\n"
            .'errortype: '.$error['type'].' ('.$error['typeStr'].')'."\n"
            .'file: '.$error['file']."\n"
            .'line: '.$error['line']."\n"
            .'remote_addr: '.$_SERVER['REMOTE_ADDR']."\n"
            .'http_host: '.$_SERVER['HTTP_HOST']."\n"
            .'referer: '.$_SERVER['HTTP_REFERER']."\n"
            .'request_uri: '.$_SERVER['REQUEST_URI']."\n"
            .'';
        if (!empty($_POST)) {
            $emailBody .= 'post params: '.var_export($_POST, true)."\n";
        }
        if ($error['type'] & $this->cfg['emailTraceMask']) {
            /*
                backtrace:
                0: here
                1: call_user_func_array
                2: errorHandler
                3: where error occured
            */
            $search = array(
                ")\n\n",
            );
            $replace = array(
                ")\n",
            );
            $backtrace = debug_backtrace(null);	// no object info
            $backtrace = array_slice($backtrace, 3);
            foreach ($backtrace as $k => $frame) {
                if ($frame['file'] == $error['file'] && $frame['line'] == $error['line']) {
                    $backtrace = array_slice($backtrace, $k);
                    break;
                }
            }
            $backtrace[0]['vars'] = $error['vars'];
            $debug = __NAMESPACE__.'\\Debug';
            if (class_exists($debug)) {
                $debug = $debug::getInstance();
                $str = $debug->varDump->dump($backtrace, 'text');
            } else {
                $str = print_r($backtrace, true);
                $str = preg_replace('/Array\s+\(\s+\)/s', 'Array()', $str); // single-lineify empty arrays
                $str = str_replace($search, $replace, $str);
                $str = substr($str, 0, -1);
            }
            $emailBody .= "\n".'backtrace: '.$str;
        }
        $this->email($this->cfg['emailTo'], $subject, $emailBody);
        return;
    }

    /**
     * Write string to file / creates file if doesn't exist
     *
     * @param string $file filepath
     * @param string $str  string to write
     *
     * @return integer|boolean number of bytes written or false on error
     */
    protected function fileWrite($file, $str)
    {
        $return = false;
        if (!file_exists($file)) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);    // 3rd param is php 5
            }
        }
        $fh = fopen($file, 'w');
        if ($fh) {
            $return = fwrite($fh, $str);
            fclose($fh);
        }
        return $return;
    }

    /**
     * Retrieve a config or data value
     *
     * @param string $path what to get
     *
     * @return mixed
     */
    public function get($path)
    {
        $ret = null;
        if (isset($this->cfg[$path])) {
            $ret = $this->cfg[$path];
        } elseif (isset($this->data[$path])) {
            $ret = $this->data[$path];
        } elseif (isset($this->{$path})) {
            $ret = $this->{$path};
        }
        return $ret;
    }

    /**
     * generate hash used to uniquely identify this error
     *
     * @param array $error error array
     *
     * @return string hash
     */
    protected function getErrorHash($error)
    {
        $errMsg = $error['message'];
        // (\(.*?)\d+(.*?\))    "(tried to allocate 16384 bytes)" -> "(tried to allocate xxx bytes)"
        $errMsg = preg_replace('/(\(.*?)\d+(.*?\))/', '\1x\2', $errMsg);
        // "blah123" -> "blahxxx"
        $errMsg = preg_replace('/\b([a-z]+\d+)+\b/', 'xxx', $errMsg);
        // "-123.123" -> "xxx"
        $errMsg = preg_replace('/\b[\d.-]{4,}\b/', 'xxx', $errMsg);
        // remove "comments"..  this allows throttling email, while still adding unique info to user errors
        $errMsg = preg_replace('/\s*##.+$/', '', $errMsg);
        $hash = md5($error['file'].$error['line'].$error['type'].$errMsg);
        return $hash;
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
     * Error handler
     *
     * @param integer $errType the level of the error
     * @param string  $errMsg  the error message
     * @param string  $file    filepath the error was raised in
     * @param string  $line    the line the error was raised in
     * @param array   $vars    active symbol table at point error occured
     *
     * @return void
     * @link http://www.php.net/manual/en/language.operators.errorcontrol.php
     */
    public function handler($errType, $errMsg, $file, $line, $vars = array())
    {
        $data = &$this->data;
        // determine $category
        foreach ($this->errCategories as $category => $errTypes) {
            if (in_array($errType, $errTypes)) {
                break;
            }
        }
        $isSuppressed = $category != 'fatal' && error_reporting() === 0;
        $error = array(
            'type'      => $errType,                    // int
            'category'  => $category,
            'typeStr'   => $this->errTypes[$errType],   // friendly string version of 'type'
            'message'   => $errMsg,
            'file'      => $file,
            'line'      => $line,
        );
        $hash = $this->getErrorHash($error);
        $firstOccur = !isset($data['errors'][$hash]);
        if (!empty($data['errorCaller'])) {
            $error['file'] = $data['errorCaller']['file'];
            $error['line'] = $data['errorCaller']['line'];
        }
        $error = array_merge($error, array(
            'hash'      => $hash,
            'firstOccur'=> $firstOccur,
            // if any instance of this error was not supprssed, reflect that
            'suppressed'=> !$firstOccur && !$data['errors'][$hash]['suppressed']
                ? false
                : $isSuppressed,
            'stats' => array(
                'tsEmailed'  => 0,
                'countSince' => 0,
                'emailedTo'  => '',
            ),
            'vars' => $vars,
        ));
        $data['errors'][$hash] = &$error;
        $data['lastError'] = &$data['errors'][$hash];
        $data['currentError'] = array(
            // this array is updated in handleUnsuppressed()
            'email' => false,
            'errorLog' => false,
            'allowEmail' => true, // onError function(s) may set to false to prevent email
        );
        if (!$isSuppressed) {
            $this->handleUnsuppressed($error);
            // ( suppressed error should not clear error caller )
            $data['errorCaller'] = array();
        }
        if ($data['currentError']['email']) {
            $this->emailErr($error);
        }
        if ($this->cfg['continueToPrevHandler'] && $this->prevErrorHandler) {
            call_user_func($this->prevErrorHandler, $errType, $errMsg, $error['file'], $error['line'], $vars);
        } elseif ($data['currentError']['errorLog']) {
            $errStrLog = $error['typeStr'].': '.$error['file'].' : '.$error['message'].' on line '.$error['line'];
            error_log('PHP '.$errStrLog);
        }
        // return false to continue to "normal" error handler
        return;
    }

    /**
     * calls onErrorFunctions
     * test unsuppressed errors whether should email or error_log
     * updates $this->data['currentError']
     *
     * @param array $error error array
     *
     * @return void
     */
    protected function handleUnsuppressed($error)
    {
        $data = &$this->data;
        $onErrorReturnedFalse = false;
        $error['stats'] = $this->throttleDataGet($error);
        foreach ($this->onErrorFunctions as $callable) {
            $response = call_user_func($callable, $error);
            if ($response === false) {
                $onErrorReturnedFalse = true;
            }
        }
        if ($error['firstOccur'] && !$onErrorReturnedFalse) {
            if ($this->cfg['emailTo'] && ( $error['type'] & $this->cfg['emailMask'] )) {
                if ($this->cfg['emailMin'] > 0) {
                    /*
                        keep track of error emails to prevent email flood
                    */
                    $stats = $this->throttleDataUpdate($error);
                    $tsNow = time();
                    $tsCutoff = $tsNow - $this->cfg['emailMin'] * 60;
                    $data['lastError']['stats'] = $stats;
                    $data['currentError']['email'] = $stats['tsEmailed'] <= $tsCutoff;
                }
                if (!$data['currentError']['allowEmail']) {
                    $data['currentError']['email'] = false;
                }
            }
            $data['currentError']['errorLog'] = true;
        }
        return;
    }

    /**
     * Return a unique identifier for callable
     *
     * @param mixed $callable callable
     *
     * @return string|false  returns false if not callable
     */
    protected function idCallable($callable)
    {
        $id = false;
        $isCallable = is_callable($callable, true, $callableName);
        if ($isCallable) {
            if (is_object($callable)) {
                // ie instanceof Closure
                $id = spl_object_hash($callable);
            } else {
                $id = $callableName;
                if (is_array($callable) && is_object($callable[0])) {
                    $id .= ' '.spl_object_hash($callable[0]);
                }
            }
        }
        return $id;
    }

    /**
     * Register this error handler and shutdown function
     *
     * @return void
     */
    public function register()
    {
        if (!$this->registered) {
            $this->registered = true;   // used by this->shutdownFunction()
            $this->prevErrorHandler = set_error_handler(array($this, 'handler'));
        }
        return;
    }

    /**
     * register an onError function
     *
     * @param callable $callable a callable function
     *
     * @return void
     */
    public function registerOnErrorFunction($callable)
    {
        $id = $this->idCallable($callable);
        if ($id && !isset($this->onErrorFunctions[$id])) {
            $this->onErrorFunctions[$id] = $callable;
        }
        return;
    }

    /**
     * Set one or more config values
     *
     * If setting a single value via method a or b, old value is returned
     *
     *    set('key', 'value')
     *    set('level1.level2', 'value')
     *    set(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string $path   key
     * @param mixed  $newVal value
     *
     * @return mixed
     */
    public function set($path, $newVal = null)
    {
        $ret = null;
        if (is_string($path)) {
            $path = preg_split('#[\./]#', $path);
            if ($path[0] == 'data') {
                $ret = $this->data;
                $ref = &$this->data;
                array_shift($path);
            } else {
                $ret = $this->cfg;
                $ref = &$this->cfg;
                if ($path[0] == 'onError') {
                    $this->registerOnErrorFunction($newVal);
                }
            }
            foreach ($path as $k) {
                $ret = isset($ret[$k])
                    ? $ret[$k]
                    : null;
                $ref = &$ref[$k];
            }
            $ref = $newVal;
        } elseif (is_array($path)) {
            if (isset($path['onError'])) {
                $this->registerOnErrorFunction($path['onError']);
                unset($path['onError']);
            }
            $this->cfg = array_merge($this->cfg, $path);
        }
        return $ret;
    }

    /**
     * Set the calling file/line for next error
     * this override will apply until cleared or error occurs
     *
     * Example:  some wrapper function that is called often:
     *     Rather than reporting that an error occurred within the wrapper, you can use
     *     setErrorCaller() to report the error originating from the file/line that called the function
     *
     * @param array   $caller pass null or array() to clear (default)
     * @param integer $offset if determining automatically : how many fuctions to go back (default = 1)
     *
     * @return void
     */
    public function setErrorCaller($caller = 'notPassed', $offset = 1)
    {
        if ($caller === 'notPassed') {
            $backtrace = version_compare(PHP_VERSION, '5.4.0', '>=')
                ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $offset + 2)
                : debug_backtrace(false);   // don't provide object
            $i = isset($backtrace[$offset])
                ? $offset
                : $offset-1;
            $caller = isset($backtrace[$i]['file'])
                ? $backtrace[$i]
                : $backtrace[$i+1]; // likely called via call_user_func.. need to go one more to get calling file & line
            $caller = array(
                'file' => $caller['file'],
                'line' => $caller['line'],
            );
        } elseif (empty($caller)) {
            // clear errorCaller
            $caller = array();
        }
        $this->data['errorCaller'] = $caller;
        return;
    }

    /**
     * Catch Fatal Error ( if PHP >= 5.2 )
     *
     * @return void
     * @requires PHP >= 5.2.0 / should be met as class requires PHP >= 5.3.0 (namespaces)
     */
    public function shutdownFunction()
    {
        if ($this->registered && version_compare(PHP_VERSION, '5.2.0', '>=')) {
            $error = error_get_last();
            if (in_array($error['type'], $this->errCategories['fatal'])) {
                $this->handler($error['type'], $error['message'], $error['file'], $error['line']);
            }
        }
        return;
    }

    /**
     * Load throttle data
     *
     * @return void
     */
    protected function throttleDataLoad()
    {
        if (!$this->throttleData) {
            $dataStr = is_readable($this->cfg['emailThrottleFile'])
                            ? file_get_contents($this->cfg['emailThrottleFile'])
                            : '';
            $throttleData = json_decode($dataStr, true);
            if (!is_array($throttleData)) {
                $tsNow = time();
                $throttleData = array(
                    'tsTrashCollection' => $tsNow,
                    'errors' => array(),
                );
            }
            $this->throttleData = $throttleData;
        }
    }

    /**
     * load throttle stats for passed error
     *
     * @param array $error error array
     *
     * @return aarray
     */
    protected function throttleDataGet($error)
    {
        $return = $this->data['lastError']['stats']; // initialize
        if ($this->cfg['emailThrottleFile']) {
            $this->throttleDataLoad();
            $hash = $error['hash'];
            if (isset($this->throttleData['errors'][$hash])) {
                foreach (array_keys($return) as $k) {
                    $return[$k] = $this->throttleData['errors'][$hash][$k];
                }
            }
        }
        return $return;
    }

    /**
     * Returns associative array containing
     *    tsEmailed     // previously emailed ts
     *    countSince    // times error has occured since being emailed
     *    emailTo       // who the error was emailed to
     *
     * @param array $error error array
     *
     * @return array error's throttle stats
     */
    protected function throttleDataUpdate($error)
    {
        $return = $error['stats'];
        $cfg = &$this->cfg;
        if ($cfg['emailThrottleFile']) {
            $throttleData = &$this->throttleData;
            $hash = $error['hash'];
            $init = true;   // initialize this errors throttleData
            $tsNow = time();
            if ($return['tsEmailed']) {
                // error has been emailed at some point
                $tsCutoff = $tsNow - $cfg['emailMin'] * 60;
                if ($throttleData['errors'][$hash]['tsEmailed'] > $tsCutoff) {
                    // This error was recently emailed
                    $init = false;
                    $throttleData['errors'][$hash]['countSince']++;
                }
            }
            if ($init) {
                if ($this->data['errorCaller']) {
                    $error['file'] = $this->data['errorCaller']['file'];
                    $error['line'] = $this->data['errorCaller']['file'];
                }
                $throttleData['errors'][$hash] = array(
                    'file'       => $error['file'],
                    'line'       => $error['line'],
                    'errType'    => $error['type'],
                    'errMsg'     => $error['message'],
                    'tsEmailed'  => $tsNow,
                    'emailedTo'  => $this->cfg['emailTo'],
                    'countSince' => 0,
                );
            }
            $throttleData = $this->throttleTrashCollection($throttleData);
            $this->fileWrite($cfg['emailThrottleFile'], json_encode($throttleData));
        }
        return $return;
    }

    /**
     * Clean out errors stored in emailThrottleFile that havent occured recently
     * If error(s) have occured since they were last emailed, a summary email will be sent
     *
     * @param array $data Data structure as stored in emailThrottleFile
     *
     * @return array
     */
    protected function throttleTrashCollection($data)
    {
        $tsNow     = time();
        $tsCutoff  = $tsNow - $this->cfg['emailMin'] * 60;
        if ($data['tsTrashCollection'] < $tsCutoff) {
            // trash collection time
            $data['tsTrashCollection'] = $tsNow;
            $emailBody = '';
            foreach ($data['errors'] as $k => $err) {
                if ($err['tsEmailed'] > $tsCutoff) {
                    continue;
                }
                // it's been a while since this error was emailed
                if ($err['emailedTo'] != $this->cfg['emailTo']) {
                    // it was emailed to a different address
                    if ($err['countSince'] < 1 || $err['tsEmailed'] < $tsNow - 60*60*24) {
                        unset($data['errors'][$k]);
                    }
                    continue;
                }
                unset($data['errors'][$k]);
                if ($err['countSince'] > 0) {
                    $dateLastEmailed = date('Y-m-d H:i:s', $err['tsEmailed']);
                    $emailBody .= ''
                        .'File: '.$err['file']."\n"
                        .'Line: '.$err['line']."\n"
                        .'Error: '.$this->errTypes[ $err['errType'] ].': '.$err['errMsg']."\n"
                        .'Has occured '.$err['countSince'].' times since '.$dateLastEmailed."\n\n";
                }
            }
            if ($emailBody && $this->cfg['emailThrottledSummary']) {
                $this->email($this->cfg['emailTo'], 'Website Errors: '.$_SERVER['SERVER_NAME'], $emailBody);
            }
        }
        return $data;
    }

    /**
     * un-register this error handler and shutdown function
     *
     * Note:  PHP conspicuously lacks an unregister_shutdown_function function.
     *     Technically this will still be registered, however:
     *     $this->registered will be used to keep track of whether
     *     we're "registered" or not and behave accordingly
     *
     * @return void
     */
    public function unregister()
    {
        if ($this->registered) {
            // set and restore error handler to determine the current error handler
            $errHandlerCur = set_error_handler(array($this, 'handler'));
            restore_error_handler();
            if ($errHandlerCur == array($this, 'handler')) {
                // we are the current error handler
                restore_error_handler();
            }
            $this->prevErrorHandler = null;
            $this->registered = false;  // used by shutdownFunction()
        }
        return;
    }

    /**
     * unregister an onError function
     *
     * @param callable $callable a callable function
     *
     * @return void
     */
    public function unregisterOnErrorFunction($callable)
    {
        $id = $this->idCallable($callable);
        if ($id) {
            unset($this->onErrorFunctions[$id]);
        }
        return;
    }
}

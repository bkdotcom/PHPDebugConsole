<?php

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
 * Error handling methods
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
            'emailMin'          => 15,
            'emailTo' => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'emailMask'         => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_WARNING | E_USER_ERROR | E_USER_NOTICE,
            'emailTraceMask'    => E_WARNING | E_USER_ERROR | E_USER_NOTICE,
            'continueToPrevHandler' => true,    // if there was a prev error handler
            'emailThrottleFile' => dirname(__FILE__).'/error_emails.json',
            // set onError to something callable, will receive error array
            //     shortcut for registerOnErrorFunction()
            'onError'           => null,
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
     * Email this error
     *
     * @param integer $errType the level of the error
     * @param string  $errMsg  the error message
     * @param string  $file    "actual" filepath the error was raised in
     * @param string  $line    "actual" line the error was raised in
     * @param array   $vars    active symbol table at point error occured
     *
     * @return void
     */
    protected function emailErr($errType, $errMsg, $file, $line, $vars = array())
    {
        $dateTimeFmt = 'Y-m-d H:i:s (T)';
        $errMsg     = preg_replace('/ \[<a.*?\/a>\]/i', '', $errMsg);   // remove links from errMsg
        $cs         = $stats['countSince'];
        $subject    = 'Website Error: '.$_SERVER['SERVER_NAME'].': '.$errMsg.( $cs ? ' ('.$cs.'x)' : '' );
        $emailBody = '';
        if (!empty($cs)) {
            $dateTimePrev = date($dateTimeFmt, $stats['tsEmailed']);
            $emailBody .= 'Error has occurred '.$cs.' times since last email ('.$dateTimePrev.').'."\n\n";
        }
        $emailBody .= ''
            .'datetime: '.date($dateTimeFmt)."\n"
            .'errormsg: '.$errMsg."\n"
            .'errortype: '.$errType.' ('.$this->errTypes[$errType].')'."\n"
            .'file: '.$file."\n"
            .'line: '.$line."\n"
            .'remote_addr: '.$_SERVER['REMOTE_ADDR']."\n"
            .'http_host: '.$_SERVER['HTTP_HOST']."\n"
            .'request_uri: '.$_SERVER['REQUEST_URI']."\n"
            .'';
        if (!empty($_POST)) {
            $emailBody .= 'post params: '.var_export($_POST, true)."\n";
        }
        if ($errType & $this->cfg['emailTraceMask']) {
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
            $backtrace = debug_backtrace();
            $backtrace = array_slice($backtrace, 3);
            $backtrace[0]['vars'] = $vars;
            $str = print_r($backtrace, true);
            $str = preg_replace('/Array\s+\(\s+\)/s', 'Array()', $str); // single-lineify empty arrays
            $str = str_replace($search, $replace, $str);
            $str = substr($str, 0, -1);
            $emailBody .= "\n".'backtrace: '.$str;
        }
        mail($this->cfg['emailTo'], $subject, $emailBody);
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
    public function fileWrite($file, $str)
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
     * @param string $k what to get
     *
     * @return mixed
     */
    public function get($k)
    {
        $ret = null;
        if (isset($this->cfg[$k])) {
            $ret = $this->cfg[$k];
        } elseif (isset($this->data[$k])) {
            $ret = $this->data[$k];
        } elseif (isset($this->{$k})) {
            $ret = $this->{$k};
        }
        return $ret;
    }

    /**
     * generate hash
     *
     * @param integer $errType the level of the error
     * @param string  $errMsg  the error message
     * @param string  $file    filepath the error was raised in
     * @param string  $line    the line the error was raised in
     *
     * @return string hash
     */
    protected function getErrorHash($errType, $errMsg, $file, $line)
    {
        $errMsg = preg_replace('/(\(.*?)\d+(.*?\))/', '\1x\2', $errMsg);
        $errMsg = preg_replace('/\b([a-z]+\d+)+\b/', 'xxx', $errMsg);
        $errMsg = preg_replace('/\b[\d.-]{4,}\b/', 'xxx', $errMsg);
        $hash = md5($file.$line.$errType.$errMsg);
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
        $cfg = &$this->cfg;
        $data = &$this->data;
		// determine $category
        foreach ($this->errCategories as $category => $errTypes) {
            if (in_array($errType, $errTypes)) {
                break;
            }
        }
        $isSuppressed = $category != 'fatal' && error_reporting() === 0;
        $hash = $this->getErrorHash($errType, $errMsg, $file, $line);
        $firstOccur = !isset($data['errors'][$hash]);
        if (!empty($data['errorCaller'])) {
            $file = $data['errorCaller']['file'];
            $line = $data['errorCaller']['line'];
        }
        $errStrLog = $this->errTypes[$errType].': '.$file.' : '.$errMsg.' on line '.$line;
        $error = array(
            'type'      => $errType,
            'category'  => $category,
            'typeStr'   => $this->errTypes[$errType],
            'message'   => $errMsg,
            'file'      => $file,
            'line'      => $line,
            'hash'      => $hash,
            'firstOccur'=> $firstOccur,
            // if any instance of this error was not supprssed, reflect that
            'suppressed'=> !$firstOccur && !$data['errors'][$hash]['suppressed']
                ? false
                : $isSuppressed,
        );
        $data['errors'][$hash] = $error;
        $data['lastError'] = &$data['errors'][$hash];
        $data['currentError'] = array(
            'allowEmail' => true, // onError function(s) may set to false to prevent email
        );
        $email = false;
        $callErrorLog = false;
        if ($isSuppressed) {
            // @suppressed error
        } else {
            $onErrorReturnedFalse = false;
            foreach ($this->onErrorFunctions as $callable) {
                $response = call_user_func($callable, $error);
                if ($response === false) {
                    $onErrorReturnedFalse = true;
                }
            }
            if ($firstOccur && !$onErrorReturnedFalse) {
                if ($this->cfg['emailTo'] && ( $errType & $cfg['emailMask'] )) {
                    if ($this->cfg['emailMin'] > 0) {
                        /*
                            keep track of error emails to prevent email flood
                        */
                        $stats = $this->throttleDataUpdate($errType, $errMsg, $file, $line);
                        $tsNow = time();
                        $tsCutoff = $tsNow - $this->cfg['emailMin'] * 60;
                        $email = $stats['tsEmailed'] <= $tsCutoff;
                    }
                    if (!$data['currentError']['allowEmail']) {
                        $email = false;
                    }
                }
                $callErrorLog = true;
            }
        }
        if (!$isSuppressed) {
            $data['errorCaller'] = array();
        }
        if ($email) {
            $this->emailErr($errType, $errMsg, $file, $line, $vars);
        }
        if ($cfg['continueToPrevHandler'] && $this->prevErrorHandler) {
            call_user_func($this->prevErrorHandler, $errType, $errMsg, $file, $line, $vars);
        } elseif ($callErrorLog) {
            error_log('PHP '.$errStrLog);
        }
        // return false to continue to "normal" error handler
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
     * @param string $k key
     * @param mixed  $v value
     *
     * @return mixed
     */
    public function set($k, $v = null)
    {
        $ret = null;
        if (is_string($k)) {
            $path = preg_split('#[\./]#', $k);
            if ($path[0] == 'data') {
                $ret = $this->data;
                $ref = &$this->data;
                array_shift($path);
            } else {
                $ret = $this->cfg;
                $ref = &$this->cfg;
                if ($path[0] == 'onError') {
                    $this->registerOnErrorFunction($v);
                }
            }
            foreach ($path as $k) {
                $ret = isset($ret[$k])
                    ? $ret[$k]
                    : null;
                $ref = &$ref[$k];
            }
            $ref = $v;
        } elseif (is_array($k)) {
            $this->cfg = array_merge($this->cfg, $k);
            if (isset($k['onError'])) {
                $this->registerOnErrorFunction($k['onError']);
            }
        }
        return $ret;
    }

    /**
     * Set the calling file/line for next error
     * this override will apply until cleared, error occurs, or groupEnd()
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
            $backtrace = debug_backtrace();
            $i = isset($backtrace[$offset])
                ? $offset
                : $offset-1;
            $caller = isset($backtrace[$i]['file'])
                ? $backtrace[$i]
                : $backtrace[$i+1];
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
     * @requires PHP 5.2.0
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
     * Returns associative array containing
     *    tsEmailed     // previously emailed ts
     *    countSince    // times error has occured since being emailed
     *    emailTo       // who the error was emailed to
     *
     * @param integer $errType the level of the error
     * @param string  $errMsg  the error message
     * @param string  $file    filepath the error was raised in
     * @param string  $line    the line the error was raised in
     *
     * @return array
     */
    protected function throttleDataUpdate($errType, $errMsg, $file, $line)
    {
        $return = array(
            'tsEmailed'  => 0,
            'countSince' => 0,
            'emailTo'    => '',
        );
        $cfg = &$this->cfg;
        if ($cfg['emailThrottleFile']) {
            $tsNow = time();
            $tsCutoff = $tsNow - $cfg['emailMin'] * 60;
            $hash = $this->getErrorHash($errType, $errMsg, $file, $line);
            $throttleData = &$this->throttleData;
            if (!$throttleData) {
                $dataStr = is_readable($cfg['emailThrottleFile'])
                                ? file_get_contents($cfg['emailThrottleFile'])
                                : '';
                $throttleData = json_decode($dataStr, true);
                if (!is_array($throttleData)) {
                    $throttleData = array(
                        'tsTrashCollection' => $tsNow,
                        'errors' => array(),
                    );
                }
            }
            $init = true;
            if (isset($throttleData['errors'][$hash])) {
                foreach ($return as $k => $v) {
                    $return[$k] = $throttleData['errors'][$hash][$k];
                }
                if ($throttleData['errors'][$hash]['tsEmailed'] > $tsCutoff) {
                    // This error was recently emailed
                    $init = false;
                    $throttleData['errors'][$hash]['countSince']++;
                }
            }
            if ($init) {
                if ($this->data['errorCaller']) {
                    $file = $this->data['errorCaller']['file'];
                    $line = $this->data['errorCaller']['file'];
                }
                $throttleData['errors'][$hash] = array(
                    'file'       => $file,
                    'line'       => $line,
                    'errType'    => $errType,
                    'errMsg'     => $errMsg,
                    'tsEmailed'  => $tsNow,
                    'emailTo'    => $this->cfg['emailTo'],
                    'countSince' => 0,
                );
            }
            $throttleData = $this->throttleTrashCollection($throttleData);
            $wrote = $this->fileWrite($cfg['emailThrottleFile'], json_encode($throttleData));
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
                if ($err['emailTo'] != $this->cfg['emailTo']) {
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
            if ($emailBody) {
                mail($this->cfg['emailTo'], 'Website Errors: '.$_SERVER['SERVER_NAME'], $emailBody);
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

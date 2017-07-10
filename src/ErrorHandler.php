<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 */

namespace bdk\Debug;

/*
    These should all be defined...  we're using namespaces which require php >= 5.3.0
*/
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
 * General-purpose error handler which supports fatal errors
 *
 * Able to register multiple onError "callback" functions
 */
class ErrorHandler
{

    public $eventManager;
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
    protected $registered = false;
    protected $prevErrorHandler = null;
    protected $prevDisplayErrors = null;
    private static $instance;

    /**
     * Constructor
     *
     * @param array $eventManager event manager
     * @param array $cfg          config
     */
    public function __construct($eventManager, $cfg = array())
    {
        $this->eventManager = $eventManager;
        $this->cfg = array(
            'continueToPrevHandler' => true,    // if there was a prev error handler
            'errorReporting' => E_ALL | E_STRICT,    // what errors are handled by handler? bitmask or "system" to use runtime value
            // set onError to something callable, will receive error array
            //     shortcut for registerOnErrorFunction()
            'onError' => null,
        );
        $this->data = array(
            'errorCaller'   => array(),
            'errors'        => array(),
            'lastError'     => null,
        );
        // Initialize self::$instance if not set
        //    so that self::getInstance() will always return original instance
        //    as opposed the the last instance created with new ErrorHandler()
        if (!isset(self::$instance)) {
            self::$instance = $this;
        }
        $this->setCfg($cfg);
        $this->register();
        // there's no method to unregister a shutdown function
        //    so, always register, and have shutdownFunction check if "registered"
        register_shutdown_function(array($this,'shutdownFunction'));
        return;
    }

    /**
     * Retrieve a configuration value
     *
     * @param string $key what to get
     *
     * @return mixed
     */
    public function getCfg($key = null)
    {
        if ($key === null) {
            return $this->cfg;
        }
        if (isset($this->cfg[$key])) {
            return $this->cfg[$key];
        }
        return null;
    }

    /**
     * Retrieve a data value or property
     *
     * @param string $key what to get
     *
     * @return mixed
     */
    public function get($key = null)
    {
        if ($key == 'lastError') {
            return isset($this->data['lastError'])
                ? $this->data['lastError']->getValues()
                : null;
        }
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
        if (isset($this->{$key})) {
            return $this->{$key};
        }
        return null;
    }

    /**
     * Returns the *Singleton* instance of this class (IF INSTANCE EXISTS)
     *
     * @param array $cfg optional config
     *
     * @return object|false
     */
    public static function getInstance($cfg = array())
    {
        if (!isset(self::$instance)) {
            return false;
        } elseif ($cfg) {
            self::$instance->setCfg($cfg);
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
     * @return boolean
     * @link   http://www.php.net/manual/en/language.operators.errorcontrol.php
     */
    public function handler($errType, $errMsg, $file, $line, $vars = array())
    {
        $prevHandler = $this->cfg['continueToPrevHandler'] && $this->prevErrorHandler;
        $errorReporting = $this->cfg['errorReporting'] === 'system'
            ? error_reporting()
            : $this->cfg['errorReporting'];
        if (!($errType & $errorReporting)) {
            // not handled
            if ($prevHandler) {
                call_user_func($this->prevErrorHandler, $errType, $errMsg, $file, $line, $vars);
            } else {
                return false;   // return false to continue to "normal" error handler
            }
        }
        $error = $this->buildError($errType, $errMsg, $file, $line, $vars);
        $this->data['errors'][ $error['hash'] ] = $error;
        $this->data['lastError'] = $error;
        if (!$error['suppressed']) {
            $error['errorLog'] = $error['firstOccur'];
            $this->eventManager->dispatch('errorHandler.error', $error);
            // suppressed error should not clear error caller
            $this->data['errorCaller'] = array();
        }
        if ($prevHandler) {
            call_user_func($this->prevErrorHandler, $errType, $errMsg, $error['file'], $error['line'], $vars);
        } elseif ($error['errorLog']) {
            $errStrLog = $error['typeStr'].': '.$error['file'].' : '.$error['message'].' on line '.$error['line'];
            error_log('PHP '.$errStrLog);
        }
        // return false to continue to "normal" error handler
        return;
    }

    /**
     * Register this error handler and shutdown function
     *
     * @return void
     */
    public function register()
    {
        if (!$this->registered) {
            $this->prevDisplayErrors = ini_set('display_errors', 0);
            $this->prevErrorHandler = set_error_handler(array($this, 'handler'));
            $this->registered = true;   // used by this->shutdownFunction()
        }
        return;
    }

    /**
     * Set one or more config values
     *
     * If setting a single value via method a or b, old value is returned
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string $mixed  key=>value array or key
     * @param mixed  $newVal value
     *
     * @return mixed
     */
    public function setCfg($mixed, $newVal = null)
    {
        $ret = null;
        $values = array();
        if (is_string($mixed)) {
            $key = $mixed;
            $ret = isset($this->cfg[$key])
                ? $this->cfg[$key]
                : null;
            $values = array(
                $key => $newVal,
            );
        } elseif (is_array($mixed)) {
            $values = $mixed;
        }
        if (isset($values['onError'])) {
            $this->eventManager->addListener('errorHandler.error', $values['onError']);
            unset($values['onError']);
        }
        $this->cfg = array_merge($this->cfg, $values);
        return $ret;
    }

    /**
     * Set data value
     *
     * @param string $key   what
     * @param mixed  $value value
     *
     * @return void
     */
    public function setData($key, $value)
    {
        $this->data[$key] = $value;
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
     *
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
            ini_set('display_errors', $this->prevDisplayErrors);
            $this->prevErrorHandler = null;
            $this->registered = false;  // used by shutdownFunction()
        }
        return;
    }

    /**
     * Build error object
     *
     * Error object is simply an event object
     *
     * @param integer $errType the level of the error
     * @param string  $errMsg  the error message
     * @param string  $file    filepath the error was raised in
     * @param string  $line    the line the error was raised in
     * @param array   $vars    active symbol table at point error occured
     *
     * @return boolean
     */
    protected function buildError($errType, $errMsg, $file, $line, $vars)
    {
        // determine $category
        foreach ($this->errCategories as $category => $errTypes) {
            if (in_array($errType, $errTypes)) {
                break;
            }
        }
        $errorValues = array(
            'type'      => $errType,                    // int
            'typeStr'   => $this->errTypes[$errType],   // friendly string version of 'type'
            'category'  => $category,
            'message'   => $errMsg,
            'file'      => $file,
            'line'      => $line,
            'vars'      => $vars,
            'errorLog'  => false,
            'firstOccur' => true,
            'hash'      => null,
            'suppressed' => $category != 'fatal' && error_reporting() === 0,
            /*
            'email' => false,
            'stats' => array(
                'tsEmailed'  => 0,
                'countSince' => 0,
                'emailedTo'  => '',
            ),
            */
        );
        $hash = $this->errorHash($errorValues);
        $firstOccur = !isset($this->data['errors'][$hash]);
        if (!empty($this->data['errorCaller'])) {
            $errorValues['file'] = $this->data['errorCaller']['file'];
            $errorValues['line'] = $this->data['errorCaller']['line'];
        }
        $errorValues = array_merge($errorValues, array(
            'hash' => $hash,
            'firstOccur' => $firstOccur,
            // if any instance of this error was not supprssed, reflect that
            'suppressed' => !$firstOccur && !$this->data['errors'][$hash]['suppressed']
                ? false
                : $errorValues['suppressed'],
        ));
        return new Event(null, $errorValues);
    }

    /**
     * Generate hash used to uniquely identify this error
     *
     * @param array $error error array
     *
     * @return string hash
     */
    protected function errorHash($error)
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
}

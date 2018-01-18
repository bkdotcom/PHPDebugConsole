<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v2.0.1
 */

namespace bdk\Debug;

use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;

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
    protected $data = array(
        'errorCaller'   => array(),
        'errors'        => array(),
        'lastError'     => null,
    );
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
        E_RECOVERABLE_ERROR => 'Recoverable Error',
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
    protected $prevDisplayErrors = null;
    protected $prevErrorHandler = null;
    protected $prevExceptionHandler = null;
    protected $uncaughtException;
    private static $instance;

    /**
     * Constructor
     *
     * @param EventManager $eventManager event manager
     * @param array        $cfg          config
     */
    public function __construct(EventManager $eventManager, $cfg = array())
    {
        $this->eventManager = $eventManager;
        $this->cfg = array(
            'continueToPrevHandler' => true,    // continue to prev handler (if there is a prev error handler)
            'errorReporting' => E_ALL | E_STRICT,  // what errors are handled by handler? bitmask or "system" to use runtime value
                                                   // note that if using "system", suppressed errors (via @ operator) will not be handled (we'll still handle fatal category)
            // set onError to something callable, will receive error array
            'onError' => null,
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
        register_shutdown_function(array($this,'onShutdown'));
        return;
    }

    /**
     * Get backtrace
     *
     * To get trace from within shutdown function utilizes xdebug_get_function_stack() if available
     *
     * @return array
     */
    public function backtrace()
    {
        $error = error_get_last();
        if ($this->uncaughtException) {
            $backtrace = $this->uncaughtException->getTrace();
            array_unshift($backtrace, array(
                'file' => $this->uncaughtException->getFile(),
                'line' => $this->uncaughtException->getLine(),
            ));
        } elseif ($error && in_array($error['type'], $this->errCategories['fatal']) && extension_loaded('xdebug')) {
            unset($error['type']);
            $backtrace = xdebug_get_function_stack();
            $backtrace = array_reverse($backtrace);
            array_pop($backtrace);
            foreach ($backtrace as &$frame) {
                if (isset($frame['class'])) {
                    $frame['type'] = $frame['type'] === 'dynamic' ? '->' : '::';
                }
                if (isset($frame['include_filename'])) {
                    $frame['function'] = 'include or require';
                }
            }
            unset($frame);
            $backtrace = $this->backtraceRemoveInternal($backtrace);
            $backtrace[0] = $error;
        } else {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $backtrace = $this->backtraceRemoveInternal($backtrace);
        }
        return $this->normalizeTrace($backtrace);
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
    public function handleError($errType, $errMsg, $file, $line, $vars = array())
    {
        $error = $this->buildError($errType, $errMsg, $file, $line, $vars);
        $errorReporting = $this->cfg['errorReporting'] === 'system'
            ? error_reporting() // note:  will return 0 if error suppression is active in call stack (via @ operator)
                                //  our shutdown function unsupresses fatal errors
            : $this->cfg['errorReporting'];
        $continueToPrev = $this->cfg['continueToPrevHandler'] && $this->prevErrorHandler;
        $isHandledType = $errType & $errorReporting;
        if (!$isHandledType) {
            // not handled
            //   if cfg['errorReporting'] == 'system', error could simply be suppressed
            if ($continueToPrev) {
                call_user_func($this->prevErrorHandler, $errType, $errMsg, $error['file'], $error['line'], $vars);
                return true;
            }
            return false;   // return false to continue to "normal" error handler
        }
        if (!$error['isSuppressed']) {
            $error['logError'] = $error['isFirstOccur'];
            // suppressed error should not clear error caller
            $this->data['lastError'] = $error;
            $this->data['errorCaller'] = array();
            $this->eventManager->publish('errorHandler.error', $error);
        }
        $this->data['errors'][ $error['hash'] ] = $error;
        if ($continueToPrev) {
            call_user_func($this->prevErrorHandler, $error['type'], $error['message'], $error['file'], $error['line'], $vars);
            return true;
        }
        if ($error['logError']) {
            return false;   // return false to continue to "normal" error handler
                            // PHP will log the error
        }
        return true;
    }

    /**
     * Handle uncaught exceptions
     *
     * This isn't strictly necesssary...  uncaught exceptions  are a fatal error, which we can handle...
     * However..  An backtrace sure is nice...
     *    a) catching backtrace via shutdown function only possible if xdebug installed
     *    b) xdebug_get_function_stack's magic doesn't seem to powerless for uncaught exceptions!
     *
     * @param Exception|Throwable $exception exception to handle
     *
     * @return void
     */
    public function handleException($exception)
    {
        // lets store the exception so we can use the backtrace it provides
        $this->uncaughtException = $exception;
        $this->handleError(
            E_ERROR,
            'Uncaught exception \''.get_class($exception).'\' with message '.$exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        $this->uncaughtException = null;
    }

    /**
     * Catch Fatal Error
     *
     * @return void
     */
    public function onShutdown()
    {
        if (!$this->registered) {
            return;
        }
        /*
            error_get_last() is php >= 5.2
            we're using namespaces (5.3), so this requiement is met
        */
        $error = error_get_last();
        if (in_array($error['type'], $this->errCategories['fatal'])) {
            /*
                found in wild:
                @include(some_file_with_parse_error)
                which will trigger a fatal error (here we are),
                but error_reporting() will return 0 due to the @ operator
                unsupress fatal error here
            */
            error_reporting(E_ALL);
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
        return;
    }

    /**
     * Register this error handler and shutdown function
     *
     * @return void
     */
    public function register()
    {
        if ($this->registered) {
            return;
        }
        $this->prevDisplayErrors = ini_set('display_errors', 0);
        $this->prevErrorHandler = set_error_handler(array($this, 'handleError'));
        $this->prevExceptionHandler = set_exception_handler(array($this, 'handleException'));
        $this->registered = true;   // used by this->shutdownFunction()
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
            /*
                Replace - not append - subscriber set via setCfg
            */
            if (isset($this->cfg['onError'])) {
                $this->eventManager->unsubscribe('errorHandler.error', $this->cfg['onError']);
            }
            $this->eventManager->subscribe('errorHandler.error', $values['onError']);
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
     * @param array   $caller (default) null : determine automatically
     *                        empty value (false, "", 0, array(): clear current value
     *                        array() : manually set value
     * @param integer $offset (optional) if determining automatically : adjust how many frames to go back
     *
     * @return void
     */
    public function setErrorCaller($caller = null, $offset = 0)
    {
        if ($caller === null) {
            $backtrace = version_compare(PHP_VERSION, '5.4.0', '>=')
                ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $offset + 3)
                : debug_backtrace(false);   // don't provide object
            $i = isset($backtrace[$offset+1])
                ? $offset + 1
                : count($backtrace) - 1;
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
        if (!$this->registered) {
            return;
        }
        /*
            set and restore error handler to determine the current error handler
        */
        $errHandlerCur = set_error_handler(array($this, 'handleError'));
        restore_error_handler();
        if ($errHandlerCur == array($this, 'handleError')) {
            // we are the current error handler
            restore_error_handler();
        }
        /*
            set and restore exception handler to determine the current error handler
        */
        $exHandlerCur = set_exception_handler(array($this, 'handleException'));
        restore_exception_handler();
        if ($exHandlerCur == array($this, 'handleException')) {
            // we are the current exception handler
            restore_exception_handler();
        }
        ini_set('display_errors', $this->prevDisplayErrors);
        $this->prevErrorHandler = null;
        $this->prevExceptionHandler = null;
        $this->registered = false;  // used by shutdownFunction()
        return;
    }

    /**
     * Remove internal frames from backtrace
     *
     * @param array $backtrace backtrace
     *
     * @return array
     */
    protected function backtraceRemoveInternal($backtrace)
    {
        for ($i = count($backtrace) - 1; $i >= 0; $i--) {
            $frame = $backtrace[$i];
            if (isset($frame['class']) && strpos($frame['class'], __NAMESPACE__.'\\') === 0) {
                break;
            }
        }
        return array_slice($backtrace, $i);
    }

    /**
     * "Normalize" backtrace from debug_backtrace() or xdebug_get_function_stack();
     *
     * @param array $backtrace trace/stack from debug_backtrace() or xdebug_Get_function_stack()
     *
     * @return array
     */
    protected function normalizeTrace($backtrace)
    {
        $backtraceNew = array();
        $frameDefault = array(
            'file' => null,
            'line' => null,
            'function' => null,
            'class' => null,
            'type' => null,
        );
        $funcsSkip = array('call_user_func','call_user_func_array');
        $funcsSkipRegex = '/^('.implode('|', $funcsSkip).')[:\(\{]/';
        for ($i = 0, $count=count($backtrace); $i < $count; $i++) {
            $frame = array_merge($frameDefault, $backtrace[$i]);
            $frame = array_intersect_key($frame, $frameDefault);
            if (in_array($frame['function'], $funcsSkip) || preg_match($funcsSkipRegex, $frame['function'])) {
                $backtraceNew[count($backtraceNew) - 1]['file'] = $frame['file'];
                $backtraceNew[count($backtraceNew) - 1]['line'] = $frame['line'];
                continue;
            }
            $frame['function'] = preg_match('/\{closure\}$/', $frame['function'])
                ? $frame['function']
                : $frame['class'].$frame['type'].$frame['function'];
            if (!$frame['function']) {
                unset($frame['function']);
            }
            unset($frame['class'], $frame['type']);
            $backtraceNew[] = $frame;
        }
        return $backtraceNew;
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
     * @return Event
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
            'backtrace' => array(), // only for fatal type errors, and only if xdebug is enabled
            'exception' => $this->uncaughtException,  // non-null if error is uncaught-exception
            'hash'      => null,
            'isFirstOccur'  => true,
            'isSuppressed'  => error_reporting() === 0,
            'logError'      => false,
        );
        $hash = $this->errorHash($errorValues);
        $isFirstOccur = !isset($this->data['errors'][$hash]);
        if (!empty($this->data['errorCaller'])) {
            $errorValues['file'] = $this->data['errorCaller']['file'];
            $errorValues['line'] = $this->data['errorCaller']['line'];
        }
        if (in_array($errType, array(E_ERROR, E_USER_ERROR)) && extension_loaded('xdebug')) {
            $backtrace = $this->backtrace();
            if (!array_intersect_assoc($backtrace[0], array('file'=>$file, 'line'=>$line))) {
                array_unshift($backtrace, array(
                    'file'=>$file,
                    'line'=>$line,
                ));
            }
            $errorValues['backtrace'] = $backtrace;
        }
        $errorValues = array_merge($errorValues, array(
            'hash' => $hash,
            'isFirstOccur' => $isFirstOccur,
            // if any instance of this error was not supprssed, reflect that
            'isSuppressed' => !$isFirstOccur && !$this->data['errors'][$hash]['isSuppressed']
                ? false
                : $errorValues['isSuppressed'],
        ));
        return new Event($this, $errorValues);
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

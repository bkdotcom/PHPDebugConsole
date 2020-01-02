<?php
/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 */

namespace bdk;

use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use ReflectionObject;

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
        E_STRICT            => 'Runtime Notice (E_STRICT)', // php 5.0 :  2048
        E_RECOVERABLE_ERROR => 'Recoverable Error',         // php 5.2 :  4096
        E_DEPRECATED        => 'Deprecated',                // php 5.3 :  8192
        E_USER_DEPRECATED   => 'User Deprecated',           // php 5.3 : 16384
    );
    protected $errCategories = array(
        'deprecated'    => array( E_DEPRECATED, E_USER_DEPRECATED ),
        'error'         => array( E_USER_ERROR, E_RECOVERABLE_ERROR ),
        'notice'        => array( E_NOTICE, E_USER_NOTICE ),
        'strict'        => array( E_STRICT ),
        'warning'       => array( E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING ),
        'fatal'         => array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR ),
    );
    protected $userErrors = array(
        E_USER_DEPRECATED,
        E_USER_ERROR,
        E_USER_NOTICE,
        E_USER_WARNING,
    );
    protected $inShutdown = false;
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
            'continueToPrevHandler' => true,    // whether to continue to previously defined handler (if there is/was a prev error handler)
                                                //   will not continue if error event propagation stopped
            'errorReporting' => E_ALL | E_STRICT,   // what errors are handled by handler? bitmask or "system" to use runtime value
                                                    //   note that if using "system", suppressed errors (via @ operator) will not be handled (we'll still handle fatal category)
            // shortcut for subscribing to errorHandler.error Event
            //   will receive error Event object
            'onError' => null,
            'onEUserError' => 'normal', // only applicable if we're not continuing to a prev error handler
                                    // (continueToPrevHandler = false, there's no previous handler, or propagation stopped)
                                    //   'continue' : forces continueToNormal = false (script will continue)
                                    //   'log' : if propagation not stopped, call error_log()
                                    //         continue script execution
                                    //   'normal' : forces continueToNormal = true;
                                    //   null : use error's continueToNormal value
        );
        // Initialize self::$instance if not set
        //    so that self::getInstance() will always return original instance
        //    as opposed the the last instance created with new ErrorHandler()
        if (!isset(self::$instance)) {
            self::$instance = $this;
        }
        $this->setCfg($cfg);
        $this->register();
        // easier to maintain subscription to php.shutdown event and check this->registered value
        // than to subscribe with register() and unsub with unRegister()
        $this->eventManager->subscribe('php.shutdown', array($this, 'onShutdown'), PHP_INT_MAX);
        return;
    }

    /**
     * Get backtrace
     *
     * To get trace from within shutdown function utilizes xdebug_get_function_stack() if available
     *
     * @param array|Event|Exception $error (optional) error details if getting error backtrace
     *
     * @return array
     */
    public function backtrace($error = null)
    {
        $exception = null;
        if ($error instanceof \Exception) {
            $exception = $error;
        } elseif ($error) {
            // array or Event
            $exception = $error['exception'];
        }
        if ($exception) {
            $backtrace = $exception->getTrace();
            \array_unshift($backtrace, array(
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));
        } elseif ($this->inShutdown) {
            if (!\extension_loaded('xdebug')) {
                return array();
            }
            $backtrace = $this->xdebugGetFunctionStack();
            $backtrace = \array_reverse($backtrace);
            $backtrace = $this->backtraceRemoveInternal($backtrace);
            $errorFileLine = array(
                'file' => $error['file'],
                'line' => $error['line'],
            );
            \array_pop($backtrace);   // pointless entry that xdebug_get_function_stack() includes
            if (empty($backtrace)) {
                return array();
            }
            if (\array_intersect_assoc($errorFileLine, $backtrace[0]) !== $errorFileLine) {
                \array_unshift($backtrace, $errorFileLine);
            }
        } else {
            $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $backtrace = $this->backtraceRemoveInternal($backtrace);
        }
        return $this->normalizeTrace($backtrace);
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
     * Retrieve a configuration value
     *
     * @param string $key what to get
     *
     * @return mixed
     */
    public function getCfg($key = null)
    {
        if (!\strlen($key)) {
            return $this->cfg;
        }
        if (isset($this->cfg[$key])) {
            return $this->cfg[$key];
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
     * @link   http://php.net/manual/en/function.set-error-handler.php
     * @link   http://php.net/manual/en/language.operators.errorcontrol.php
     */
    public function handleError($errType, $errMsg, $file, $line, $vars = array())
    {
        $error = $this->buildError($errType, $errMsg, $file, $line, $vars);
        $errorReporting = $this->cfg['errorReporting'] === 'system'
            ? \error_reporting() // note:  will return 0 if error suppression is active in call stack (via @ operator)
                                //  our shutdown function unsupresses fatal errors
            : $this->cfg['errorReporting'];
        $isHandledType = $errType & $errorReporting;
        if (!$isHandledType) {
            // not handled
            //   if cfg['errorReporting'] == 'system', error could simply be suppressed
            return $this->continueToPrev($error);
        }
        if (!$error['isSuppressed']) {
            // suppressed error should not clear error caller
            $this->data['lastError'] = $error;
            $this->data['errorCaller'] = array();
            $this->eventManager->publish('errorHandler.error', $error);
        }
        $this->data['errors'][ $error['hash'] ] = $error;
        if ($error['continueToPrevHandler'] && $this->prevErrorHandler && !$error->isPropagationStopped()) {
            return $this->continueToPrev($error);
        }
        if (\in_array($error['type'], array(E_USER_ERROR, E_RECOVERABLE_ERROR))) {
            $this->onUserError($error);
        }
        if ($error['continueToNormal']) {
            // PHP will log the error
            // if E_USER_ERROR, php will exit()
            return false;
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
        \http_response_code(500);
        $this->handleError(
            E_ERROR,
            'Uncaught exception \''.\get_class($exception).'\' with message '.$exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        $this->uncaughtException = null;
        if ($this->cfg['continueToPrevHandler'] && $this->prevExceptionHandler) {
            \call_user_func($this->prevErrorHandler, $exception);
        }
    }

    /**
     * Send string to error_log()
     *
     * @param Event $error Error event to log
     *
     * @return boolean
     */
    public function log($error)
    {
        $str = 'PHP User Error:  '.$error['message'].' in '.$error['file'].' on line '.$error['line'];
        return \error_log($str);
    }

    /**
     * php.shutdown event subscriber
     *
     * Used to handle fatal errors
     *
     * Test fatal error handling by publishing 'php.shutdown' event with error value
     *
     * @param Event $event php.shutdown event
     *
     * @return void
     */
    public function onShutdown(Event $event)
    {
        $this->inShutdown = true;
        if (!$this->registered) {
            return;
        }
        $error = $event['error'] ?: \error_get_last();
        if (!$error) {
            return;
        }
        if (\in_array($error['type'], $this->errCategories['fatal'])) {
            /*
                found in wild:
                @include(some_file_with_parse_error)
                which will trigger a fatal error (here we are),
                but error_reporting() will return 0 due to the @ operator
                unsupress fatal error here
            */
            \error_reporting(E_ALL | E_STRICT);
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
        /*
            Find the fatal error/uncaught-exception and attach to shutdown event
        */
        foreach ($this->data['errors'] as $error) {
            if ($error['category'] === 'fatal') {
                $event['error'] = $error;
                break;
            }
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
        $this->prevDisplayErrors = \ini_set('display_errors', 0);
        $this->prevErrorHandler = \set_error_handler(array($this, 'handleError'));
        $this->prevExceptionHandler = \set_exception_handler(array($this, 'handleException'));
        $this->registered = true;   // used by this->onShutdown()
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
        if (\is_string($mixed)) {
            $key = $mixed;
            $ret = isset($this->cfg[$key])
                ? $this->cfg[$key]
                : null;
            $values = array(
                $key => $newVal,
            );
        } elseif (\is_array($mixed)) {
            $ret = \array_intersect_key($this->cfg, $mixed);
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
        $this->cfg = \array_merge($this->cfg, $values);
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
            $backtrace = \version_compare(PHP_VERSION, '5.4.0', '>=')
                ? \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $offset + 3)
                : \debug_backtrace(false);   // don't provide object
            $i = isset($backtrace[$offset+1])
                ? $offset + 1
                : \count($backtrace) - 1;
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
        $errHandlerCur = \set_error_handler(array($this, 'handleError'));
        \restore_error_handler();
        if ($errHandlerCur == array($this, 'handleError')) {
            // we are the current error handler
            \restore_error_handler();
        }
        /*
            set and restore exception handler to determine the current error handler
        */
        $exHandlerCur = \set_exception_handler(array($this, 'handleException'));
        \restore_exception_handler();
        if ($exHandlerCur == array($this, 'handleException')) {
            // we are the current exception handler
            \restore_exception_handler();
        }
        \ini_set('display_errors', $this->prevDisplayErrors);
        $this->prevErrorHandler = null;
        $this->prevExceptionHandler = null;
        $this->registered = false;  // used by $this->onShutdown()
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
        for ($i = \count($backtrace) - 1; $i > 0; $i--) {
            $frame = $backtrace[$i];
            if (isset($frame['class']) && $frame['class'] === __CLASS__) {
                break;
            }
        }
        if ($backtrace[$i]['function'] == 'onShutdown') {
            /*
                We got here via php.shutdown event (fatal error)
                skip over PubSub internals
            */
            $refObj = new ReflectionObject($this->eventManager);
            $filepath = $refObj->getFilename();
            while (isset($backtrace[$i+1]['file']) && $backtrace[$i+1]['file'] == $filepath) {
                $i++;
            }
        }
        $i++;
        return \array_slice($backtrace, $i);
    }

    /**
     * Pass error to prevErrorHandler (if there was one)
     *
     * @param Event $error Event instance
     *
     * @return boolean
     */
    protected function continueToPrev(Event $error)
    {
        if (!$this->prevErrorHandler) {
            return false;
        }
        return \call_user_func(
            $this->prevErrorHandler,
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line'],
            $error['vars']
        );
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
        $funcsSkipRegex = '/^('.\implode('|', $funcsSkip).')[:\(\{]/';
        for ($i = 0, $count=\count($backtrace); $i < $count; $i++) {
            $frame = \array_merge($frameDefault, $backtrace[$i]);
            $frame = \array_intersect_key($frame, $frameDefault);
            if (\in_array($frame['function'], $funcsSkip) || \preg_match($funcsSkipRegex, $frame['function'])) {
                $backtraceNew[count($backtraceNew) - 1]['file'] = $frame['file'];
                $backtraceNew[count($backtraceNew) - 1]['line'] = $frame['line'];
                continue;
            }
            if (\in_array($frame['type'], array('dynamic','static'))) {
                // xdebug_get_function_stack
                $frame['type'] = $frame['type'] === 'dynamic' ? '->' : '::';
            }
            if (isset($backtrace[$i]['include_filename'])) {
                // xdebug_get_function_stack
                $frame['function'] = 'include or require';
            } else {
                $frame['function'] = \preg_match('/\{closure\}$/', $frame['function'])
                    ? $frame['function']
                    : $frame['class'].$frame['type'].$frame['function'];
            }
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
            if (\in_array($errType, $errTypes)) {
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
            'continueToNormal' => false,    // aka, let PHP do its thing (log error)
            'continueToPrevHandler' => $this->cfg['continueToPrevHandler'] && $this->prevErrorHandler,
            'exception' => $this->uncaughtException,  // non-null if error is uncaught-exception
            'hash'          => null,
            'isFirstOccur'  => true,
            'isHtml'        => \filter_var(\ini_get('html_errors'), FILTER_VALIDATE_BOOLEAN)
                && !\in_array($errType, $this->userErrors) && !$this->uncaughtException,
            'isSuppressed'  => false,
        );
        $hash = $this->errorHash($errorValues);
        $isFirstOccur = !isset($this->data['errors'][$hash]);
        // if any instance of this error was not supprssed, reflect that
        if ($errorValues['isHtml']) {
            $errorValues['message'] = \str_replace('<a ', '<a target="phpRef" ', $errorValues['message']);
        }
        $isSuppressed = !$isFirstOccur && !$this->data['errors'][$hash]['isSuppressed']
            ? false
            : \error_reporting() === 0;
        if (!empty($this->data['errorCaller'])) {
            $errorValues['file'] = $this->data['errorCaller']['file'];
            $errorValues['line'] = $this->data['errorCaller']['line'];
        }
        if (\in_array($errType, array(E_ERROR, E_USER_ERROR))) {
            // will return empty unless xdebug extension installed/enabled
            $errorValues['backtrace'] = $this->backtrace($errorValues);
        }
        $errorValues = \array_merge($errorValues, array(
            'continueToNormal' => !$isSuppressed && $isFirstOccur,
            'hash' => $hash,
            'isFirstOccur' => $isFirstOccur,
            'isSuppressed' => $isSuppressed,
        ));
        return new Event($this, $errorValues);
    }

    /**
     * Generate hash used to uniquely identify this error
     *
     * @param array $errorValues error array
     *
     * @return string hash
     */
    protected function errorHash($errorValues)
    {
        $errMsg = $errorValues['message'];
        // (\(.*?)\d+(.*?\))    "(tried to allocate 16384 bytes)" -> "(tried to allocate xxx bytes)"
        $errMsg = \preg_replace('/(\(.*?)\d+(.*?\))/', '\1x\2', $errMsg);
        // "blah123" -> "blahxxx"
        $errMsg = \preg_replace('/\b([a-z]+\d+)+\b/', 'xxx', $errMsg);
        // "-123.123" -> "xxx"
        $errMsg = \preg_replace('/\b[\d.-]{4,}\b/', 'xxx', $errMsg);
        // remove "comments"..  this allows throttling email, while still adding unique info to user errors
        $errMsg = \preg_replace('/\s*##.+$/', '', $errMsg);
        $hash = \md5($errorValues['file'].$errorValues['line'].$errorValues['type'].$errMsg);
        return $hash;
    }

    /**
     * Handle E_USER_ERROR
     *
     * Should script terminate, or continue?
     *
     * @param Event $error errorHandler.error event
     *
     * @return void
     */
    protected function onUserError(Event $error)
    {
        switch ($this->cfg['onEUserError']) {
            case 'continue':
                $error['continueToNormal'] = false;
                break;
            case 'log':
                // log the error, but continue script
                if (!$error->isPropagationStopped() && $error['continueToNormal']) {
                    $this->log($error);
                }
                $error['continueToNormal'] = false;
                break;
            case 'normal':
                // force continueToNormal
                $error['continueToNormal'] = true;
                break;
            default:
                /*
                    no special consideration
                    unless errorHandler.error subscriber changes `continueToNormal` value,
                    script will be halted
                */
        }
    }

    /**
     * wrapper for xdebug_get_function_stack
     * accounts for bug 1529 (may report incorrect file)
     *
     * @return array
     * @see    https://bugs.xdebug.org/view.php?id=1529
     */
    protected static function xdebugGetFunctionStack()
    {
        $stack = \xdebug_get_function_stack();
        $xdebugVer = \phpversion('xdebug');
        if (\version_compare($xdebugVer, '2.6.0', '<')) {
            $count = \count($stack);
            for ($i = 0; $i < $count; $i++) {
                $frame = $stack[$i];
                $function = isset($frame['function'])
                    ? $frame['function']
                    : null;
                if ($function === '__get') {
                    // wrong file!
                    $prev = $stack[$i - 1];
                    $stack[$i]['file'] = isset($prev['include_filename'])
                        ? $prev['include_filename']
                        : $prev['file'];
                }
            }
        }
        return $stack;
    }
}

<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk;

use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use ReflectionClass;
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
        'lastErrors'     => array(),    // contains up to two errors: suppressed & unsuppressed
                                        // lastError[0] is the most recent error
        'uncaughtException' => null,    // error constructor will pull this
    );
    protected $inShutdown = false;
    protected $registered = false;
    protected $prevDisplayErrors = null;
    protected $prevErrorHandler = null;
    protected $prevExceptionHandler = null;
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
            'errorFactory' => array($this, 'errorFactory'),
            'errorReporting' => E_ALL | E_STRICT,   // what errors are handled by handler? bitmask or "system" to use runtime value
                                                    //   note that if using "system", suppressed errors (via @ operator) will not be handled (we'll still handle fatal category)
            // shortcut for subscribing to errorHandler.error Event
            //   will receive error Event object
            'onError' => null,
            'onEUserError' => 'normal', // only applicable if we're not continuing to a prev error handler
                                    // (continueToPrevHandler = false, there's no previous handler, or propagation stopped)
                                    //   'continue' : forces error[continueToNormal] = false (script will continue)
                                    //   'log' : if propagation not stopped, call error_log()
                                    //         continue script execution
                                    //   'normal' : forces error[continueToNormal] = true;
                                    //   null : use error's error[continueToNormal] value
        );
        // Initialize self::$instance if not set
        //    so that self::getInstance() will always return original instance
        //    as opposed the the last instance created with new ErrorHandler()
        if (!isset(self::$instance)) {
            self::$instance = $this;
        }
        $this->setCfg($cfg);
        $this->register();
        $this->eventManager->subscribe('php.shutdown', array($this, 'onShutdown'), PHP_INT_MAX);
        return;
    }

    /**
     * Helper method to get backtrace
     *
     * Utilizes `xdebug_get_function_stack()` (if available) to get backtrace in shutdown phase
     * When called internally, internal frames are removed
     *
     * @param Error|Exception $error (optional) Error instance if getting error backtrace
     *
     * @return array
     */
    public function backtrace($error = null)
    {
        $exception = null;
        if ($error instanceof \Exception) {
            $exception = $error;
        } elseif ($error instanceof Error) {
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
     * @param string $key  what to get
     * @param string $hash if key == 'error', specify error hash
     *
     * @return mixed
     */
    public function get($key = null, $hash = null)
    {
        if ($key == 'error') {
            return isset($this->data['errors'][$hash])
                ? $this->data['errors'][$hash]
                : false;
        }
        if ($key == 'lastError') {
            return $this->getLastError();
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
     * Get information about last error
     *
     * @param boolean $inclSuppressed (false)
     *
     * @return null|array
     */
    public function getLastError($inclSuppressed = false)
    {
        if (!$inclSuppressed) {
            // (default) skip over suppressed error to find last non-suppressed
            foreach ($this->data['lastErrors'] as $error) {
                if (!$error['isSuppressed']) {
                    return $error->getValues();
                }
            }
        } elseif ($this->data['lastErrors']) {
            return $this->data['lastErrors'][0]->getValues();
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
     * @param integer $errType error lavel / type (one of PHP's E_* constants)
     * @param string  $errMsg  the error message
     * @param string  $file    filepath the error was raised in
     * @param integer $line    the line the error was raised in
     * @param array   $vars    active symbol table at point error occured
     *
     * @return boolean
     * @link   http://php.net/manual/en/function.set-error-handler.php
     * @link   http://php.net/manual/en/language.operators.errorcontrol.php
     */
    public function handleError($errType, $errMsg, $file, $line, $vars = array())
    {
        $error = $this->cfg['errorFactory']($this, $errType, $errMsg, $file, $line, $vars);
        if (!$this->isErrTypeHandled($errType)) {
            // not handled
            //   if cfg['errorReporting'] == 'system', error could simply be suppressed
            // return false to continue to "normal" error handler
            return $this->continueToPrev($error);
        }
        $this->storeLastError($error);
        if (!$error['isSuppressed']) {
            // only clear error caller via non-suppressed error
            $this->data['errorCaller'] = array();
            // only publish event for non-suppressed error
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
     * This isn't strictly necesssary...  uncaught exceptions are a fatal error, which we can handle...
     * However:
     *   * catching backtrace via shutdown function only possible if xdebug installed
     *   * xdebug_get_function_stack's magic seems powerless for uncaught exceptions!
     *
     * @param \Exception|\Throwable $exception exception to handle
     *
     * @return void
     */
    public function handleException($exception)
    {
        // lets store the exception so we can use the backtrace it provides
        //   error constructor will pull this
        $this->data['uncaughtException'] = $exception;
        \http_response_code(500);
        $this->handleError(
            E_ERROR,
            'Uncaught exception \'' . \get_class($exception) . '\' with message ' . $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        $this->data['uncaughtException'] = null;
        if ($this->cfg['continueToPrevHandler'] && $this->prevExceptionHandler) {
            \call_user_func($this->prevErrorHandler, $exception);
        }
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
        if (\is_array($error)) {
            $error = $this->cfg['errorFactory'](
                $this,
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line'],
                isset($error['vars'])
                    ? $error['vars']
                    : array()
            );
        }
        if ($error->isFatal()) {
            /*
                found in wild:
                @include(some_file_with_parse_error)
                which will trigger a fatal error (here we are),
                but error_reporting() will return 0 due to the @ operator
                unsuppress fatal error here
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
        $this->prevDisplayErrors = \ini_set('display_errors', '0');
        $this->prevErrorHandler = \set_error_handler(array($this, 'handleError'));
        $this->prevExceptionHandler = \set_exception_handler(array($this, 'handleException'));
        $this->registered = true;   // used by this->onShutdown()
        return;
    }

    /**
     * Set one or more config values
     *
     *    `setCfg('key', 'value')`
     *    `setCfg(array('k1'=>'v1', 'k2'=>'v2'))`
     *
     * @param string|array $mixed  key=>value array or key
     * @param mixed        $newVal value
     *
     * @return mixed old value(s)
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
     * Set the calling file/line for next error.
     * This override will apply until cleared or error occurs
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
            $i = isset($backtrace[$offset + 1])
                ? $offset + 1
                : \count($backtrace) - 1;
            $caller = isset($backtrace[$i]['file'])
                ? $backtrace[$i]
                : $backtrace[$i + 1]; // likely called via call_user_func.. need to go one more to get calling file & line
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
     * Un-register this error handler
     *
     * Restores previous error handler
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
            while (isset($backtrace[$i + 1]['file']) && $backtrace[$i + 1]['file'] == $filepath) {
                $i++;
            }
        }
        $i++;
        return \array_slice($backtrace, $i);
    }

    /**
     * Pass error to prevErrorHandler (if there was one)
     *
     * @param Error $error Error instance
     *
     * @return boolean
     */
    protected function continueToPrev(Error $error)
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
     * Create Error instance
     *
     * @param self    $handler ErrorHandler instance
     * @param integer $errType the level of the error
     * @param string  $errMsg  the error message
     * @param string  $file    filepath the error was raised in
     * @param string  $line    the line the error was raised in
     * @param array   $vars    active symbol table at point error occured
     *
     * @return Error
     */
    protected function errorFactory(self $handler, $errType, $errMsg, $file, $line, $vars = array())
    {
        return new Error($handler, $errType, $errMsg, $file, $line, $vars);
    }

    /**
     * Test if error type is handled
     *
     * @param integer $errType error type
     *
     * @return boolean
     */
    protected function isErrTypeHandled($errType)
    {
        $errorReporting = $this->cfg['errorReporting'] === 'system'
            ? \error_reporting() // note:  will return 0 if error suppression is active in call stack (via @ operator)
                                //  our shutdown function unsupresses fatal errors
            : $this->cfg['errorReporting'];
        $isHandledType = $errType & $errorReporting;
        return $isHandledType;
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
        $funcsSkipRegex = '/^(' . \implode('|', $funcsSkip) . ')[:\(\{]/';
        for ($i = 0, $count = \count($backtrace); $i < $count; $i++) {
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
                    : $frame['class'] . $frame['type'] . $frame['function'];
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
     * Handle E_USER_ERROR
     *
     * Should script terminate, or continue?
     *
     * @param Error $error Errorinstance
     *
     * @return void
     */
    protected function onUserError(Error $error)
    {
        switch ($this->cfg['onEUserError']) {
            case 'continue':
                $error['continueToNormal'] = false;
                break;
            case 'log':
                // log the error, but continue script
                if (!$error->isPropagationStopped() && $error['continueToNormal']) {
                    $error->log();
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
     * Store last error
     *
     * We store up to two errors...  so that we can return last suppressed error (if desired)
     *
     * @param Error $error error instance
     *
     * @return void
     */
    private function storeLastError(Error $error)
    {
        $this->data['lastErrors'] = \array_filter($this->data['lastErrors'], function (Error $error) {
            return !$error['isSuppressed'];
        });
        $this->data['lastErrors'] = \array_slice($this->data['lastErrors'], 0, 1);
        \array_unshift($this->data['lastErrors'], $error);
    }

    /**
     * wrapper for xdebug_get_function_stack
     * accounts for bug 1529 (may report incorrect file)
     *
     * @return array
     * @see    https://bugs.xdebug.org/view.php?id=1529
     */
    protected function xdebugGetFunctionStack()
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

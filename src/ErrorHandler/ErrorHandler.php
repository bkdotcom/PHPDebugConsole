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
use Exception;
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
    protected $shutdownError;   // array from error_get_last()
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
            'continueToPrevHandler' => true,    // whether to continue to previously defined handler (if there is/was a prev handler)
                                                //   prev handler will not be called if error event propagation stopped
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
     * @return Error|null
     */
    public function getLastError($inclSuppressed = false)
    {
        if (!$inclSuppressed) {
            // (default) skip over suppressed error to find last non-suppressed
            foreach ($this->data['lastErrors'] as $error) {
                if (!$error['isSuppressed']) {
                    return $error;
                }
            }
        } elseif ($this->data['lastErrors']) {
            return $this->data['lastErrors'][0];
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
            return $this->continueToPrevHandler($error);
        }
        $this->storeLastError($error);
        if (!$error['isSuppressed']) {
            // only clear error caller via non-suppressed error
            $this->data['errorCaller'] = array();
            // only publish event for non-suppressed error
            $this->eventManager->publish('errorHandler.error', $error);
        }
        $this->data['errors'][ $error['hash'] ] = $error;
        return $this->continueToPrevHandler($error);
    }

    /**
     * Handle uncaught exceptions
     *
     * This isn't strictly necesssary...  uncaught exceptions are a fatal error, which we can handle...
     * However:
     *   * catching backtrace via shutdown function only possible if xdebug installed
     *   * xdebug_get_function_stack's magic seems powerless for uncaught exceptions!
     *
     * @param Exception|\Throwable $exception exception to handle
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
        if ($error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR)) {
            /*
                found in wild:
                @include(some_file_with_parse_error)
                which will trigger a fatal error (here we are),
                but error_reporting() will return 0 due to the @ operator
                unsuppress fatal error here
            */
            \error_reporting(E_ALL | E_STRICT);
            $this->shutdownError = $error;
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line'], isset($error['vars'])
                ? $error['vars']
                : array());
            /*
                Find the fatal error/uncaught-exception and attach to shutdown event
            */
            foreach ($this->data['errors'] as $error) {
                if ($error['category'] === 'fatal') {
                    $event['error'] = $error;
                    break;
                }
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
     * Conditioanlly pass error or exception to previously defined handler
     *
     * @param Error $error Error instance
     *
     * @return boolean
     * @throws Exception
     */
    protected function continueToPrevHandler(Error $error)
    {
        if (\in_array($error['type'], array(E_USER_ERROR, E_RECOVERABLE_ERROR))) {
            // set error['continueToNormal']
            $this->handleUserError($error);
        }
        if (!$error['continueToPrevHandler'] || $error->isPropagationStopped()) {
            return !$error['continueToNormal'];
        }
        if ($error['exception']) {
            if (!$this->prevExceptionHandler) {
                return !$error['continueToNormal'];
            }
            /*
                re-throw exception vs calling handler directly
            */
            \restore_exception_handler();
            throw $error['exception'];
        } else {
            if (!$this->prevErrorHandler) {
                return !$error['continueToNormal'];
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
     * Handle E_USER_ERROR and E_RECOVERABLE_ERROR
     *
     * Should script terminate, or continue?
     *
     * @param Error $error Errorinstance
     *
     * @return void
     */
    protected function handleUserError(Error $error)
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
                // for a userError, php will log error and script will halt
                $error['continueToNormal'] = true;
                break;
            default:
                /*
                don't change continueToNormal value
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
}

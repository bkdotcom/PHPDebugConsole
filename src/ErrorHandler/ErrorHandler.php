<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0.1
 */

namespace bdk;

use bdk\Backtrace;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;

/**
 * General-purpose error handler which supports fatal errors
 *
 * Able to register multiple onError "callback" functions
 *
 * @property \bdk\Backtrace $backtrace Backtrace instance
 */
class ErrorHandler
{

    const EVENT_ERROR = 'errorHandler.error';

    /** @var EventManager */
    public $eventManager;
    /** @var array */
    protected $cfg = array();
    /** @var array */
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

    /** @var Backtrace */
    private $backtrace;

    /**
     * Temp store error exception caught/triggered inside __toString
     *
     * @var \Exception|\Throwable|null
     */
    private $toStringException = null;

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
            'errorThrow' => 0,          // bitmask: error types that should converted to ErrorException and thrown
            'onError' => null,          // shortcut for subscribing to errorHandler.error Event
                                        //   will receive error Event object
            'onEUserError' => 'normal', // only applicable if we're not continuing to a prev error handler
                                    // (continueToPrevHandler = false, there's no previous handler, or propagation stopped)
                                    //   'continue' : forces error[continueToNormal] = false (script will continue)
                                    //   'log' : if propagation not stopped, call error_log()
                                    //         continue script execution
                                    //   'normal' : forces error[continueToNormal] = true;
                                    //   null : use error's error[continueToNormal] value
            'suppressNever' => E_ERROR | E_PARSE | E_RECOVERABLE_ERROR | E_USER_ERROR,
        );
        // Initialize self::$instance if not set
        //    so that self::getInstance() will always return original instance
        //    as opposed the the last instance created with new ErrorHandler()
        if (!isset(self::$instance)) {
            self::$instance = $this;
        }
        $this->setCfg($cfg);
        $this->register();
        $this->eventManager->subscribe(EventManager::EVENT_PHP_SHUTDOWN, array($this, 'onShutdown'), PHP_INT_MAX);
    }

    /**
     * Magic method to get inaccessible / undefined properties
     * Lazy load child classes
     *
     * @param string $property property name
     *
     * @return mixed property value
     */
    public function __get($property)
    {
        /*
            Check getter method
        */
        $getter = 'get' . \ucfirst($property);
        if (\method_exists($this, $getter)) {
            return $this->{$getter}();
        }
        return null;
    }

    /**
     * What error level are we handling
     *
     * @return int
     */
    public function errorReporting()
    {
        $errorReporting = $this->cfg['errorReporting'] === 'system'
            ? \error_reporting() // note:  will return 0 if error suppression is active in call stack (via @ operator)
                                //  our shutdown function unsupresses fatal errors
            : $this->cfg['errorReporting'];
        if ($errorReporting === -1) {
            $errorReporting = E_ALL | E_STRICT;
        }
        return $errorReporting;
    }

    /**
     * Retrieve a data value or property
     *
     * @param string $key  what to get
     * @param string $hash if key == 'error', specify error hash
     *
     * @return mixed
     */
    public function get($key, $hash = null)
    {
        if ($key === 'error') {
            return isset($this->data['errors'][$hash])
                ? $this->data['errors'][$hash]
                : null;
        }
        if ($key === 'lastError') {
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
     * @param bool $inclSuppressed (false)
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
     * @param int    $errType error lavel / type (one of PHP's E_* constants)
     * @param string $errMsg  the error message
     * @param string $file    filepath the error was raised in
     * @param int    $line    the line the error was raised in
     * @param array  $vars    active symbol table at point error occured
     *
     * @return bool
     * @link   http://php.net/manual/en/function.set-error-handler.php
     * @link   http://php.net/manual/en/language.operators.errorcontrol.php
     */
    public function handleError($errType, $errMsg, $file, $line, $vars = array())
    {
        /*
        echo '<pre>handleError : ' . \htmlspecialchars(\print_r(array(
            'errType' => $errType,
            // 'errTypeStr' => Error::$errTypes[$errType],
            'errMsg' => $errMsg,
            'file' => $file,
            'line' => $line,
        ), true)) . '</pre>';
        */
        $error = $this->cfg['errorFactory']($this, $errType, $errMsg, $file, $line, $vars);
        $this->anonymousCheck($error);
        $this->toStringCheck($error);
        if (!$this->isErrTypeHandled($errType)) {
            // not handled
            //   if cfg['errorReporting'] == 'system', error could simply be suppressed
            // return false to continue to "normal" error handler
            return $this->continueToPrevHandler($error);
        }
        $this->storeLastError($error);
        $this->data['errors'][ $error['hash'] ] = $error;
        if (!$error['isSuppressed']) {
            // only clear error caller via non-suppressed error
            $this->data['errorCaller'] = array();
            // only publish event for non-suppressed error
            $this->eventManager->publish(self::EVENT_ERROR, $error);
            $this->throwError($error);
        }
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
    }

    /**
     * EventManager::EVENT_PHP_SHUTDOWN event subscriber
     *
     * Used to handle fatal errors
     *
     * Test fatal error handling by publishing EventManager::EVENT_PHP_SHUTDOWN event with error value
     *
     * @param Event $event EventManager::EVENT_PHP_SHUTDOWN event
     *
     * @return void
     */
    public function onShutdown(Event $event)
    {
        $this->inShutdown = true;
        if ($this->registered === false) {
            return;
        }
        $error = $event['error'] ?: \error_get_last();
        if (!$error) {
            return;
        }
        $isFatal = ($error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR)) === $error['type'];
        if ($isFatal === false) {
            return;
        }
        $this->handleError(
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line'],
            isset($error['vars'])
                ? $error['vars']
                : array()
        );
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

    /**
     * Register this error handler and shutdown function
     *
     * @return void
     */
    public function register()
    {
        $errHandlerCur = $this->getErrorHandler();
        if ($errHandlerCur !== array($this, 'handleError')) {
            $this->prevErrorHandler = \set_error_handler(array($this, 'handleError'));
        }

        $exHandlerCur = $this->getExceptionHandler();
        if ($exHandlerCur !== array($this, 'handleException')) {
            $this->prevExceptionHandler = \set_exception_handler(array($this, 'handleException'));
        }

        $this->prevDisplayErrors = \ini_set('display_errors', '0');
        $this->registered = true;   // used by this->onShutdown()
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
            if ($this->cfg['onError'] !== null) {
                $this->eventManager->unsubscribe(self::EVENT_ERROR, $this->cfg['onError']);
            }
            $this->eventManager->subscribe(self::EVENT_ERROR, $values['onError']);
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
     * @param array|null|false $caller (default) null : determine automatically
     *                           false or empty array: clear current value
     *                           array() : manually set value
     * @param int              $offset (optional) if determining automatically : adjust how many frames to go back
     *
     * @return void
     */
    public function setErrorCaller($caller = null, $offset = 0)
    {
        if ($caller === null) {
            $backtrace = \version_compare(PHP_VERSION, '5.4.0', '>=')
                ? \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $offset + 3)
                : \debug_backtrace(false);   // don't provide object
            $index = isset($backtrace[$offset + 1])
                ? $offset + 1
                : \count($backtrace) - 1;
            $caller = isset($backtrace[$index]['file'])
                ? $backtrace[$index]
                : $backtrace[$index + 1]; // likely called via call_user_func.. need to go one more to get calling file & line
            $caller = array(
                'file' => $caller['file'],
                'line' => $caller['line'],
            );
        } elseif (empty($caller)) {
            // clear errorCaller
            $caller = array();
        }
        $this->data['errorCaller'] = $caller;
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
        $errHandlerCur = $this->getErrorHandler();
        if ($errHandlerCur === array($this, 'handleError')) {
            // we are the current error handler
            \restore_error_handler();
        }

        $exHandlerCur = $this->getExceptionHandler();
        if ($exHandlerCur === array($this, 'handleException')) {
            // we are the current exception handler
            \restore_exception_handler();
        }

        if ($this->prevDisplayErrors !== false) {
            \ini_set('display_errors', $this->prevDisplayErrors);
        }
        $this->prevErrorHandler = null;
        $this->prevExceptionHandler = null;
        $this->registered = false;  // used by $this->onShutdown()
    }

    /**
     * Check for anonymous class notation
     * Replace with more usefull parent class
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    private function anonymousCheck(Error $error)
    {
        $message = $error['message'];
        if (\strpos($message, "@anonymous\0") === false) {
            return;
        }
        $regex = '/[a-zA-Z_\x7f-\xff][\\\\a-zA-Z0-9_\x7f-\xff]*+@anonymous\x00(.*?\.php)(?:0x?|:([0-9]++)\$)[0-9a-fA-F]++/';
        $error['message'] = \preg_replace_callback($regex, static function ($matches) {
            return \class_exists($matches[0], false)
                ? (\get_parent_class($matches[0]) ?: \key(\class_implements($matches[0])) ?: 'class') . '@anonymous'
                : $matches[0];
        }, $message);
    }

    /**
     * Conditioanlly pass error or exception to previously defined handler
     *
     * @param Error $error Error instance
     *
     * @return bool
     * @throws \Exception
     */
    protected function continueToPrevHandler(Error $error)
    {
        $this->handleUserError($error);
        if ($error['continueToPrevHandler'] === false || $error->isPropagationStopped()) {
            return $error['continueToNormal'] === false;
        }
        if ($error['exception']) {
            if (!$this->prevExceptionHandler) {
                return $error['continueToNormal'] === false;
            }
            /*
                re-throw exception vs calling handler directly
            */
            \restore_exception_handler();
            throw $error['exception'];
        }
        if (!$this->prevErrorHandler) {
            return $error['continueToNormal'] === false;
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
     * @param self   $handler ErrorHandler instance
     * @param int    $errType the level of the error
     * @param string $errMsg  the error message
     * @param string $file    filepath the error was raised in
     * @param string $line    the line the error was raised in
     * @param array  $vars    active symbol table at point error occured
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
     * @param int $errType error type
     *
     * @return bool
     */
    protected function isErrTypeHandled($errType)
    {
        return ($errType & $this->errorReporting()) === $errType;
    }

    /**
     * Handle E_USER_ERROR and E_RECOVERABLE_ERROR
     *
     * Log user error if cfg['onEUserError'] === 'log' and propagation not stopped
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    protected function handleUserError(Error $error)
    {
        if (\in_array($error['type'], array(E_USER_ERROR, E_RECOVERABLE_ERROR)) === false) {
            return;
        }
        if ($this->cfg['onEUserError'] === 'log' && !$error->isPropagationStopped()) {
            $error->log();
        }
    }

    /**
     * Get Backtrace instance
     *
     * @return Backtrace
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function getBacktrace()
    {
        if (!$this->backtrace) {
            $this->backtrace = new Backtrace();
        }
        return $this->backtrace;
    }

    /**
     * Get current registered error handler
     *
     * @return callable|null
     */
    private function getErrorHandler()
    {
        /*
            set and restore error handler to determine the current error handler
        */
        $errHandlerCur = \set_error_handler(array($this, 'handleError'));
        \restore_error_handler();
        return $errHandlerCur;
    }

    /**
     * Get current registered exception handler
     *
     * @return callable|null
     */
    private function getExceptionHandler()
    {
        /*
            set and restore exception handler to determine the current error handler
        */
        $exHandlerCur = \set_exception_handler(array($this, 'handleException'));
        \restore_exception_handler();
        return $exHandlerCur;
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
     * Throw ErrorException if $error['throw'] === true
     * Fatal or Suppressed errors will never be thrown
     *
     * @param Error $error error exception
     *
     * @return void
     *
     * @throws \ErrorException
     */
    private function throwError(Error $error)
    {
        if ($error['isSuppressed']) {
            return;
        }
        if ($error->isFatal()) {
            return;
        }
        if ($error['throw']) {
            throw $error->asException();
        }
    }

    /**
     * Handle  Fatal Error 'Method __toString() must not throw an exception'
     *
     * PHP < 7.4 does not allow an exception to be thrown from __toString
     * A work around
     *    try {
     *        // code
     *    } catch (\Exception $e) {
     *        return trigger_error ($e, E_USER_ERROR);
     *    }
     *
     * @param Error $error [description]
     *
     * @return void
     * @throws \Exception re-throws caught exception
     */
    private function toStringCheck(Error $error)
    {
        if (PHP_VERSION_ID >= 70400) {
            return;
        }
        if ($this->toStringException) {
            $exception = $this->toStringException;
            $this->toStringException = null;
            throw $exception;
        }
        if ($error['type'] !== E_USER_ERROR) {
            return;
        }
        $errMsg = $error['message'];
        /*
            Find exception in context
            if found, check if error via __toString -> trigger_error
        */
        foreach ($error['vars'] as $val) {
            if ($val instanceof \Exception && ($val->getMessage() === $errMsg || (string) $val === $errMsg)) {
                $this->toStringCheckTrigger($error, $val);
                break;
            }
        }
    }

    /**
     * Look through backtrace to see if error via __toString -> trigger_error
     *
     * @param Error                 $error     Error instance
     * @param \Throwable|\Exception $exception Exception
     *
     * @return void
     */
    private function toStringCheckTrigger(Error $error, $exception)
    {
        $backtrace = $error->getTrace();
        if ($backtrace === false) {
            return;
        }
        $count = \count($backtrace);
        for ($i = 1; $i < $count; $i++) {
            if (
                isset($backtrace[$i - 1]['function'])
                && \in_array($backtrace[$i - 1]['function'], array('trigger_error', 'user_error'))
                && \strpos($backtrace[$i]['function'], '->__toString') !== false
            ) {
                $error->stopPropagation();
                $error['continueToNormal'] = false;
                $this->toStringException = $exception;
                return;
            }
        }
    }
}

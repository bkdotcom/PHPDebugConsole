<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.2
 */

namespace bdk;

use bdk\ErrorHandler\AbstractErrorHandler;
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
class ErrorHandler extends AbstractErrorHandler
{
    const EVENT_ERROR = 'errorHandler.error';

    /** @var EventManager */
    public $eventManager;
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
            'continueToPrevHandler' => true,    // whether to continue to previously defined handler (if there is/was a prev handler)
                                                //   prev handler will not be called if error event propagation stopped
            'errorFactory' => array($this, 'errorFactory'),
            'errorReporting' => E_ALL | E_STRICT,   // what errors are handled by handler? bitmask or "system" to use runtime value
                                                    //   note that if using "system", suppressed errors (via @ operator) will not be handled (we'll still handle fatal category)
            'errorThrow' => 0,          // bitmask: error types that should converted to ErrorException and thrown
            'onError' => null,          // callable : shortcut for subscribing to errorHandler.error Event
                                        //   will receive error Event object
            'onFirstError' => null,     // callable : called on first error..   usefull for lazy-loading subscriberInterface
            'onEUserError' => 'normal', // only applicable if we're not continuing to a prev error handler
                                    // (continueToPrevHandler = false, there's no previous handler, or propagation stopped)
                                    //   'continue' : sets error[continueToNormal] = false
                                    //         script will continue
                                    //         error will not be sent to error log
                                    //   'log' : sets error[continueToNormal] = false
                                    //         script will continue
                                    //         if propagation not stopped, call error_log()
                                    //   'normal' : sets error[continueToNormal] = true;
                                    //         php will log error
                                    //         script will hault
                                    //   null : use error's error[continueToNormal] value
                                    //         continueToNormal true -> log
                                    //         continueToNormal false -> continue
            'suppressNever' => E_ERROR | E_PARSE | E_RECOVERABLE_ERROR | E_USER_ERROR,
            // emailer options
            'enableEmailer' => false,
            'emailer' => array(),
            // stats options
            'enableStats' => false,
            'stats' => array(
                'errorStatsFile' => __DIR__ . '/error_stats.json',
            ),
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
     * Get information about last error
     *
     * @param bool $inclSuppressed (false)
     *
     * @return Error|null
     */
    public function getLastError($inclSuppressed = false)
    {
        foreach ($this->data['lastErrors'] as $error) {
            if (!$inclSuppressed && $error['isSuppressed']) {
                continue;
            }
            return $error;
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
        }
        if ($cfg) {
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
     * @return bool false: will be handled by standard PHP error handler
     *              true: we "handled" / will not be handed by PHP error handler
     * @link   http://php.net/manual/en/function.set-error-handler.php
     * @link   http://php.net/manual/en/language.operators.errorcontrol.php
     */
    public function handleError($errType, $errMsg, $file, $line, $vars = array())
    {
        $error = $this->cfg['errorFactory']($this, $errType, $errMsg, $file, $line, $vars);
        $this->anonymousCheck($error);
        $this->toStringCheck($error);
        if (!$this->isErrTypeHandled($errType)) {
            // not handled
            //   if cfg['errorReporting'] == 'system', error could simply be suppressed
            // return false to continue to "standard" error handler
            return $this->continueToPrevHandler($error);
        }
        $this->storeLastError($error);
        if (empty($this->data['errors'])) {
            $this->onFirstError($error);
        }
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
        $error = $event['error'] ?: \error_get_last();
        if ($this->registered === false || !$error) {
            return;
        }
        if (\is_array($error)) {
            $error = \array_merge(array(
                'vars' => array(),
            ), $error);
            $error = $this->cfg['errorFactory']($this, $error['type'], $error['message'], $error['file'], $error['line'], $error['vars']);
        }
        if ($error->isFatal() === false) {
            $event['error'] = $error;
            return;
        }
        $this->handleError(
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line'],
            $error['vars']
        );
        /*
            Attach fatal error to event
        */
        $event['error'] = $this->getLastError();
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
        } elseif (empty($caller) === true) {
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
            $this->continueToPrevHandlerException($error);
            return $error['continueToNormal'] === false;
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
     * Restore previous excption handler and re-throw or log exception
     *
     * @param Error $error Error instance
     *
     * @return void
     * @throws \Exception
     */
    private function continueToPrevHandlerException(Error $error)
    {
        if ($this->prevExceptionHandler) {
            /*
                re-throw exception vs calling handler directly
            */
            \restore_exception_handler();
            $this->data['uncaughtException'] = null;
            throw $error['exception'];
        }
        if ($error['continueToNormal']) {
            $error->log();
        }
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
        return new Error($handler, array(
            'type' => $errType,
            'message' => $errMsg,
            'file' => $file,
            'line' => $line,
            'vars' => $vars,
        ));
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
        if ($error['category'] !== Error::CAT_ERROR) {
            return;
        }
        if ($this->cfg['onEUserError'] === 'log' && !$error->isPropagationStopped()) {
            $error->log();
            return;
        }
        if ($this->cfg['onEUserError'] !== null) {
            return;
        }
        if ($error['continueToNormal']) {
            $error->log();
        }
        $error['continueToNormal'] = false;
    }

    /**
     * Called on first error
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    protected function onFirstError(Error $error)
    {
        $this->enableStatsEmailer(true);
        if ($this->cfg['onFirstError']) {
            $this->cfg['onFirstError']($error);
        }
    }
}

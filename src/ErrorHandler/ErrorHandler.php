<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.3
 */

namespace bdk;

use bdk\Backtrace;
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
    /** @var EventManager */
    public $eventManager;

    /** @var bool */
    protected $inShutdown = false;

    /** @var bool */
    protected $registered = false;

    /** @var string|false previous display_errors setting (false if error getting/setting) */
    protected $prevDisplayErrors = false;

    /** @var static */
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
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $this->cfg = array(
            'continueToPrevHandler' => true,    // whether to continue to previously defined handler (if there is/was a prev handler)
                                                //   prev handler will not be called if error event propagation stopped
            'errorFactory' => [$this, 'errorFactory'],
            'errorReporting' => E_ALL,  // what errors are handled by handler?
                                        //   bitmask or "system" to use runtime value
                                        //   note: if using "system", suppressed errors (via @ operator) will not be handled (we'll still handle fatal category)
            'errorThrow' => 0,          // bitmask: error types that should converted to ErrorException and thrown
            'onError' => null,          // callable : shortcut for subscribing to errorHandler.error Event
                                        //   will receive error Event object
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
                                    //         script will halt
                                    //   null : use error's error[continueToNormal] value
                                    //         continueToNormal true -> log
                                    //         continueToNormal false -> continue
            'onFirstError' => null,     // callable : called on first error..   useful for lazy-loading subscriberInterface
            'suppressNever' => E_ERROR | E_PARSE | E_RECOVERABLE_ERROR | E_USER_ERROR,
            // emailer options
            'emailer' => array(),
            'enableEmailer' => false,
            // stats options
            'enableStats' => false,
            'stats' => array(
                'errorStatsFile' => __DIR__ . '/Plugin/error_stats.json',
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
        $this->eventManager->subscribe(EventManager::EVENT_PHP_SHUTDOWN, [$this, 'onShutdown'], PHP_INT_MAX);
        $this->setData('errorReportingInitial', $this->errorReporting());
    }

    /**
     * What error level are we handling
     *
     * @return int
     */
    public function errorReporting()
    {
        $errorReporting = $this->cfg['errorReporting'] === 'system'
            ? \error_reporting() // note: error could be "suppressed"
            : $this->cfg['errorReporting'];
        if ($errorReporting === -1) {
            $errorReporting = E_ALL;
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
     * @param int    $errType error level / type (one of PHP's E_* constants)
     * @param string $errMsg  the error message
     * @param string $file    filepath the error was raised in
     * @param int    $line    the line the error was raised in
     * @param array  $vars    active symbol table at point error occurred
     *
     * @return bool false: will be handled by standard PHP error handler
     *              true: we "handled" / will not be handed by PHP error handler
     * @link   http://php.net/manual/en/function.set-error-handler.php
     * @link   http://php.net/manual/en/language.operators.errorcontrol.php
     */
    public function handleError($errType, $errMsg, $file, $line, $vars = array())
    {
        $error = $this->cfg['errorFactory']($this, $errType, $errMsg, $file, $line, $vars);
        $this->data['uncaughtException'] = null;
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
            $this->setErrorCaller(array());
            // only publish event for non-suppressed error
            $this->eventManager->publish(self::EVENT_ERROR, $error);
            $this->throwError($error);
        }
        return $this->continueToPrevHandler($error);
    }

    /**
     * Handle uncaught exceptions
     *
     * This isn't strictly necessary...  uncaught exceptions are a fatal error, which we can handle...
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
        //   * error constructor will pull this
        //   * we clear this in handleError() so any errors encountered
        //      during exception handling will not use this
        $this->data['uncaughtException'] = $exception;
        if (\headers_sent() === false) {
            \http_response_code(500);
        }
        $this->handleError(
            E_ERROR,
            'Uncaught exception \'' . \get_class($exception) . '\' with message ' . $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
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
        $errArr = $error instanceof Error
            ? $error->getValues()
            : $error;
        $errArr = \array_merge(array(
            'vars' => array(),
        ), $errArr);
        // create temporary error object to use isFatal
        $errObj = $this->cfg['errorFactory']($this, $errArr['type'], $errArr['message'], $errArr['file'], $errArr['line'], $errArr['vars']);
        if ($errObj->isFatal() === false) {
            $event['error'] = $errObj;
            return;
        }
        $this->handleError(
            $errArr['type'],
            $errArr['message'],
            $errArr['file'],
            $errArr['line'],
            $errArr['vars']
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
        if ($errHandlerCur !== [$this, 'handleError']) {
            $this->prevErrorHandler = \set_error_handler(array($this, 'handleError'));
        }

        $exHandlerCur = $this->getExceptionHandler();
        if ($exHandlerCur !== [$this, 'handleException']) {
            $this->prevExceptionHandler = \set_exception_handler(array($this, 'handleException'));
        }

        $this->prevDisplayErrors = \ini_set('display_errors', '0');
        $this->registered = true;   // used by this->onShutdown()
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
            $backtrace = Backtrace::get(null, $offset + 3);
            $index = isset($backtrace[$offset + 1])
                ? $offset + 1
                : \count($backtrace) - 1;
            $caller = isset($backtrace[$index]['file'])
                ? $backtrace[$index]
                : $backtrace[$index + 1]; // likely called via call_user_func.. need to go one more to get calling file & line
            $caller = array(
                'evalLine' => $caller['evalLine'],
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
        if ($errHandlerCur === [$this, 'handleError']) {
            // we are the current error handler
            \restore_error_handler();
        }

        $exHandlerCur = $this->getExceptionHandler();
        if ($exHandlerCur === [$this, 'handleException']) {
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
     * Create Error instance
     *
     * @param self   $handler ErrorHandler instance
     * @param int    $errType the level of the error
     * @param string $errMsg  the error message
     * @param string $file    filepath the error was raised in
     * @param string $line    the line the error was raised in
     * @param array  $vars    active symbol table at point error occurred
     *
     * @return Error
     */
    protected function errorFactory(self $handler, $errType, $errMsg, $file, $line, $vars = array())
    {
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
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

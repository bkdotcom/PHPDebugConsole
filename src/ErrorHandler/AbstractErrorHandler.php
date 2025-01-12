<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.3
 */

namespace bdk\ErrorHandler;

use bdk\Backtrace;
use bdk\ErrorHandler\AbstractComponent;
use bdk\ErrorHandler\Error;
use bdk\ErrorHandler\Plugin\Emailer;
use bdk\ErrorHandler\Plugin\Stats;

/**
 * Serves as base class for ErrorHandler
 *
 * Able to register multiple onError "callback" functions
 *
 * @property \bdk\Backtrace                 $backtrace Backtrace instance
 * @property \bdk\ErrorHandler\Plugin\Stats $stats     Stats instance
 */
abstract class AbstractErrorHandler extends AbstractComponent
{
    const EVENT_ERROR = 'errorHandler.error';

    /** @var array<string,mixed> */
    protected $data = array(
        'errorCaller'   => array(),
        'errorReportingInitial' => 0,   // Initial error reporting level. to compare against value when handling error
        'errors'        => array(),
        'lastErrors'    => array(),     // contains up to two errors: suppressed & unsuppressed
                                        // lastError[0] is the most recent error
        'uncaughtException' => null,    // error constructor will pull this
    );

    /** @var callable|null */
    protected $prevErrorHandler = null;

    /** @var callable|null */
    protected $prevExceptionHandler = null;

    /** @var Backtrace */
    private $backtrace;

    /** @var Emailer */
    private $emailer;

    /** @var Stats */
    private $stats;

    /**
     * Temp store error exception caught/triggered inside __toString
     *
     * @var \Exception|\Throwable|null
     */
    private $toStringException = null;

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
     * Conditionally pass error or exception to previously defined handler
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
     * Restore previous exception handler and re-throw or log exception
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
     * Check enableEmailer & enableStats cfg values and enable
     *
     * Called
     *   * on first error (passes haveError = true)
     *   * postSetCfg
     *
     * @param bool $haveError true when called via onFirstError
     *
     * @return void
     */
    protected function enableStatsEmailer($haveError = false)
    {
        if ($haveError === false && empty($this->data['errors'])) {
            // no reason to instantiate or subscribe
            return;
        }
        $callables = \array_map(static function ($subscriberInfo) {
            return $subscriberInfo['callable'];
        }, $this->eventManager->getSubscribers(self::EVENT_ERROR));
        if ($this->cfg['enableEmailer'] && \in_array([$this->getEmailer(), 'onErrorHighPri'], $callables, true) === false) {
            $this->cfg['enableStats'] = true;
            $this->eventManager->addSubscriberInterface($this->emailer);
        }
        if ($this->cfg['enableStats'] && \in_array([$this->getStats(), 'onErrorHighPri'], $callables, true) === false) {
            $this->eventManager->addSubscriberInterface($this->stats);
        }
    }

    /**
     * Get Backtrace instance
     *
     * @return Backtrace
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    protected function getBacktrace()
    {
        if (!$this->backtrace) {
            $this->backtrace = new Backtrace();
            $this->backtrace->addInternalClass([
                'bdk\\ErrorHandler',
                'bdk\\PubSub\\',
            ]);
        }
        return $this->backtrace;
    }

    /**
     * Get current registered error handler
     *
     * @return callable|null
     */
    protected function getErrorHandler()
    {
        /*
            set and restore error handler to determine the current error handler
        */
        $errHandlerCur = \set_error_handler([$this, 'handleError']);
        \restore_error_handler();
        return $errHandlerCur;
    }

    /**
     * Get current registered exception handler
     *
     * @return callable|null
     */
    protected function getExceptionHandler()
    {
        /*
            set and restore exception handler to determine the current error handler
        */
        $exHandlerCur = \set_exception_handler([$this, 'handleException']);
        \restore_exception_handler();
        return $exHandlerCur;
    }

    /**
     * Get Emailer instance
     *
     * @return Emailer
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    protected function getEmailer()
    {
        if ($this->emailer === null) {
            $this->emailer = new Emailer($this->cfg['emailer']);
        }
        return $this->emailer;
    }

    /**
     * Get Stats instance
     *
     * @return Stats
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    protected function getStats()
    {
        if ($this->stats === null) {
            $this->stats = new Stats($this->cfg['stats']);
        }
        return $this->stats;
    }

    /**
     * Handle updated onError
     *
     * @param callable|null $onError new onError value
     * @param callable|null $prev    previous onError value
     *
     * @return void
     */
    protected function onCfgOnError($onError, $prev)
    {
        /*
            Replace - not append - subscriber set via setCfg
        */
        if ($prev !== null) {
            $this->eventManager->unsubscribe(self::EVENT_ERROR, $prev);
        }
        if ($onError) {
            $this->eventManager->subscribe(self::EVENT_ERROR, $onError);
        }
    }

    /**
     * Handle updated cfg values
     *
     * @param array $cfg  new config values
     * @param array $prev previous config values
     *
     * @return void
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        if (isset($this->emailer) && isset($cfg['emailer'])) {
            $this->emailer->setCfg($cfg['emailer']);
        }
        if (isset($this->stats) && isset($cfg['stats'])) {
            $this->stats->setCfg($cfg['stats']);
        }
        $this->enableStatsEmailer();
        if (\array_key_exists('onError', $cfg)) {
            $this->onCfgOnError($cfg['onError'], $prev['onError']);
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
    protected function storeLastError(Error $error)
    {
        $this->data['lastErrors'] = \array_filter($this->data['lastErrors'], static function (Error $error) {
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
    protected function throwError(Error $error)
    {
        if ($error['isSuppressed'] || $error->isFatal()) {
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
     * @param Error $error Error instance
     *
     * @return void
     * @throws \Exception re-throws caught exception
     */
    protected function toStringCheck(Error $error)
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
     * Only utilized by PHP < 7.4
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
                && \in_array($backtrace[$i - 1]['function'], ['trigger_error', 'user_error'], true)
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

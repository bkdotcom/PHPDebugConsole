<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.1
 */

namespace bdk\ErrorHandler;

use bdk\Backtrace;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;

/**
 * General-purpose error handler which supports fatal errors
 *
 * Able to register multiple onError "callback" functions
 *
 * @property \bdk\Backtrace $backtrace Backtrace instance
 * @property bool           $isCli
 */
class Scaffolding
{
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

    /** @var Backtrace */
    private $backtrace;

    /**
     * Temp store error exception caught/triggered inside __toString
     *
     * @var \Exception|\Throwable|null
     */
    private $toStringException = null;

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
        if (\preg_match('/^is[A-Z]/', $property) && \method_exists($this, $property)) {
            return $this->{$property}();
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
                $this->eventManager->unsubscribe(ErrorHandler::EVENT_ERROR, $this->cfg['onError']);
            }
            $this->eventManager->subscribe(ErrorHandler::EVENT_ERROR, $values['onError']);
        }
        $this->cfg = \array_merge($this->cfg, $values);
        return $ret;
    }

    /**
     * Check for anonymous class notation
     * Replace with more usefull parent class
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    protected function anonymousCheck(Error $error)
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
     * Get current registered error handler
     *
     * @return callable|null
     */
    protected function getErrorHandler()
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
    protected function getExceptionHandler()
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
    protected function storeLastError(Error $error)
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
    protected function throwError(Error $error)
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
     * Is script running from command line (or cron)?
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function isCli()
    {
        $argv = isset($_SERVER['argv'])
            ? $_SERVER['argv']
            : null;
        $query = isset($_SERVER['QUERY_STRING'])
            ? $_SERVER['QUERY_STRING']
            : null;
        return $argv && \implode('+', $argv) !== $query;
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

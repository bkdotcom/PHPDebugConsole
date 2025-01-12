<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.3
 */

namespace bdk\ErrorHandler;

use bdk\ErrorHandler;
use ErrorException;
use ParseError;
use ReflectionProperty;

/**
 * Error object
 *
 * @property array $context lines surrounding error
 * @property array $trace   backtrace
 */
class Error extends AbstractError
{
    /**
     * Constructor
     *
     * @param ErrorHandler $errHandler ErrorHandler instance
     * @param array        $values     Initial values
     *                                   must include type, message, file, & line
     *                                   optional:  vars
     */
    public function __construct(ErrorHandler $errHandler, array $values)
    {
        $this->subject = $errHandler;
        $this->values['constructor'] = true;
        $this->setValues($values);
        unset($this->values['constructor']);
        $errorCaller = $errHandler->get('errorCaller');
        if ($errorCaller) {
            $errorCallerVals = \array_intersect_key($errorCaller, \array_flip(['file', 'line']));
            $this->values = \array_merge($this->values, $errorCallerVals);
        }
    }

    /**
     * Return as ErrorException (or caught exception)
     *
     * If error is an uncaught exception, the original Exception will be returned
     *
     * @return \Exception|ErrorException
     */
    public function asException()
    {
        if ($this->values['exception']) {
            return $this->values['exception'];
        }
        $exception = new ErrorException(
            $this->values['message'],
            0,
            $this->values['type'],
            $this->values['file'],
            $this->values['line']
        );
        $traceReflector = new ReflectionProperty('Exception', 'trace');
        $traceReflector->setAccessible(true);
        $traceReflector->setValue($exception, $this->getTrace() ?: array());
        return $exception;
    }

    /**
     * Alias for `getTrace()
     *
     * @param bool|'auto' $withContext (auto) Whether to include code snippets
     *
     * @return array|false|null
     */
    public function getBacktrace($withContext = 'auto')
    {
        return $this->getTrace($withContext);
    }

    /**
     * Get php code surrounding error
     *
     * @return array|false
     */
    public function getContext()
    {
        return $this->subject->backtrace->getFileLines(
            $this->values['file'],
            \max($this->values['line'] - 6, 0),
            13
        );
    }

    /**
     * Get file and line string
     *
     * @return string
     */
    public function getFileAndLine()
    {
        $fileAndLine = \sprintf(
            '%s (line %s, eval\'d line %s)',
            $this->values['file'],
            $this->values['line'],
            $this->values['evalLine']
        );
        $fileAndLine = \str_replace(', eval\'d line )', ')', $fileAndLine);
        return $fileAndLine;
    }

    /**
     * Get error message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->values['isHtml']
            ? $this->getMessageHtml()
            : $this->getMessageText();
    }

    /**
     * Get the message html-escaped
     *
     * @return string html
     */
    public function getMessageHtml()
    {
        $message = $this->values['message'];
        return $this->values['isHtml']
            ? \str_replace('<a ', '<a target="phpRef" ', $message)
            : \htmlspecialchars($message);
    }

    /**
     * Get the plain text error message (html tags removed)
     *
     * @return string
     */
    public function getMessageText()
    {
        $message = $this->values['message'];
        $message = \strip_tags($message);
        $message = \htmlspecialchars_decode($message);
        return $message;
    }

    /**
     * Get backtrace
     *
     * Backtrace is avail for fatal errors (incl uncaught exceptions)
     *   (does not include parse errors)
     *
     * @param bool|'auto' $withContext (auto) Whether to include code snippets
     *
     * @return array|false|null
     */
    public function getTrace($withContext = 'auto')
    {
        if ($this->values['exception'] instanceof ParseError) {
            return null;
        }
        $trace = $this->values['exception']
            ? $this->subject->backtrace->get(null, 0, $this->values['exception']) // adds Exception's file/line as frame and "normalizes"
            : $this->backtrace;
        if (!$trace) {
            // false, null, or empty array()
            return $trace;
        }
        if ($withContext === 'auto') {
            $withContext = $this->isFatal();
        }
        return $withContext
            ? $this->subject->backtrace->addContext($trace)
            : $trace;
    }

    /**
     * Send error to `error_log()`
     *
     * @return bool
     */
    public function log()
    {
        $error = $this->values;
        $str = \sprintf(
            'PHP %s: %s in %s on line %s',
            $error['typeStr'],
            $error['message'],
            $error['file'],
            $error['line']
        );
        return \error_log($str);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($key)
    {
        if ($key === 'message') {
            $return = $this->getMessage();
            return $return;
        }
        $return =& parent::offsetGet($key);
        return $return;
    }
}

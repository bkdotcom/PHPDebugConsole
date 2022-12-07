<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.2
 */

namespace bdk\ErrorHandler;

use bdk\ErrorHandler;
use bdk\PubSub\Event;
use InvalidArgumentException;

/**
 * Error object
 *
 * @property array $context lines surrounding error
 * @property array $trace   backtrace
 */
class Error extends Event
{
    const CAT_DEPRECATED = 'deprecated';
    const CAT_ERROR = 'error';
    const CAT_NOTICE = 'notice';
    const CAT_STRICT = 'strict';
    const CAT_WARNING = 'warning';
    const CAT_FATAL = 'fatal';

    protected static $errCategories = array(
        self::CAT_DEPRECATED => array( E_DEPRECATED, E_USER_DEPRECATED ),
        self::CAT_ERROR      => array( E_USER_ERROR, E_RECOVERABLE_ERROR ),
        self::CAT_NOTICE     => array( E_NOTICE, E_USER_NOTICE ),
        self::CAT_STRICT     => array( E_STRICT ),
        self::CAT_WARNING    => array( E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING ),
        self::CAT_FATAL      => array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR ),
    );
    protected static $errTypes = array(
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
        E_STRICT            => 'Strict',            // php 5.0 :  2048
        E_RECOVERABLE_ERROR => 'Recoverable Error', // php 5.2 :  4096
        E_DEPRECATED        => 'Deprecated',        // php 5.3 :  8192
        E_USER_DEPRECATED   => 'User Deprecated',   // php 5.3 : 16384
    );
    protected static $userErrors = array(
        E_USER_DEPRECATED,
        E_USER_ERROR,
        E_USER_NOTICE,
        E_USER_WARNING,
    );

    /**
     * Store fatal non-Exception backtrace
     *
     * Initially null, will become array or false on attempt to get backtrace
     *
     * @var array|false|null
     */
    protected $backtrace = null;

    /**
     * @var array Array of key/values
     */
    protected $values = array(
        'type'      => null,        // int: The severity / level / one of the E_* constants
        'message'   => '',          // The error message
        'file'      => null,        // Filepath the error was raised in
        'line'      => null,        // Line the error was raised in
        'vars'      => array(),     // Active symbol table at point error occured
        'category'  => null,
        'continueToNormal' => null, // let PHP do its thing (log error / exit if E_USER_ERROR)
        'continueToPrevHandler' => true,
        'exception'     => null,
        'hash'          => null,
        'isFirstOccur'  => true,    // per error  (ie a error inside a loop, or inside a functon called multiple times)
        'isHtml'        => false,
        'isSuppressed'  => false,
        'throw'         => false,   // whether to throw as exception (fatal errors never throw)
        'typeStr'       => '',      // friendly version of 'type'
    );

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
        $this->assertValues($values);
        $this->setValues($values);
        unset($this->values['vars']['GLOBALS']);
        $errorCaller = $errHandler->get('errorCaller');
        if ($errorCaller) {
            $errorCallerVals = \array_intersect_key($errorCaller, \array_flip(array('file','line')));
            $this->values = \array_merge($this->values, $errorCallerVals);
        }
        if (\in_array($this->values['type'], array(E_ERROR, E_USER_ERROR), true) && $this->values['exception'] === null) {
            // will return empty unless xdebug extension installed/enabled
            $this->backtrace = $this->subject->backtrace->get();
        }
    }

    /**
     * Return as ErrorException (or caught exception)
     *
     * If error is an uncaught exception, the original Exception will be returned
     *
     * @return \Exception|\ErrorException
     */
    public function asException()
    {
        if ($this->values['exception']) {
            return $this->values['exception'];
        }
        $exception = new \ErrorException(
            $this->values['message'],
            0,
            $this->values['type'],
            $this->values['file'],
            $this->values['line']
        );
        $traceReflector = new \ReflectionProperty('Exception', 'trace');
        $traceReflector->setAccessible(true);
        $traceReflector->setValue($exception, $this->getTrace() ?: array());
        return $exception;
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
            ? $message
            : \htmlspecialchars($message);
    }

    /**
     * Get the plain text error message
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
        if ($this->values['exception'] instanceof \ParseError) {
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
     * Is the error "fatal"?
     *
     * @return bool
     */
    public function isFatal()
    {
        return $this->values['category'] === self::CAT_FATAL;
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
     * ArrayAccess getValue.
     *
     * special-case for backtrace... will pull from exception if applicable
     *
     * @param string $key Array key
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($key)
    {
        if ($key === 'backtrace') {
            $trace = $this->getTrace();
            return $trace;
        } elseif ($key === 'context') {
            $context = $this->subject->backtrace->getFileLines(
                $this->values['file'],
                \max($this->values['line'] - 6, 0),
                13
            );
            return $context;
        }
        return parent::offsetGet($key);
    }

    /**
     * {@inheritDoc}
     */
    public function setValues(array $values = array())
    {
        $this->setValuesInit($values);
        $errType = $values['type'];
        $hash = $this->hash($values);
        $prevOccurance = $this->subject->get('error', $hash);
        $isSuppressed = $this->isSuppressed($prevOccurance);
        $this->values = \array_merge(
            $this->values,
            array(
                'continueToNormal' => $this->setContinueToNormal($isSuppressed, $prevOccurance === null),
                'continueToPrevHandler' => $this->subject->getCfg('continueToPrevHandler'),
                'throw' => $this->isFatal() === false && ($errType & $this->subject->getCfg('errorThrow')) === $errType,
            ),
            $values,
            array(
                'category' => $this->values['category'],
                'hash' => $hash,
                'isFirstOccur' => !$prevOccurance,
                'isHtml' => $this->isHtml(),
                'isSuppressed' => $isSuppressed,
                'typeStr' => self::$errTypes[$errType],
                'message' => $this->isHtml()
                    ? \str_replace('<a ', '<a target="phpRef" ', $this->values['message'])
                    : $this->values['message'],
            )
        );
    }

    /**
     * Get human-friendly error type
     *
     * @param int $type E_xx constant value
     *
     * @return string
     */
    public static function typeStr($type)
    {
        return isset(self::$errTypes[$type])
            ? self::$errTypes[$type]
            : '';
    }

    /**
     * Validate error values
     *
     * @param array $values Initial error values
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function assertValues($values)
    {
        $keysMustHave = array('type', 'message', 'file', 'line');
        $keys = \array_keys($values);
        if (\array_intersect($keysMustHave, $keys) !== $keysMustHave) {
            throw new InvalidArgumentException('Error values must include: type, message, file, & line');
        }
        $validTypes = \array_diff_key(self::$errTypes, \array_flip(array(E_ALL)));
        if (\array_key_exists($values['type'], $validTypes) === false) {
            throw new InvalidArgumentException('invalid error type specified');
        }
        if (\array_key_exists('vars', $values) && \is_array($values['vars']) === false) {
            throw new InvalidArgumentException('Error vars must be an array');
        }
    }

    /**
     * Generate hash used to uniquely identify this error
     *
     * @return string hash
     */
    protected function hash()
    {
        $errMsg = $this->values['message'];
        // (\(.*?)\d+(.*?\))    "(tried to allocate 16384 bytes)" -> "(tried to allocate xxx bytes)"
        $errMsg = \preg_replace('/(\(.*?)\d+(.*?\))/', '\1x\2', $errMsg);
        // "blah123" -> "blahxxx"
        $errMsg = \preg_replace('/\b([a-z]+\d+)+\b/', 'xxx', $errMsg);
        // "-123.123" -> "xxx"
        $errMsg = \preg_replace('/\b[\d.-]{4,}\b/', 'xxx', $errMsg);
        // remove "comments"..  this allows throttling email, while still adding unique info to user errors
        $errMsg = \preg_replace('/\s*##.+$/', '', $errMsg);
        return \md5($this->values['file'] . $this->values['line'] . $this->values['type'] . $errMsg);
    }

    /**
     * ErrType to category
     *
     * @param int $errType error type
     *
     * @return string|null
     */
    protected static function getCategory($errType)
    {
        $return = null;
        foreach (self::$errCategories as $category => $errTypes) {
            if (\in_array($errType, $errTypes, true)) {
                $return = $category;
                break;
            }
        }
        return $return;
    }

    /**
     * isHtml?  More like "allowHtml"
     *
     * We only allow html_errors if html_errors ini value is tru and non-user error
     *
     * @return bool
     */
    private function isHtml()
    {
        return \filter_var(\ini_get('html_errors'), FILTER_VALIDATE_BOOLEAN)
            && \in_array($this->values['type'], static::$userErrors, true) === false
            && !$this->values['exception'];
    }

    /**
     * Get initial `isSuppressed` value
     *
     * @param self|null $prevOccurance previous occurrence of current error
     *
     * @return bool
     */
    private function isSuppressed($prevOccurance = null)
    {
        if ($prevOccurance && !$prevOccurance['isSuppressed']) {
            // if any instance of this error was not supprssed, reflect that
            return false;
        }
        $errType = $this->values['type'];
        if (($this->subject->getCfg('suppressNever') & $errType) === $errType) {
            // never suppress tyis type
            return false;
        }
        return \error_reporting() === 0;
    }

    /**
     * Set continueToNormal flag
     *
     * @param bool $isSuppressed     Whether error is suppressed
     * @param bool $isFirstOccurance Whether this is errors' first occurance durring this request
     *
     * @return bool
     */
    private function setContinueToNormal($isSuppressed, $isFirstOccurance)
    {
        $continueToNormal = $isSuppressed === false && $isFirstOccurance;
        if ($continueToNormal === false || $this->values['category'] !== self::CAT_ERROR) {
            return $continueToNormal;
        }
        // we are a user error
        switch ($this->subject->getCfg('onEUserError')) {
            case 'continue':
                return false;
            case 'log':
                return false;
            case 'normal':
                return true;
        }
        return $continueToNormal;
    }

    /**
     * Set the core values (type, message, file, line)
     *
     * @param array $values values being set
     *
     * @return void
     */
    private function setValuesInit($values)
    {
        $errType = $values['type'];
        $category = $this->getCategory($errType);
        $this->values = \array_merge(
            $this->values,
            array(
                'category' => $category,
                'exception' => $this->subject->get('uncaughtException'),  // non-null if error is uncaught-exception
            ),
            $values
        );
    }
}

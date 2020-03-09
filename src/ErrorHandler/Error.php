<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\ErrorHandler;

use bdk\Backtrace;
use bdk\ErrorHandler;
use bdk\PubSub\Event;

/**
 * Error object
 */
class Error extends Event
{

    protected static $errCategories = array(
        'deprecated'    => array( E_DEPRECATED, E_USER_DEPRECATED ),
        'error'         => array( E_USER_ERROR, E_RECOVERABLE_ERROR ),
        'notice'        => array( E_NOTICE, E_USER_NOTICE ),
        'strict'        => array( E_STRICT ),
        'warning'       => array( E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING ),
        'fatal'         => array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR ),
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
        E_STRICT            => 'Runtime Notice (E_STRICT)', // php 5.0 :  2048
        E_RECOVERABLE_ERROR => 'Recoverable Error',         // php 5.2 :  4096
        E_DEPRECATED        => 'Deprecated',                // php 5.3 :  8192
        E_USER_DEPRECATED   => 'User Deprecated',           // php 5.3 : 16384
    );
    protected static $userErrors = array(
        E_USER_DEPRECATED,
        E_USER_ERROR,
        E_USER_NOTICE,
        E_USER_WARNING,
    );
    protected $backtrace = false;  // store fatal non-Exception backtrace

    /**
     * Constructor
     *
     * @param ErrorHandler $errHandler ErrorHandler instance
     * @param int          $errType    the level of the error
     * @param string       $errMsg     the error message
     * @param string       $file       filepath the error was raised in
     * @param string       $line       the line the error was raised in
     * @param array        $vars       active symbol table at point error occured
     */
    public function __construct(ErrorHandler $errHandler, $errType, $errMsg, $file, $line, $vars = array())
    {
        $this->subject = $errHandler;
        $this->values = array(
            'type'      => $errType,                    // int
            'typeStr'   => self::$errTypes[$errType],   // friendly string version of 'type'
            'category'  => self::getCategory($errType),
            'message'   => $errMsg,
            'file'      => $file,
            'line'      => $line,
            'vars'      => $vars,
            'continueToNormal' => false,    // aka, let PHP do its thing (log error)
            'continueToPrevHandler' => $errHandler->getCfg('continueToPrevHandler'),
            'exception' => $errHandler->get('uncaughtException'),  // non-null if error is uncaught-exception
            'hash'          => null,
            'isFirstOccur'  => true,
            'isHtml'        => false,
            'isSuppressed'  => false,
        );
        $hash = self::errorHash();
        $prevOccurance = $errHandler->get('error', $hash);
        $this->values = \array_merge($this->values, array(
            'hash' => $hash,
            'isHtml' => $this->isHtml(),
            'isFirstOccur' => !$prevOccurance,
            'isSuppressed' => $this->isSuppressed($prevOccurance),
        ));
        $this->values = \array_merge($this->values, array(
            'continueToNormal' => $this->values['isSuppressed'] === false && !$prevOccurance,
            'message' => $this->values['isHtml']
                ? \str_replace('<a ', '<a target="phpRef" ', $this->values['message'])
                : $this->values['message'],
        ));
        if (\in_array($errType, array(E_ERROR, E_USER_ERROR)) && !$this->values['exception']) {
            // will return empty unless xdebug extension installed/enabled
            Backtrace::addInternalClass(array(
                'bdk\\ErrorHandler',
                'bdk\\PubSub',
            ));
            $this->backtrace = Backtrace::get();
        }
        $errorCaller = $errHandler->get('errorCaller');
        if ($errorCaller) {
            $errorCallerVals = \array_intersect_key($errorCaller, \array_flip(array('file','line')));
            $this->values = \array_merge($this->values, $errorCallerVals);
        }
    }

    /**
     * Get the plain text error message
     *
     * (error[message] may be html)
     *
     * @return string
     */
    public function getMessage()
    {
        $message = $this->values['message'];
        if ($this->values['isHtml']) {
            $message = \strip_tags($message);
            $message = \htmlspecialchars_decode($message);
        }
        return $message;
    }

    /**
     * Get backtrace
     *
     * Backtrace is avail for fatal errors (incl uncaught exceptions)
     *
     * @param bool $withContext (auto) Whether to include code snippets
     *
     * @return array
     */
    public function getTrace($withContext = 'auto')
    {
        $trace = $this->values['exception']
            ? Backtrace::get($this->values['exception']) // adds Exception's file/line as frame and "normalizes"
            : $this->backtrace;
        if (!$trace) {
            return false;
        }
        if ($withContext === 'auto') {
            $withContext = $this->isFatal();
        }
        return $withContext
            ? Backtrace::addContext($trace)
            : $trace;
    }

    /**
     * Is the error "fatal"?
     *
     * @return bool
     */
    public function isFatal()
    {
        return \in_array($this->values['type'], self::$errCategories['fatal']);
    }

    /**
     * Send error to `error_log()`
     *
     * @return bool
     */
    public function log()
    {
        $error = $this->values;
        $str = 'PHP ' . $error['typeStr'] . ':  ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'];
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
    public function &offsetGet($key)
    {
        if ($key === 'backtrace') {
            $trace = $this->getTrace();
            return $trace;
        }
        return parent::offsetGet($key);
    }

    /**
     * Generate hash used to uniquely identify this error
     *
     * @return string hash
     */
    protected function errorHash()
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
        foreach (self::$errCategories as $category => $errTypes) {
            if (\in_array($errType, $errTypes)) {
                return $category;
            }
        }
        return null;
    }

    /**
     * Sets isHtml and modifies message
     *
     * @return bool
     */
    private function isHtml()
    {
        return \filter_var(\ini_get('html_errors'), FILTER_VALIDATE_BOOLEAN)
            && !\in_array($this->values['type'], static::$userErrors)
            && !$this->subject->get('uncaughtException');
    }

    /**
     * Get initial `isSuppressed` value
     *
     * @param self|null $prevOccurance previous ccurance of current error
     *
     * @return bool
     */
    private function isSuppressed(self $prevOccurance = null)
    {
        // if any instance of this error was not supprssed, reflect that
        return $prevOccurance && !$prevOccurance['isSuppressed']
            ? false
            : \error_reporting() === 0;
    }
}

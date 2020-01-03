<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\ErrorHandler;

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

    /**
     * Constructor
     *
     * @param ErrorHandler $errHandler ErrorHandler instance
     * @param integer      $errType    the level of the error
     * @param string       $errMsg     the error message
     * @param string       $file       filepath the error was raised in
     * @param string       $line       the line the error was raised in
     * @param array        $vars       active symbol table at point error occured
     */
    public function __construct(ErrorHandler $errHandler, $errType, $errMsg, $file, $line, $vars = array())
    {
        $this->subject = $errHandler;
        $values = array(
            'type'      => $errType,                    // int
            'typeStr'   => self::$errTypes[$errType],   // friendly string version of 'type'
            'category'  => self::getCategory($errType),
            'message'   => $errMsg,
            'file'      => $file,
            'line'      => $line,
            'vars'      => $vars,
            'backtrace' => array(), // only for fatal type errors, and only if xdebug is enabled
            'continueToNormal' => false,    // aka, let PHP do its thing (log error)
            'continueToPrevHandler' => $errHandler->getCfg('continueToPrevHandler'),
            'exception' => $errHandler->get('uncaughtException'),  // non-null if error is uncaught-exception
            'hash'          => null,
            'isFirstOccur'  => true,
            // isHtml = "allow" HTML
            'isHtml'        => \filter_var(\ini_get('html_errors'), FILTER_VALIDATE_BOOLEAN)
                && !\in_array($errType, static::$userErrors) && !$errHandler->get('uncaughtException'),
            'isSuppressed'  => false,
        );
        $hash = self::errorHash($values);
        $prevOccurance = $errHandler->get('error', $hash);
        // if any instance of this error was not supprssed, reflect that
        $isSuppressed = $prevOccurance && !$prevOccurance['isSuppressed']
            ? false
            : \error_reporting() === 0;
        if ($values['isHtml']) {
            $values['message'] = \str_replace('<a ', '<a target="phpRef" ', $values['message']);
        }
        $errorCaller = $errHandler->get('errorCaller');
        if ($errorCaller) {
            $errorCallerVals = \array_intersect_key($errorCaller, \array_flip(array('file','line')));
            $values = \array_merge($values, $errorCallerVals);
        }
        $this->values = \array_merge($values, array(
            'continueToNormal' => !$isSuppressed && !$prevOccurance,
            'hash' => $hash,
            'isFirstOccur' => !$prevOccurance,
            'isSuppressed' => $isSuppressed,
        ));
        if (\in_array($errType, array(E_ERROR, E_USER_ERROR))) {
            // will return empty unless xdebug extension installed/enabled
            $this->values['backtrace'] = $errHandler->backtrace($this);
        }
    }

    /**
     * Is the error "fatal"?
     *
     * @return boolean
     */
    public function isFatal()
    {
        return \in_array($this->values['type'], self::$errCategories['fatal']);
    }

    /**
     * Send error to `error_log()`
     *
     * @return boolean
     */
    public function log()
    {
        $error = $this->values;
        $str = 'PHP ' . $error['typeStr'] . ':  ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'];
        return \error_log($str);
    }

    /**
     * Generate hash used to uniquely identify this error
     *
     * @param array $errorValues error array
     *
     * @return string hash
     */
    protected static function errorHash($errorValues)
    {
        $errMsg = $errorValues['message'];
        // (\(.*?)\d+(.*?\))    "(tried to allocate 16384 bytes)" -> "(tried to allocate xxx bytes)"
        $errMsg = \preg_replace('/(\(.*?)\d+(.*?\))/', '\1x\2', $errMsg);
        // "blah123" -> "blahxxx"
        $errMsg = \preg_replace('/\b([a-z]+\d+)+\b/', 'xxx', $errMsg);
        // "-123.123" -> "xxx"
        $errMsg = \preg_replace('/\b[\d.-]{4,}\b/', 'xxx', $errMsg);
        // remove "comments"..  this allows throttling email, while still adding unique info to user errors
        $errMsg = \preg_replace('/\s*##.+$/', '', $errMsg);
        $hash = \md5($errorValues['file'] . $errorValues['line'] . $errorValues['type'] . $errMsg);
        return $hash;
    }

    /**
     * ErrType to category
     *
     * @param integer $errType error type
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
}

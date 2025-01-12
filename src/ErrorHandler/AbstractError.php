<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.3
 */

namespace bdk\ErrorHandler;

use bdk\PubSub\Event;
use InvalidArgumentException;

/**
 * Error object
 *
 * @property array $context lines surrounding error
 * @property array $trace   backtrace
 */
class AbstractError extends Event
{
    const CAT_DEPRECATED = 'deprecated';
    const CAT_ERROR = 'error';
    const CAT_NOTICE = 'notice';
    const CAT_STRICT = 'strict';
    const CAT_WARNING = 'warning';
    const CAT_FATAL = 'fatal';

    /** @var array<string,list<int>> */
    protected static $errCategories = array(
        self::CAT_DEPRECATED => [E_DEPRECATED, E_USER_DEPRECATED],
        self::CAT_ERROR      => [E_USER_ERROR, E_RECOVERABLE_ERROR],
        self::CAT_FATAL      => [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR],
        self::CAT_NOTICE     => [E_NOTICE, E_USER_NOTICE],
        self::CAT_STRICT     => [2048], // E_STRICT raises deprecation notice as of php 8.4
        self::CAT_WARNING    => [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING],
    );

    /** @var array<int,string> */
    protected static $errTypes = array(
        2048                => 'Strict',            // php 5.0 :  2048; deprecated as of php 8.5
        E_ALL               => 'E_ALL',             // listed here for completeness
        E_COMPILE_ERROR     => 'Compile Error',     // handled via shutdown function
        E_COMPILE_WARNING   => 'Compile Warning',   // handled?
        E_CORE_ERROR        => 'Core Error',        // handled via shutdown function
        E_CORE_WARNING      => 'Core Warning',      // handled?
        E_DEPRECATED        => 'Deprecated',        // php 5.3 :  8192
        E_ERROR             => 'Fatal Error',       // handled via shutdown function
        E_NOTICE            => 'Notice',
        E_PARSE             => 'Parsing Error',     // handled via shutdown function
        E_RECOVERABLE_ERROR => 'Recoverable Error', // php 5.2 :  4096
        E_USER_DEPRECATED   => 'User Deprecated',   // php 5.3 : 16384
        E_USER_ERROR        => 'User Error',
        E_USER_NOTICE       => 'User Notice',
        E_USER_WARNING      => 'User Warning',
        E_WARNING           => 'Warning',
    );

    /** @var list<int> */
    protected static $userErrors = [
        E_USER_DEPRECATED,
        E_USER_ERROR,
        E_USER_NOTICE,
        E_USER_WARNING,
    ];

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
    protected $values = array( // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        'type'      => null,        // int: The severity / level / one of the E_* constants
        'message'   => '',          // The raw error message
        'evalLine'  => null,
        'file'      => null,        // Filepath the error was raised in
        'line'      => null,        // Line the error was raised in
        'vars'      => array(),     // Active symbol table at point error occurred
        'category'  => null,
        'continueToNormal' => null, // let PHP do its thing (log error / exit if E_USER_ERROR)
        'continueToPrevHandler' => true,
        'exception'     => null,
        'hash'          => null,
        'isFirstOccur'  => true,    // per error  (ie a error inside a loop, or inside a function called multiple times)
        'isHtml'        => false,
        'isSuppressed'  => false,
        'throw'         => false,   // whether to throw as exception (fatal errors never throw)
        'typeStr'       => '',      // friendly version of 'type'
    );

    /**
     * Is this error "fatal"?
     *
     * @return bool
     */
    public function isFatal()
    {
        return $this->values['category'] === self::CAT_FATAL;
    }

    /**
     * {@inheritDoc}
     */
    public function setValues(array $values = array())
    {
        $this->assertValues($values);
        if (!empty($this->values['constructor'])) {
            $this->setValuesInit($values);
        }
        $values = \array_merge($this->values, $values);
        parent::setValues($values);
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
        $keysMustHave = ['type', 'message', 'file', 'line'];
        $values = \array_merge($this->values, $values);
        $valuesCheck = \array_intersect_key($values, \array_flip($keysMustHave));
        $keys = \array_keys(\array_filter($valuesCheck, static function ($val) {
            return \strlen((string) $val) > 0;
        }));
        if (\array_intersect($keysMustHave, $keys) !== $keysMustHave) {
            throw new InvalidArgumentException('Error values must include: type, message, file, & line');
        }
        $validTypes = \array_diff_key(self::$errTypes, \array_flip([E_ALL]));
        if (\array_key_exists($values['type'], $validTypes) === false) {
            throw new InvalidArgumentException('invalid error type specified');
        }
        if (\array_key_exists('vars', $values) && \is_array($values['vars']) === false) {
            throw new InvalidArgumentException('Error vars must be an array');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function onSet($values = array())
    {
        $this->values['category'] = $this->valCategory();
        $this->values['isHtml'] = $this->valIsHtml();
        $this->values['typeStr'] = $this->typeStr($this->values['type']);
        unset($this->values['vars']['GLOBALS']);
        if (isset($values['message'])) {
            $this->values['message'] = $this->onSetMessage($values['message']);
        }
        $regexEvaldCode = '/^(.+)\((\d+)\) : eval\(\)\'d code$/';
        $matches = array();
        if (\preg_match($regexEvaldCode, (string) $this->values['file'], $matches)) {
            // reported line = line within eval
            // line inside paren is the line `eval` is on
            $this->values['evalLine'] = $this->values['line'];
            $this->values['file'] = $matches[1];
            $this->values['line'] = (int) $matches[2];
        }
        if ($this->backtrace === null && \in_array($this->values['type'], [E_ERROR, E_USER_ERROR], true) && $this->values['exception'] === null) {
            // will return empty unless xdebug extension installed/enabled
            $this->backtrace = $this->subject->backtrace->get();
        }
    }

    /**
     * Check for anonymous class notation
     * Replace with more useful parent class
     *
     * @param string $message Error Message
     *
     * @return string
     */
    private function onSetMessage($message)
    {
        $regex = '/[a-zA-Z_\x7f-\xff][\\\\a-zA-Z0-9_\x7f-\xff]*+@anonymous\x00(.*?)(?:0x?|:([0-9]++)\$)[0-9a-fA-F]++/';
        return \preg_replace_callback($regex, static function ($matches) {
            $friendlyClassName = \get_parent_class($matches[0]) ?: \key(\class_implements($matches[0], false)) ?: 'class';
            return $friendlyClassName . '@anonymous';
        }, $message);
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
        $this->values = \array_merge(
            $this->values,
            \array_intersect_key($values, \array_flip(['type', 'message', 'file', 'line']))
        );
        $this->setValuesInitDefault();
        $this->values = \array_merge($this->values, $values);
        if ($this->isFatal()) {
            $count = 0;
            // fatal message may contain trace info...
            //   this occurs if fatal encountered in shutdown
            $this->values['message'] = \preg_replace(
                '/ in \S+\nStack trace:\n(#\d+ .+\n)+  thrown/',
                '',
                (string) $this->values['message'],
                -1,
                $count
            );
            if ($count) {
                // don't try to get trace info.
                $this->backtrace = array();
                $this->values['exception'] = null;
            }
        }
    }

    /**
     * Set values that will remain unchanged after __construct
     *
     * @return void
     */
    private function setValuesInitDefault()
    {
        $errType = $this->values['type'];
        $isSuppressed = $this->valIsSuppressed();
        $this->values['hash'] = $this->valHash();
        $this->values['category'] = $this->valCategory();
        $this->values = \array_merge($this->values, array(
            'continueToNormal' => $this->valContinueToNormal($isSuppressed),
            'continueToPrevHandler' => $this->subject->getCfg('continueToPrevHandler'),
            'exception' => $this->subject->get('uncaughtException'),  // non-null if error is uncaught-exception
            'isFirstOccur' => $this->subject->get('error', $this->values['hash']) === null,
            'isSuppressed' => $isSuppressed,
            'throw' => $this->isFatal() === false && ($errType & $this->subject->getCfg('errorThrow')) === $errType,
        ));
    }

    /**
     * Get error "category"
     *
     * @return string|null
     */
    private function valCategory()
    {
        $return = null;
        foreach (self::$errCategories as $category => $errTypes) {
            if (\in_array($this->values['type'], $errTypes, true)) {
                $return = $category;
                break;
            }
        }
        return $return;
    }

    /**
     * get default continueToNormal flag
     *
     * @param bool $isSuppressed Whether error is suppressed
     *
     * @return bool
     */
    private function valContinueToNormal($isSuppressed)
    {
        $prevOccurrence = $this->subject->get('error', $this->values['hash']);
        $continueToNormal = $isSuppressed === false && $prevOccurrence === null;
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
     * Generate hash used to uniquely identify this error
     *
     * @return string hash
     */
    private function valHash()
    {
        $errMsg = $this->values['message'];
        $dirParts = \array_slice(\explode(DIRECTORY_SEPARATOR, __DIR__), 0, 3);
        $dirStart = \implode(DIRECTORY_SEPARATOR, $dirParts);
        // remove paths from message
        $errMsg = \preg_replace('/' . \preg_quote($dirStart, '/') . '[\S:]*/', $dirStart . '...', $errMsg);
        // remove floats: "-123.123" -> "###"
        $errMsg = \preg_replace('/\b[\d.-]{4,}\b/', '###', $errMsg);
        // remove integers:  "123" -> "###"
        $errMsg = \preg_replace('/\d+/', '###', $errMsg);
        // remove "comments"..  this allows throttling email, while still adding unique info to user errors
        $errMsg = \preg_replace('/\s*##.+$/', '', $errMsg);
        return \md5($this->values['file'] . $this->values['line'] . $this->values['type'] . $errMsg);
    }

    /**
     * isHtml?  More like "allowHtml"
     *
     * We only allow html_errors if html_errors ini value is true and non-user error
     *
     * @return bool
     */
    private function valIsHtml()
    {
        return \filter_var(\ini_get('html_errors'), FILTER_VALIDATE_BOOLEAN)
            && \in_array($this->values['type'], static::$userErrors, true) === false
            && !$this->values['exception'];
    }

    /**
     * Get initial `isSuppressed` value
     *
     * @return bool
     */
    private function valIsSuppressed()
    {
        $prevOccurrence = $this->subject->get('error', $this->values['hash']);
        if ($prevOccurrence && !$prevOccurrence['isSuppressed']) {
            // if any instance of this error was not suppressed, reflect that
            return false;
        }
        $errType = $this->values['type'];
        if (($this->subject->getCfg('suppressNever') & $errType) === $errType) {
            // never suppress this type
            return false;
        }
        $errorReporting = \error_reporting();
        // @see https://php.watch/versions/8.0/fatal-error-suppression
        $php8suppressValue = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE;
        return $errorReporting === 0
            || (PHP_VERSION_ID >= 80000 && $errorReporting === $php8suppressValue && $errorReporting < $this->subject->get('errorReportingInitial'));
    }
}

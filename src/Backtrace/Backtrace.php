<?php

/**
 * @package   Backtrace
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2020-2023 Brad Kent
 * @version   v2.2
 * @link      http://www.github.com/bkdotcom/Backtrace
 */

namespace bdk;

use bdk\Backtrace\Context;
use bdk\Backtrace\Normalizer;
use bdk\Backtrace\SkipInternal;
use Exception;
use ParseError;

/**
 * Utility for getting backtrace
 *
 * backtrace:
 *    index 0 is current position
 *    file/line are calling _from_
 *    function/class are what's getting called
 */
class Backtrace
{
    const INCL_ARGS = 1;
    const INCL_OBJECT = 2;

    protected static $callerInfoDefault = array(
        'args' => array(),
        'class' => null,         // where the method is defined
        'classCalled' => null,   // parent::method()... this will be the parent class
        'classContext' => null,  // child->method()
        'evalLine' => null,
        'file' => null,
        'function' => null,
        'line' => null,
        'type' => null,
    );

    private static $isXdebugAvail = null;

    /**
     * Add a new namespace or classname to be used to determine when to
     * stop iterrating over the backtrace when determining calling info
     *
     * @param array|string $classes classname(s)
     * @param int          $level   "priority".  0 = will never skip
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public static function addInternalClass($classes, $level = 0)
    {
        SkipInternal::addInternalClass($classes, $level);
    }

    /**
     * Helper method to get backtrace
     *
     * Uses passed exception, xdebug_get_function_stack, or debug_backtrace
     *
     * @param int|null              $options   bitmask of options
     * @param int                   $limit     limit the number of stack frames returned.
     * @param \Exception|\Throwable $exception (optional) Exception from which to get backtrace
     *
     * @return array
     */
    public static function get($options = 0, $limit = 0, $exception = null)
    {
        $debugBacktraceOpts = self::translateOptions($options);
        $limit = $limit ?: null;
        $trace = $exception
            ? self::getExceptionTrace($exception)
            : (\array_reverse(static::xdebugGetFunctionStack() ?: array())
                ?: \debug_backtrace($debugBacktraceOpts, $limit ? $limit + 2 : 0));
        $trace = Normalizer::normalize($trace);
        $trace = SkipInternal::removeInternalFrames($trace);
        // keep the calling file & line, but toss the called function (what initiated trace)
        unset($trace[0]['function']);
        unset($trace[\count($trace) - 1]['function']);  // remove "{main}"
        $trace = \array_slice($trace, 0, $limit);
        $keysRemove = \array_filter(array(
            'args' => ($options & self::INCL_ARGS) !== self::INCL_ARGS,
            'object' => ($options & self::INCL_OBJECT) !== self::INCL_OBJECT,
        ));
        return \array_map(static function ($frame) use ($keysRemove) {
            $frame = \array_diff_key($frame, $keysRemove);
            return $frame;
        }, $trace);
    }

    /**
     * Returns information regarding previous call stack position
     * call_user_func() and call_user_func_array() are skipped
     *
     * Information returned:
     *     function : function/method name
     *     class :    fully qualified classname
     *     file :     file
     *     line :     line number
     *     type :     "->": instance call, "::": static call, null: not object oriented
     *
     * If a method is defined as static:
     *    the class value will always be the class in which the method was defined,
     *    type will always be "::", even if called with an ->
     *
     * @param int $offset  Adjust how far to go back
     * @param int $options bitmask options
     *
     * @return array
     */
    public static function getCallerInfo($offset = 0, $options = 0)
    {
        /*
            we need to collect object... we'll remove object at end if undesired
        */
        $phpOptions = static::translateOptions($options | self::INCL_OBJECT);
        $backtrace = \debug_backtrace($phpOptions, 28);
        $backtrace = Normalizer::normalize($backtrace);
        $index = SkipInternal::getFirstIndex($backtrace, $offset);
        $index = \max($index, 1); // insure we're >= 1
        $return = static::callerInfoBuild(\array_slice($backtrace, $index, 2));
        if (!($options & self::INCL_OBJECT)) {
            unset($return['object']);
        }
        return $return;
    }

    /**
     * Add context (code snippet) to each frame
     *
     * context is an array of `lineNumber => line`
     *
     * @param array $backtrace backtrace frames
     * @param int   $length    number of lines to include
     *
     * @return array backtrace
     */
    public static function addContext(array $backtrace, $length = 19)
    {
        return Context::add($backtrace, $length);
    }

    /**
     * Get lines from a file
     *
     * Returns array of lineNumber => line
     *
     * @param string $file   filepath
     * @param int    $start  line to start on (1 = first line)
     * @param int    $length number of lines to return
     *
     * @return array|false false if file doesn't exist
     */
    public static function getFileLines($file, $start = null, $length = null)
    {
        return Context::getFileLines($file, $start, $length);
    }

    /**
     * Check if `xdebug_get_function_stack()` is available for use
     *
     * @return bool
     */
    public static function isXdebugFuncStackAvail()
    {
        if (self::$isXdebugAvail !== null) {
            return self::$isXdebugAvail;
        }
        // phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
        if (extension_loaded('xdebug') === false) {
            self::$isXdebugAvail = false;
            return false;
        }
        $xdebugVer = \phpversion('xdebug');
        $mode = \ini_get('xdebug.mode') ?: 'off';
        self::$isXdebugAvail = \version_compare($xdebugVer, '3.0.0', '<') || \strpos($mode, 'develop') !== false;
        return self::$isXdebugAvail;
    }

    /**
     * Wrapper for xdebug_get_function_stack
     * accounts for bug 1529 (may report incorrect file)
     *
     * xdebug.collect_params ini must be set prior to running code to be backtraced for params (args) to be collected
     *
     * @return array|false
     *
     * @see https://bugs.xdebug.org/view.php?id=695
     * @see https://bugs.xdebug.org/view.php?id=1529
     * @see https://xdebug.org/docs/all_settings#xdebug.collect_params
     */
    public static function xdebugGetFunctionStack()
    {
        if (static::isXdebugFuncStackAvail() === false) {
            return false;
        }
        $stack = \xdebug_get_function_stack();
        // phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
        $xdebugVer = phpversion('xdebug');
        if (\version_compare($xdebugVer, '2.6.0', '<')) {
            $stack = static::xdebugFix($stack);
        }
        // phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
        $error = error_get_last();
        if ($error !== null && $error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR)) {
            // xdebug_get_function_stack doesn't include the frame that triggered the error!
            $errorFileLine = array(
                'file' => $error['file'],
                'line' => $error['line'],
            );
            $lastFrame = \end($stack);
            if (\array_intersect_assoc($errorFileLine, $lastFrame) !== $errorFileLine) {
                \array_push($stack, $errorFileLine);
            }
        }
        return \array_map(static function ($frame) {
            \ksort($frame);
            return $frame;
        }, $stack);
    }

    /**
     * Build callerInfo array from given backtrace segment
     *
     * @param array $backtrace backtrace
     *
     * @return array
     */
    private static function callerInfoBuild(array $backtrace)
    {
        $return = static::$callerInfoDefault;
        $iFileLine = 0;
        $iFunc = 1;
        if (isset($backtrace[$iFunc])) {
            $return = \array_merge(
                $return,
                $backtrace[$iFunc],
                self::parseFunction($backtrace[$iFunc]['function'])
            );
            $return['classCalled'] = $return['class'];
        }
        if (isset($backtrace[$iFileLine])) {
            $return['file'] = $backtrace[$iFileLine]['file'];
            $return['line'] = $backtrace[$iFileLine]['line'];
        }
        if ($return['type'] === '->') {
            $return['classContext'] = \get_class($backtrace[$iFunc]['object']);
            $return = self::callerInfoClassCalled($return);
        }
        return $return;
    }

    /**
     * Instance method was called...  classCalled
     *
     * @param array $info Caller info
     *
     * @return array
     */
    private static function callerInfoClassCalled(array $info)
    {
        // parent::method()
        //   class : classname of parent (or where method defined)
        //   object : scope / context
        $info['classCalled'] = $info['classContext'];
        $classDeclared = null;
        if ($info['classContext'] !== $info['class']) {
            $reflector = new \ReflectionMethod($info['classContext'], $info['function']);
            $classDeclared = $reflector->getDeclaringClass()->getName();
        }
        if ($classDeclared === $info['classContext']) {
            // method is (re)declared in classContext, yet that's not what's being executed
            // we must have called parent::method()
            $info['classCalled'] = $info['class'];
        }
        return $info;
    }

    /**
     * Get trace from exception
     *
     * @param Exception|Throwable $exception Exception instance
     *
     * @return array
     */
    private static function getExceptionTrace($exception)
    {
        if ($exception instanceof ParseError) {
            return array();
        }
        $backtrace = $exception->getTrace();
        \array_unshift($backtrace, array(
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ));
        return $backtrace;
    }

    /**
     * Parsed "normalized" function into class, type, & function components
     *
     * @param string $function Function string to parse
     *
     * @return array
     */
    private static function parseFunction($function)
    {
        return \preg_match('/^(?<class>\S+)(?<type>::|->)(?<method>\S+)$/', $function, $matches)
            ? array(
                'class' => $matches['class'],
                'function' => $matches['method'],
                'type' => $matches['type'],
            )
            : array(
                'class' => null,
                'function' => $function,
                'type' => null,
            );
    }

    /**
     * Convert our additive options to PHP's options
     *
     * @param int $options bitmask options
     *
     * @return int
     */
    private static function translateOptions($options)
    {
        $options = $options ?: 0;
        $phpOptions = DEBUG_BACKTRACE_IGNORE_ARGS;
        if ($options & self::INCL_ARGS) {
            $phpOptions &= ~DEBUG_BACKTRACE_IGNORE_ARGS;
        }
        if ($options & self::INCL_OBJECT) {
            $phpOptions |= DEBUG_BACKTRACE_PROVIDE_OBJECT;
        }
        return $phpOptions;
    }

    /**
     * Fix xdebug bugs
     *
     * https://bugs.xdebug.org/view.php?id=695 - doesn't set the call type key
     * https://bugs.xdebug.org/view.php?id=1529 - __get : wrong file
     *
     * @param array $stack xdebug stack
     *
     * @return array
     */
    private static function xdebugFix(array $stack)
    {
        $count = \count($stack);
        for ($i = 0; $i < $count; $i++) {
            $frame = \array_merge(array(
                'function' => null,
            ), $stack[$i]);
            if (!isset($frame['type']) && isset($frame['class'])) {
                // XDebug pre 2.1.1 doesn't set the call type key https://bugs.xdebug.org/view.php?id=695
                $stack[$i]['type'] = 'static';
            }
            // __get ... wrong file! - https://bugs.xdebug.org/view.php?id=1529
            if ($frame['function'] === '__get' && isset($stack[$i - 1]['include_filename'])) {
                // if prev frame has include_filename, we can get the correct file,
                //    otherwise, the file will still be wrong
                $stack[$i]['file'] = $stack[$i - 1]['include_filename'];
            }
        }
        return $stack;
    }
}

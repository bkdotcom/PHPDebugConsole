<?php

/**
 * @package   Backtrace
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2020-2023 Brad Kent
 * @version   v2.1
 * @link      http://www.github.com/bkdotcom/Backtrace
 */

namespace bdk;

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
     * Uses passed exception, debug_backtrace, or xdebug_get_function_stack
     *
     * Utilizes `xdebug_get_function_stack()` (if available) to get backtrace in shutdown phase
     *
     * @param int|null              $options   bitmask of options
     * @param int                   $limit     limit the number of stack frames returned.
     * @param \Exception|\Throwable $exception (optional) Exception from which to get backtrace
     *
     * @return array
     */
    public static function get($options = 0, $limit = 0, $exception = null)
    {
        $limit = $limit ?: null;
        if ($exception) {
            $backtrace = self::getExceptionTrace($exception);
            return \array_slice($backtrace, 0, $limit);
        }
        $options = self::translateOptions($options);
        $backtrace = \debug_backtrace($options, $limit ? $limit + 2 : 0);
        if (\array_key_exists('file', \end($backtrace)) === false) {
            // We're in shutdown
            $backtrace = static::xdebugGetFunctionStack() ?: array();
            $backtrace = \array_reverse($backtrace);
        }
        $backtrace = Normalizer::normalize($backtrace);
        $backtrace = SkipInternal::removeInternalFrames($backtrace);
        // keep the calling file & line, but toss the called function (what initiated trace)
        unset($backtrace[0]['function']);
        unset($backtrace[\count($backtrace) - 1]['function']);  // remove "{main}"
        return \array_slice($backtrace, 0, $limit);
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
        $return = static::callerInfoBuild(\array_slice($backtrace, $index));
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
    public static function addContext($backtrace, $length = 19)
    {
        if ($length <= 0) {
            $length = 19;
        }
        $sub = (int) \floor($length  / 2);
        foreach ($backtrace as $i => $frame) {
            $backtrace[$i]['context'] = static::getFileLines(
                $frame['file'],
                \max($frame['line'] - $sub, 0),
                $length
            );
        }
        return $backtrace;
    }

    /**
     * Get lines from a file
     *
     * Returns array of lineNumber => line
     *
     * @param string $file   filepath
     * @param int    $start  line to start on (1-indexed; 1 = first line)
     *                         0 also = first line
     * @param int    $length number of lines to return
     *
     * @return array|false false if file doesn't exist
     */
    public static function getFileLines($file, $start = 1, $length = null)
    {
        $start  = (int) $start;
        $length = (int) $length;
        if (\file_exists($file) === false) {
            return false;
        }
        $lines = \array_merge(array(null), \file($file));
        if ($start <= 0) {
            $start = 1;
        }
        if ($start > 1 || $length) {
            // Get a subset of lines from $start to $end (preserve keys)
            $lines = \array_slice($lines, $start, $length, true);
        }
        return $lines;
    }

    /**
     * Check if `xdebug_get_function_stack()` is available for use
     *
     * @return bool
     */
    public static function isXdebugFuncStackAvail()
    {
        if (\extension_loaded('xdebug') === false) {
            return false;
        }
        $xdebugVer = \phpversion('xdebug');
        if (\version_compare($xdebugVer, '3.0.0', '>=')) {
            $mode = \ini_get('xdebug.mode') ?: 'off';
            if (\strpos($mode, 'develop') === false) {
                return false;
            }
        }
        return true;
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
        $xdebugVer = \phpversion('xdebug');
        if (\version_compare($xdebugVer, '2.6.0', '<')) {
            $stack = static::xdebugFix($stack);
        }
        $error = \error_get_last();
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
    private static function callerInfoBuild($backtrace)
    {
        $return = array(
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
        $iFileLine = 0;
        $iFunc = 1;
        if (isset($backtrace[$iFunc])) {
            $return = \array_merge(
                $return,
                $backtrace[$iFunc],
                \preg_match('/^(?<class>\S+)(?<type>::|->)(?<method>\S+)$/', $backtrace[$iFunc]['function'], $matches)
                    ? array(
                        'class' => $matches['class'],
                        'function' => $matches['method'],
                        'type' => $matches['type'],
                    )
                    : array()
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
    private static function callerInfoClassCalled($info)
    {
        // parent::method()
        //   class : classname of parent (or where method defined)
        //   object : scope / context
        $info['classCalled'] = $info['classContext'];
        if ($info['function'] === '{closure}') {
            return $info;
        }
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
        return Normalizer::normalize($backtrace);
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
            if ($frame['function'] !== '__get') {
                continue;
            }
            // __get ... wrong file! - https://bugs.xdebug.org/view.php?id=1529
            $prev = $stack[$i - 1];
            $stack[$i]['file'] = isset($prev['include_filename'])
                ? $prev['include_filename']
                : $prev['file'];
        }
        return $stack;
    }
}

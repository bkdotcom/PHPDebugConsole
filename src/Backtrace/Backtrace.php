<?php

/**
 * @package   Backtrace
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2020-2022 Brad Kent
 * @version   v2.1
 * @link      http://www.github.com/bkdotcom/Backtrace
 */

namespace bdk;

use bdk\Backtrace\Normalizer;
use bdk\Backtrace\SkipInternal;

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
     * add a new namespace or classname to be used to determine when to
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
     * Utilizes `xdebug_get_function_stack()` (if available) to get backtrace in shutdown phase
     * When called internally, internal frames are removed
     *
     * @param int|null              $options   bitmask of options
     * @param int                   $limit     limit the number of stack frames returned.
     * @param \Exception|\Throwable $exception (optional) Exception from which to get backtrace
     *
     * @return array
     */
    public static function get($options = 0, $limit = 0, $exception = null)
    {
        $options = $options ?: 0;
        $backtrace = static::getBacktrace($options, $limit, $exception);
        if (empty($backtrace)) {
            return array();
        }
        // don't incl args passed to trace()
        $backtrace[0]['args'] = array();
        // keep the calling file & line, but toss the called function (what initiated trace)
        unset($backtrace[0]['function']);
        return $backtrace;
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
        /*
            Must get at least 15 frames to account for potential framework loggers
        */
        $backtrace = \debug_backtrace($phpOptions, 15);
        $index = SkipInternal::getFirstIndex($backtrace, $offset);
        $return = static::callerInfoBuild(\array_slice($backtrace, $index));
        if (!($options & self::INCL_OBJECT)) {
            unset($return['object']);
        }
        return $return;
    }

    /**
     * Add lines surrounding frame line to each frame
     *
     * Adds a `context` value to each backtrace frame
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
     * @param int    $start  line to start on (1-indexed; 1 = line; 1 = first line)
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
        if ($start === 0) {
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
     * Build callerInfo array from given backtrace segment
     *
     * @param array $backtrace backtrace
     *
     * @return array
     */
    private static function callerInfoBuild($backtrace)
    {
        $return = array(
            'class' => null,         // where the method is defined
            'classCalled' => null,   // parent::method()... this will be the parent class
            'classContext' => null,  // child->method()
            'file' => null,
            'function' => null,
            'line' => null,
            'type' => null,
        );
        $numFrames = \count($backtrace);
        $iFileLine = 0;
        $iFunc = 1;
        if (isset($backtrace[$iFunc])) {
            $return = \array_merge($return, $backtrace[$iFunc]);
            $return['classCalled'] = $return['class'];
        }
        if ($return['type'] === '->') {
            $return['classContext'] = \get_class($backtrace[$iFunc]['object']);
            $return = self::callerInfoClassCalled($return);
        }
        if (isset($backtrace[$iFileLine])) {
            $return['file'] = $backtrace[$iFileLine]['file'];
            $return['line'] = $backtrace[$iFileLine]['line'];
        } elseif (isset($backtrace[$numFrames - 1])) {
            $return['file'] = $backtrace[$numFrames - 1]['file'];
            $return['line'] = 0;
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
        if ($info['classContext'] !== $info['class']) {
            $reflector = new \ReflectionMethod($info['classContext'], $info['function']);
            $classDeclared = $reflector->getDeclaringClass()->getName();
            if ($classDeclared === $info['classContext']) {
                // method is (re)declared in classContext, yet that's not what's being executed
                // we must have called parent::method()
                $info['classCalled'] = $info['class'];
            }
        }
        return $info;
    }

    /**
     * Get backtrace from either passed exception,
     * debug_backtrace or xdebug_get_function_stack
     *
     * @param int                   $options   options bitmask
     * @param int                   $limit     limit the number of stack frames returned.
     * @param \Exception|\Throwable $exception (optional) Exception from which to get backtrace
     *
     * @return array|false
     */
    private static function getBacktrace($options = 0, $limit = 0, $exception = null)
    {
        if ($exception instanceof \ParseError) {
            return array();
        }
        $limit = $limit ?: null;
        if ($exception) {
            $backtrace = $exception->getTrace();
            \array_unshift($backtrace, array(
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));
            $backtrace = Normalizer::normalize($backtrace);
            return \array_slice($backtrace, 0, $limit);
        }
        $options = static::translateOptions($options);
        $backtrace = \debug_backtrace($options, $limit ? $limit + 2 : 0);
        if (\array_key_exists('file', \end($backtrace)) === true) {
            // We're NOT in shutdown
            $backtrace = Normalizer::normalize($backtrace);
            $backtrace = SkipInternal::removeInternalFrames($backtrace);
            return \array_slice($backtrace, 0, $limit);
        }
        /*
            We appear to be in shutdown - use xdebug
        */
        return static::getBacktraceXdebug($limit);
    }

    /**
     * Get backtrace via xdebug
     *
     * @param int $limit limit the number of stack frames returned.
     *
     * @return array|false
     */
    private static function getBacktraceXdebug($limit)
    {
        $backtrace = static::xdebugGetFunctionStack();
        if ($backtrace === false) {
            return false;
        }
        $backtrace = \array_reverse($backtrace);
        $backtrace = Normalizer::normalize($backtrace);
        $backtrace = SkipInternal::removeInternalFrames($backtrace);
        $backtrace = \array_slice($backtrace, 0, $limit ?: null);
        $error = \error_get_last();
        if ($error !== null && $error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR)) {
            // xdebug_get_function_stack doesn't include the frame that triggered the error!
            $errorFileLine = array(
                'file' => $error['file'],
                'line' => $error['line'],
            );
            if (\array_intersect_assoc($errorFileLine, $backtrace[0]) !== $errorFileLine) {
                \array_unshift($backtrace, $errorFileLine);
            }
        }
        \end($backtrace);
        $key = \key($backtrace);
        unset($backtrace[$key]['function']);  // remove "{main}"
        return $backtrace;
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
     * wrapper for xdebug_get_function_stack
     * accounts for bug 1529 (may report incorrect file)
     *
     * xdebug.collect_params ini must be set prior to running code to be backtraced for params (args) to be collected
     *
     * @return array|false
     * @see    https://bugs.xdebug.org/view.php?id=695
     * @see    https://bugs.xdebug.org/view.php?id=1529
     * @see    https://xdebug.org/docs/all_settings#xdebug.collect_params
     */
    private static function xdebugGetFunctionStack()
    {
        if (static::isXdebugFuncStackAvail() === false) {
            return false;
        }
        $stack = \xdebug_get_function_stack();
        $xdebugVer = \phpversion('xdebug');
        if (\version_compare($xdebugVer, '2.6.0', '>=')) {
            return $stack;
        }
        $count = \count($stack);
        for ($i = 0; $i < $count; $i++) {
            $frame = \array_merge(array(
                'function' => null,
            ), $stack[$i]);
            if (!isset($frame['type']) && isset($frame['class'])) {
                // XDebug pre 2.1.1 doesn't set the call type key http://bugs.xdebug.org/view.php?id=695
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

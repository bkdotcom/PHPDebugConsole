<?php

/**
 * @package   Backtrace
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2020-2025 Brad Kent
 * @since     v2.2
 * @link      http://www.github.com/bkdotcom/Backtrace
 */

namespace bdk;

use bdk\Backtrace\Context;
use bdk\Backtrace\Normalizer;
use bdk\Backtrace\SkipInternal;
use bdk\Backtrace\Xdebug;
use Exception;
use InvalidArgumentException;
use ParseError;
use Throwable;

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
    const INCL_INTERNAL = 4; // whether to keep "internal" frames

    const REGEX_FUNCTION = '/^(?P<classname>.+)(?P<type>::|->)(?P<method>.+)$/';

    /** @var array */
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

    /**
     * Add a new namespace or classname to be used to determine when to
     * stop iterating over the backtrace when determining calling info
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
     * @param int                   $limit     limit the number of stack frames returned
     * @param \Exception|\Throwable $exception (optional) Exception from which to get backtrace
     *
     * @return array[]
     */
    public static function get($options = 0, $limit = 0, $exception = null)
    {
        $debugBacktraceOpts = self::translateOptions($options) & ~DEBUG_BACKTRACE_IGNORE_ARGS;
        $trace = $exception
            ? self::getExceptionTrace($exception)
            : (\array_reverse(Xdebug::getFunctionStack() ?: [])
                ?: \debug_backtrace($debugBacktraceOpts));
        $trace = Normalizer::normalize($trace);
        return self::getFinish($trace, $options, $limit);
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
        $index = \max($index, 1); // ensure we're >= 1
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
     * @return array[] backtrace
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
     * Build callerInfo array from given backtrace segment
     *
     * @param array $backtrace backtrace
     *
     * @return array[]
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
            $fileLineVals = \array_intersect_key($backtrace[$iFileLine], \array_flip([
                'evalLine',
                'file',
                'line',
            ]));
            $return = \array_merge($return, $fileLineVals);
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
            return [];
        }
        $trace = $exception->getTrace();
        $fileLine = array(
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        );
        if (\array_intersect_assoc($fileLine, \reset($trace) ?: []) !== $fileLine) {
            \array_unshift($trace, $fileLine);
        }
        return $trace;
    }

    /**
     * Take normalized backtrace and finish it up
     *
     * @param array $trace   backtrace frames
     * @param int   $options bitmask of options
     * @param int   $limit   limit the number of stack frames returned
     *
     * @return array
     */
    private static function getFinish(array $trace, $options, $limit)
    {
        $sliceLimit = $limit ?: null;
        if (($options & self::INCL_INTERNAL) !== self::INCL_INTERNAL) {
            $trace = SkipInternal::removeInternalFrames($trace);
        }
        // keep the calling file & line, but toss the called function (what initiated trace)
        unset($trace[0]['function']);
        unset($trace[\count($trace) - 1]['function']);  // remove "{main}"
        $trace = \array_slice($trace, 0, $sliceLimit);
        if (($options & self::INCL_ARGS) !== self::INCL_ARGS) {
            $trace = self::getRenameFunctions($trace);
        }
        return self::getRemoveKeys($trace, $options);
    }

    /**
     * Rename __call & __callStatic to include method name
     *
     * @param array $trace backtrace frames
     *
     * @return array
     */
    private static function getRenameFunctions(array $trace)
    {
        $count = \count($trace);
        for ($i = 1; $i < $count - 1; $i++) {
            \preg_match(self::REGEX_FUNCTION, $trace[$i]['function'], $matches);
            if ($matches && \in_array($matches['method'], ['__call', '__callStatic'], true) && $trace[$i]['args']) {
                $trace[$i]['function'] = \sprintf(
                    '%s(\'%s\')',
                    $trace[$i]['function'],
                    \addslashes(\reset($trace[$i]['args']))
                );
            }
        }
        return $trace;
    }

    /**
     * Remove the keys we don't want
     *
     * @param array $trace   backtrace frames
     * @param int   $options bitmask of options
     *
     * @return array
     */
    private static function getRemoveKeys(array $trace, $options)
    {
        $keysRemove = \array_filter(array(
            'args' => ($options & self::INCL_ARGS) !== self::INCL_ARGS,
            'object' => ($options & self::INCL_OBJECT) !== self::INCL_OBJECT,
        ));
        return \array_map(static function ($frame) use ($keysRemove) {
            return \array_diff_key($frame, $keysRemove);
        }, $trace);
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
        return \preg_match(self::REGEX_FUNCTION, $function, $matches)
            ? array(
                'class' => $matches['classname'],
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
     * @param int|null $options bitmask options
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
}

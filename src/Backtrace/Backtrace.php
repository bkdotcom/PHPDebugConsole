<?php

/**
 * @package   Backtrace
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2020 Brad Kent
 * @version   v1
 * @link      http://www.github.com/bkdotcom/Backtrace
 */

namespace bdk;

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
     * @var array
     */
    private static $internalClasses = array(
        'classes' => array(),
        'regex' => '',
    );

    /**
     * Helper method to get backtrace
     *
     * Utilizes `xdebug_get_function_stack()` (if available) to get backtrace in shutdown phase
     * When called internally, internal frames are removed
     *
     * @param Exception|Throwable $exception (optional) Exception from which to get backtrace
     * @param bool                $inclArgs  (false) whether to include arguments
     *
     * @return array
     */
    public static function get($exception = null, $inclArgs = false)
    {
        if ($exception) {
            $backtrace = $exception->getTrace();
            \array_unshift($backtrace, array(
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));
            $backtrace = static::normalize($backtrace);
        } else {
            $backtrace = \debug_backtrace($inclArgs ? null : DEBUG_BACKTRACE_IGNORE_ARGS);
            if (!\array_key_exists('file', \end($backtrace))) {
                /*
                    We appear to be in shutdown
                */
                if (!\extension_loaded('xdebug')) {
                    return array();
                }
                $backtrace = static::xdebugGetFunctionStack();
                $backtrace = \array_reverse($backtrace);
                $backtrace = static::normalize($backtrace);
                $backtrace = static::removeInternalFrames($backtrace);
                $error = \error_get_last();
                if ($error && $error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR)) {
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
            } else {
                $backtrace = static::normalize($backtrace);
                $backtrace = static::removeInternalFrames($backtrace);
            }
        }
        // keep the calling file & line, but toss the called function (what initiated trace)
        $backtrace[0]['args'] = array();
        // don't incl args passed to trace()
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
     * @param int $offset Adjust how far to go back
     * @param int $flags  optional INCL_ARGS
     *
     * @return array
     */
    public static function getCallerInfo($offset = 0, $flags = 0)
    {
        /*
            we need to collect object... we'll remove object at end if undesired
        */
        $options = DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT;
        if ($flags & self::INCL_ARGS) {
            $options &= ~DEBUG_BACKTRACE_IGNORE_ARGS;
        }
        /*
            Must get at least backtrace 13 frames to account for potential framework loggers
        */
        $backtrace = \debug_backtrace($options, 13);
        $count = \count($backtrace);
        for ($i = 1; $i < $count; $i++) {
            $frame = $backtrace[$i];
            $class = isset($frame['class'])
                ? $frame['class']
                : null;
            if (static::$internalClasses['regex'] && \preg_match(static::$internalClasses['regex'], $class)) {
                continue;
            }
            if ($frame['function'] === '{closure}') {
                continue;
            }
            if (
                \in_array($frame['function'], array('call_user_func', 'call_user_func_array'))
                || $class === 'ReflectionMethod'
                    && \in_array($frame['function'], array('invoke','invokeArgs'))
            ) {
                continue;
            }
            break;
        }
        $i--;
        $i = \max($i, 1);
        /*
            file/line values may be missing... if frame called via core PHP function/method
        */
        for ($i = $i + $offset; $i < $count; $i++) {
            if (isset($backtrace[$i]['line'])) {
                break;
            }
        }
        $return = static::getCallerInfoBuild(\array_slice($backtrace, $i));
        if (!($flags & self::INCL_OBJECT)) {
            unset($return['object']);
        }
        return $return;
    }

    /**
     * add a new namespace, classname or filepath to be used to determine when to
     * stop iterrating over the backtrace when determining calling info
     *
     * @param array|string $class classname(s)
     *
     * @return void
     */
    public static function addInternalClass($class)
    {
        self::$internalClasses['classes'] = \array_merge(self::$internalClasses['classes'], (array) $class);
        self::$internalClasses['classes'] = \array_unique(self::$internalClasses['classes']);
        self::$internalClasses['regex'] = '/^('
            . \implode('|', \array_map('preg_quote', self::$internalClasses['classes']))
            . ')\b/';
    }

    /**
     * Get lines surrounding frame line
     *
     * @param array $backtrace backtrace frames
     * @param int   $length    number of lines to include
     *
     * @return array
     */
    public static function addContext($backtrace, $length = 19)
    {
        if ($length <= 0) {
            $length = 19;
        }
        $sub = \floor($length  / 2);
        foreach ($backtrace as $i => $frame) {
            $backtrace[$i]['context'] = \file_exists($frame['file'])
                ? static::getFileLines($frame['file'], \max($frame['line'] - $sub, 0), $length)
                : null;
        }
        return $backtrace;
    }

    /**
     * Build callerInfo array from given backtrace segment
     *
     * @param array $backtrace backtrace
     *
     * @return array
     */
    private static function getCallerInfoBuild($backtrace)
    {
        $return = array(
            'file' => null,
            'line' => null,
            'function' => null,
            'class' => null,
            'type' => null,
        );
        $numFrames = \count($backtrace);
        $iLine = 0;
        $iFunc = 1;
        if (isset($backtrace[$iFunc])) {
            $return = \array_merge($return, $backtrace[$iFunc]);
            if ($return['type'] === '->') {
                // class that debug_backtrace returns is the class the function is defined in vs the class that was called
                $return['class'] = \get_class($backtrace[$iFunc]['object']);
            }
        }
        if (isset($backtrace[$iLine])) {
            $return['file'] = $backtrace[$iLine]['file'];
            $return['line'] = $backtrace[$iLine]['line'];
        } elseif (isset($backtrace[$numFrames - 1])) {
            $return['file'] = $backtrace[$numFrames - 1]['file'];
            $return['line'] = 0;
        }
        return $return;
    }

    /**
     * Get lines from a file
     *
     * @param string $file   filepath
     * @param int    $start  line to start on (1-indexed; 1 = line; 1 = first line)
     *                         0 also = first line
     * @param int    $length number of lines to return
     *
     * @return array
     */
    private static function getFileLines($file, $start = 1, $length = null)
    {
        $start  = (int) $start;
        $length = (int) $length;
        $lines = \array_merge(array(null), \file($file));
        if ($start === 0) {
            $start = 1;
        }
        if ($start > 1 || $length) {
            // Get a subset of lines from $start to $end
            $lines = \array_slice($lines, $start, $length, true);
        }
        return $lines;
    }

    /**
     * "Normalize" backtrace from debug_backtrace() or xdebug_get_function_stack();
     *
     * @param array $backtrace trace/stack from debug_backtrace() or xdebug_Get_function_stack()
     *
     * @return array
     */
    private static function normalize($backtrace)
    {
        $backtraceNew = array();
        $frameDefault = array(
            'file' => null,
            'line' => null,
            'function' => null,     // function, Class::function, or Class->function
            'class' => null,        // will get removed
            'type' => null,         // will get removed
            'args' => array(),
            'evalLine' => null,
        );
        $funcsSkip = array('call_user_func','call_user_func_array');
        $funcsSkipRegex = '/^(' . \implode('|', $funcsSkip) . ')[:\(\{]/';
        for ($i = 0, $count = \count($backtrace); $i < $count; $i++) {
            $frame = \array_merge($frameDefault, $backtrace[$i]);
            $frame = \array_intersect_key($frame, $frameDefault);
            if (\in_array($frame['function'], $funcsSkip) || \preg_match($funcsSkipRegex, $frame['function'])) {
                $backtraceNew[\count($backtraceNew) - 1]['file'] = $frame['file'];
                $backtraceNew[\count($backtraceNew) - 1]['line'] = $frame['line'];
                continue;
            }
            if (
                $frame['class'] === 'ReflectionMethod'
                    && \in_array($frame['function'], array('invoke','invokeArgs'))
            ) {
                continue;
            }
            if (\in_array($frame['type'], array('dynamic','static'))) {
                // xdebug_get_function_stack
                $frame['type'] = $frame['type'] === 'dynamic' ? '->' : '::';
            }
            if (\preg_match('/^(.+)\((\d+)\) : eval\(\)\'d code$/', $frame['file'], $matches)) {
                // reported line = line within eval
                // line inside paren is the line `eval` is on
                $frame['evalLine'] = $frame['line'];
                $frame['file'] = $matches[1];
                $frame['line'] = (int) $matches[2];
            }
            if (isset($backtrace[$i]['params'])) {
                // xdebug_get_function_stack
                $frame['args'] = $backtrace[$i]['params'];
            }
            if ($frame['file'] === null) {
                // use file/line from next frame
                $frame = \array_merge($frame, \array_intersect_key($backtrace[$i + 1], \array_flip(array('file','line'))));
            }
            if (isset($backtrace[$i]['include_filename'])) {
                // xdebug_get_function_stack
                $frame['function'] = 'include or require';
            } elseif ($frame['function']) {
                $frame['function'] = \preg_match('/\{closure\}$/', $frame['function'])
                    ? $frame['function']
                    : $frame['class'] . $frame['type'] . $frame['function'];
            } else {
                unset($frame['function']);
            }
            unset($frame['class'], $frame['type']);
            $backtraceNew[] = $frame;
        }
        return $backtraceNew;
    }

    /**
     * Remove internal frames from backtrace
     *
     * @param array $backtrace backtrace
     *
     * @return array
     */
    private static function removeInternalFrames($backtrace)
    {
        $count = \count($backtrace);
        $i = 1;
        if (static::$internalClasses['regex']) {
            for (; $i < $count; $i++) {
                if (!\preg_match(static::$internalClasses['regex'], $backtrace[$i]['function'])) {
                    break;
                }
            }
        }
        if ($backtrace[$i - 1]['line'] !== 0) {
            $i--;
        }
        $i = \max(0, $i);
        return \array_slice($backtrace, $i);
    }

    /**
     * wrapper for xdebug_get_function_stack
     * accounts for bug 1529 (may report incorrect file)
     *
     * xdebug.collect_params ini must be set prior to running code to be backtraced for params (args) to be collected
     *
     * @return array
     * @see    https://bugs.xdebug.org/view.php?id=1529
     * @see    https://xdebug.org/docs/all_settings#xdebug.collect_params
     */
    private static function xdebugGetFunctionStack()
    {
        $stack = \xdebug_get_function_stack();
        $xdebugVer = \phpversion('xdebug');
        if (\version_compare($xdebugVer, '2.6.0', '<')) {
            $count = \count($stack);
            for ($i = 0; $i < $count; $i++) {
                $frame = $stack[$i];
                $function = isset($frame['function'])
                    ? $frame['function']
                    : null;
                if ($function === '__get') {
                    // wrong file!
                    $prev = $stack[$i - 1];
                    $stack[$i]['file'] = isset($prev['include_filename'])
                        ? $prev['include_filename']
                        : $prev['file'];
                }
            }
        }
        return $stack;
    }
}

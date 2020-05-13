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
        'regex' => '/^\b$/',  // start with a regex that will never match
    );

    /**
     * Helper method to get backtrace
     *
     * Utilizes `xdebug_get_function_stack()` (if available) to get backtrace in shutdown phase
     * When called internally, internal frames are removed
     *
     * @param \Exception|\Throwable $exception (optional) Exception from which to get backtrace
     * @param bool                  $inclArgs  (false) whether to include arguments
     *
     * @return array|false
     */
    public static function get($exception = null, $inclArgs = false)
    {
        $backtrace = self::getBacktrace($exception, $inclArgs);
        if (empty($backtrace)) {
            return $backtrace;
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
            Must get at least 13 frames to account for potential framework loggers
        */
        $backtrace = \debug_backtrace($options, 13);
        $count = \count($backtrace);
        for ($i = 1; $i < $count; $i++) {
            if (self::isSkippable($backtrace[$i])) {
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
     * Get backtrace from either passed exception,
     * debug_backtrace or xdebug_get_function_stack
     *
     * @param \Exception|\Throwable $exception (optional) Exception from which to get backtrace
     * @param bool                  $inclArgs  whether to include arguments
     *
     * @return array|false
     */
    private static function getBacktrace($exception, $inclArgs)
    {
        if ($exception) {
            $backtrace = $exception->getTrace();
            \array_unshift($backtrace, array(
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));
            $backtrace = static::normalize($backtrace);
            return $backtrace;
        }
        $backtrace = \debug_backtrace($inclArgs ? null : DEBUG_BACKTRACE_IGNORE_ARGS);
        if (\array_key_exists('file', \end($backtrace)) === true) {
            // We're NOT in shutdown
            $backtrace = static::normalize($backtrace);
            $backtrace = static::removeInternalFrames($backtrace);
            return $backtrace;
        }
        /*
            We appear to be in shutdown - use xdebug
        */
        $backtrace = static::xdebugGetFunctionStack();
        if ($backtrace === false) {
            return false;
        }
        $backtrace = \array_reverse($backtrace);
        $backtrace = static::normalize($backtrace);
        $backtrace = static::removeInternalFrames($backtrace);
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
     * Build callerInfo array from given backtrace segment
     *
     * @param array $backtrace backtrace
     *
     * @return array
     */
    private static function getCallerInfoBuild($backtrace)
    {
        $return = array(
            'class' => null,
            'file' => null,
            'function' => null,
            'line' => null,
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
     * Test if frame is skippable
     *
     * @param array $frame frame
     *
     * @return bool
     */
    private static function isSkippable($frame)
    {
        $class = isset($frame['class'])
            ? $frame['class']
            : null;
        if (\preg_match(static::$internalClasses['regex'], $class)) {
            return true;
        }
        if ($frame['function'] === '{closure}') {
            return true;
        }
        if (\in_array($frame['function'], array('call_user_func', 'call_user_func_array'))) {
            return true;
        }
        return $class === 'ReflectionMethod' && \in_array($frame['function'], array('invoke','invokeArgs'));
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
            'args' => array(),
            'evalLine' => null,
            'file' => null,
            'function' => null,     // function, Class::function, or Class->function
            'line' => null,
        );
        $frameTemp = array(
            'class' => null,
            'include_filename' => null,
            'params' => null,
            'type' => null,
        );
        $funcsSkip = array('call_user_func','call_user_func_array');
        $funcsSkipRegex = '/^(' . \implode('|', $funcsSkip) . ')\b[:\(\{]?/';
        $count = \count($backtrace);
        $backtrace[] = array(); // add a frame so backtrace[$i + 1] is always a thing
        for ($i = 0; $i < $count; $i++) {
            $frame = \array_merge($frameDefault, $frameTemp, $backtrace[$i]);
            if (\preg_match($funcsSkipRegex, $frame['function'])) {
                // update previous frame's file & line
                $backtraceNew[\count($backtraceNew) - 1]['file'] = $frame['file'];
                $backtraceNew[\count($backtraceNew) - 1]['line'] = $frame['line'];
                continue;
            }
            if ($frame['class'] === 'ReflectionMethod' && \in_array($frame['function'], array('invoke','invokeArgs'))) {
                continue;
            }
            $frame = self::normalizeFrame($frame, $backtrace[$i + 1]);
            $frame = \array_intersect_key($frame, $frameDefault);
            $backtraceNew[] = $frame;
        }
        return $backtraceNew;
    }

    /**
     * Normalize frame
     *
     * Normalize file & line
     * Normalize function: Combine class, type, & function
     * Normalize args
     *
     * @param array $frame     current frame
     * @param array $frameNext next frrame
     *
     * @return array
     */
    private static function normalizeFrame($frame, $frameNext)
    {
        /*
            Normalize File
        */
        $regex = '/^(.+)\((\d+)\) : eval\(\)\'d code$/';
        $matches = array();
        if (\preg_match($regex, $frame['file'], $matches)) {
            // reported line = line within eval
            // line inside paren is the line `eval` is on
            $frame['evalLine'] = $frame['line'];
            $frame['file'] = $matches[1];
            $frame['line'] = (int) $matches[2];
        }
        if ($frame['file'] === null) {
            // use file/line from next frame
            $frame = \array_merge(
                $frame,
                \array_intersect_key($frameNext, \array_flip(array('file','line')))
            );
        }
        /*
            Normalize Function / unset if empty
        */
        $frame['type'] = \strtr($frame['type'], array(
            'dynamic' => '->',
            'static' => '::',
        ));
        if ($frame['include_filename']) {
            // xdebug_get_function_stack
            $frame['function'] = 'include or require';
        } elseif ($frame['function']) {
            $frame['function'] = \preg_match('/\{closure\}$/', $frame['function'])
                ? $frame['function']
                : $frame['class'] . $frame['type'] . $frame['function'];
        }
        if (!$frame['function']) {
            unset($frame['function']);
        }
        /*
            Normalize Params
        */
        if ($frame['params']) {
            // xdebug_get_function_stack
            $frame['args'] = $frame['params'];
        }
        return $frame;
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
        $i = 2;
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
     * @return array|false
     * @see    https://bugs.xdebug.org/view.php?id=1529
     * @see    https://xdebug.org/docs/all_settings#xdebug.collect_params
     */
    private static function xdebugGetFunctionStack()
    {
        if (\extension_loaded('xdebug') === false) {
            return false;
        }
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

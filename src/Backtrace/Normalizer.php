<?php

/**
 * @package   Backtrace
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2020-2022 Brad Kent
 * @version   v2.1
 * @link      http://www.github.com/bkdotcom/Backtrace
 */

namespace bdk\Backtrace;

/**
 * Normalize backtrace frames
 */
class Normalizer
{
    private static $backtraceTemp = array();

    private static $frameDefault = array(
        'args' => array(),
        'evalLine' => null,
        'file' => null,
        'function' => null,     // function, Class::function, or Class->function
        'line' => null,
    );

    /**
     * Cache whether non-namespaced functions are internal or not
     *
     * @var array
     */
    private static $internalFuncs = array(
        '{closure}' => false,
        'include' => false,
        'include_once' => false,
        'require' => false,
        'require_once' => false,
        'trigger_error' => false,
        'user_error' => false,
    );

    /**
     * Test if frame is a non-namespaced internal function
     * if so, it must have a callable arg, such as
     * array_map, array_walk, call_user_func, or call_user_func_array
     *
     * @param array $frame backtrace frame
     *
     * @return bool
     */
    public static function isInternal($frame)
    {
        if (isset($frame['class']) || empty($frame['function'])) {
            return false;
        }
        $function = $frame['function'];
        if (\preg_match('/^.*\{closure:(.+):(\d+)-(\d+)\}$/', $frame['function'])) {
            return false;
        }
        if (!isset(self::$internalFuncs[$function])) {
            // avoid `function require() does not exit
            $isInternal = true;
            if (\function_exists($function)) {
                $refFunction = new \ReflectionFunction($function);
                $isInternal = $refFunction->isInternal();
            }
            self::$internalFuncs[$function] = $isInternal;
        }
        return self::$internalFuncs[$function];
    }

    /**
     * "Normalize" backtrace from debug_backtrace() or xdebug_get_function_stack();
     *
     * @param array $backtrace trace/stack from debug_backtrace() or xdebug_Get_function_stack()
     *
     * @return array
     */
    public static function normalize($backtrace)
    {
        self::$backtraceTemp = array();
        $frameTemp = array(
            'class' => null,
            'include_filename' => null,
            'params' => null,
            'type' => null,
        );
        $count = \count($backtrace);
        $backtrace[] = array(); // add a frame so backtrace[$i + 1] is always a thing
        for ($i = 0; $i < $count; $i++) {
            $frame = \array_merge(self::$frameDefault, $frameTemp, $backtrace[$i]);
            $include = self::normalizeFrameA($frame);
            if (!$include) {
                continue;
            }
            $frame = self::normalizeFrameFile($frame, $backtrace[$i + 1]);
            $frame = self::normalizeFrameFunction($frame);
            if ($frame['params']) {
                // xdebug_get_function_stack
                $frame['args'] = $frame['params'];
            }
            $frame = \array_intersect_key($frame, self::$frameDefault);
            self::$backtraceTemp[] = $frame;
        }
        return self::$backtraceTemp;
    }

    /**
     * Process backtrace frame
     *
     * @param array $frame current frame
     *
     * @return bool whether or not to include frame
     */
    private static function normalizeFrameA(array $frame)
    {
        if (self::isInternal($frame)) {
            // update previous frame's file & line
            $count = \count(self::$backtraceTemp);
            self::$backtraceTemp[$count - 1]['file'] = $frame['file'];
            self::$backtraceTemp[$count - 1]['line'] = $frame['line'];
            return false;
        }
        if ($frame['class'] === 'ReflectionMethod' && \in_array($frame['function'], array('invoke','invokeArgs'), true)) {
            return false;
        }
        if ($frame['include_filename']) {
            self::$backtraceTemp[] = \array_merge(self::$frameDefault, array(
                'file' => $frame['include_filename'],
                'line' => 0,
            ));
        }
        return true;
    }

    /**
     * Normalize file value
     *
     * @param array $frame     current frame
     * @param array $frameNext next frrame
     *
     * @return array
     */
    private static function normalizeFrameFile(array $frame, array $frameNext)
    {
        $regex = '/^(.+)\((\d+)\) : eval\(\)\'d code$/';
        $matches = array();
        if ($frame['file'] === null) {
            // use file/line from next frame
            $frame = \array_merge(
                $frame,
                \array_intersect_key($frameNext, \array_flip(array('file','line')))
            );
        } elseif (\preg_match($regex, $frame['file'], $matches)) {
            // reported line = line within eval
            // line inside paren is the line `eval` is on
            $frame['evalLine'] = $frame['line'];
            $frame['file'] = $matches[1];
            $frame['line'] = (int) $matches[2];
        }
        return $frame;
    }

    /**
     * Normalize Function Combine class, type, & function
     * unset if empty
     *
     * @param array $frame backtrace frame
     *
     * @return array
     */
    private static function normalizeFrameFunction(array $frame)
    {
        $frame['type'] = \strtr((string) $frame['type'], array(
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
        return $frame;
    }
}

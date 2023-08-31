<?php

/**
 * @package   Backtrace
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2020-2023 Brad Kent
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
        'object' => null,
    );

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
            $frame = self::normalizeFrameFile($frame, $backtrace[$i + 1]);
            $frame = self::normalizeFrameFunction($frame);
            if (\in_array($frame['function'], array('call_user_func', 'call_user_func_array'), true)) {
                // don't include this frame
                //   backtrace only includes when used within namespace and not fully-quallified
                //   \call_user_func(); // not in trace... same as calling func directly
                continue;
            }
            if ($frame['params']) {
                // xdebug_get_function_stack
                $frame['args'] = self::normalizeXdebugArgs($frame['params']);
            }
            $frame = \array_intersect_key($frame, self::$frameDefault);
            \ksort($frame);
            self::$backtraceTemp[] = $frame;
        }
        return self::$backtraceTemp;
    }

    /**
     * Normalize file value
     *
     * @param array $frame     current frame
     * @param array $frameNext next frrame
     *
     * @return array
     */
    private static function normalizeFrameFile(array $frame, array &$frameNext)
    {
        $regexEvaldCode = '/^(.+)\((\d+)\) : eval\(\)\'d code$/';
        $matches = array();
        if ($frame['file'] === null) {
            // use file/line from next frame
            $frame = \array_merge(
                $frame,
                \array_intersect_key($frameNext, \array_flip(array('file', 'line')))
            );
        } elseif (\preg_match($regexEvaldCode, $frame['file'], $matches)) {
            // reported line = line within eval
            // line inside paren is the line `eval` is on
            $frame['evalLine'] = (int) $matches[2];
            $frame['file'] = $matches[1];
            if (isset($frameNext['include_filename'])) {
                $frameNext['class'] = null;
                $frameNext['function'] = 'eval';
                $frameNext['include_filename'] = null;
            }
        }
        if ($frame['include_filename']) {
            // xdebug_get_function_stack
            $frame['class'] = null;
            self::$backtraceTemp[] = \array_merge(self::$frameDefault, array(
                'file' => $frame['include_filename'],
                'function' => 'include or require',
                'line' => 0,
            ));
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
        if (\preg_match('/^(call_user_func(?:_array)?):\{.+:\d+\}$/', (string) $frame['function'], $matches)) {
            // xdebug_get_function_stack
            $frame['function'] = $matches[1];
        }
        if (\preg_match('/^(.*)\{closure(?::(.*):(\d*)-(\d*))?\}$/', (string) $frame['function'])) {
            // both debug_backtrace and xdebug_get_function_stack may have the namespace prefix
            //   xdebug provides the filepath, start and end lines
            $frame['function'] = '{closure}';
        } elseif ($frame['class']) {
            $frame['function'] = $frame['class'] . $frame['type'] . $frame['function'];
        }
        return $frame;
    }

    /**
     * de-stringify most params
     *
     * @param array $args Xdebug frame "params"
     *
     * @return array
     */
    private static function normalizeXdebugArgs($args)
    {
        $map = array(
            'FALSE' => false,
            'NULL' => null,
            'TRUE' => true,
        );
        $args = \array_map(static function ($param) use ($map) {
            if ($param[0] === "'") {
                return \substr(\stripslashes($param), 1, -1);
            }
            if (\is_numeric($param)) {
                return $param * 1;
            }
            return \array_key_exists($param, $map)
                ? $map[$param]
                : $param;
        }, $args);
        return \array_values($args);
    }
}

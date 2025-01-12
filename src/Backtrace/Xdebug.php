<?php

/**
 * @package   Backtrace
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2020-2025 Brad Kent
 * @since     v2.2
 * @link      http://www.github.com/bkdotcom/Backtrace
 */

namespace bdk\Backtrace;

/**
 * Wrapper for `xdebug_get_function_stack`
 */
class Xdebug
{
    /** @var bool|null */
    private static $isXdebugAvail = null;

    /**
     * Wrapper for xdebug_get_function_stack
     * accounts for bug 1529 (may report incorrect file)
     *
     * xdebug.collect_params ini must be set prior to running code to be backtraced for params (args) to be collected
     *
     * @param int $maxDepth set xdebug.var_display_max_depth ini/config
     *
     * @return array[]|false
     *
     * @see https://bugs.xdebug.org/view.php?id=695
     * @see https://bugs.xdebug.org/view.php?id=1529
     * @see https://xdebug.org/docs/all_settings#xdebug.collect_params
     */
    public static function getFunctionStack($maxDepth = 3)
    {
        if (static::isXdebugFuncStackAvail() === false) {
            return false;
        }
        $vdmdKey = 'xdebug.var_display_max_depth';
        $vdmdBak = \ini_get($vdmdKey);
        \ini_set($vdmdKey, (string) $maxDepth);
        $stack = \xdebug_get_function_stack();
        \ini_set($vdmdKey, $vdmdBak);
        // phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
        $xdebugVer = phpversion('xdebug');
        if (\version_compare($xdebugVer, '2.6.0', '<')) {
            $stack = static::xdebugFix($stack);
        }
        $stack = static::xdebugAddError($stack);
        return \array_map(static function ($frame) {
            \ksort($frame);
            return $frame;
        }, $stack);
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
     * Add file/line that triggered error to stack
     *
     * @param array $stack xdebug stack
     *
     * @return array
     */
    private static function xdebugAddError(array $stack)
    {
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
        return $stack;
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

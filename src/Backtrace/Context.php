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
 * Utility for adding context (code snippet) to backtrace frames
 */
class Context
{
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
    public static function add(array $backtrace, $length = 19)
    {
        if ($length <= 0) {
            $length = 19;
        }
        $sub = (int) \floor($length  / 2);
        return \array_map(static function ($i, $frame) use ($backtrace, $length, $sub) {
            $lines = isset($frame['evalLine'])
                ? self::findEvalCode($backtrace, $i)
                : self::getFileLines($frame['file']);
            $line = isset($frame['evalLine'])
                ? $frame['evalLine']
                : $frame['line'];
            $frame['context'] = $lines
                ? self::sliceLines(
                    $lines,
                    \max($line - $sub, 0),
                    $length
                )
                : false;
            return $frame;
        }, \array_keys($backtrace), $backtrace);
    }

    /**
     * Get lines from a file
     *
     * Returns array of lineNumber => line
     *
     * @param string   $file   filepath
     * @param int|null $start  line to start on (1 = first line)
     * @param int|null $length number of lines to return
     *
     * @return array|false false if file doesn't exist
     */
    public static function getFileLines($file, $start = null, $length = null)
    {
        if (!$file || \file_exists($file) === false) {
            return false;
        }
        $lines = \file($file);
        // reindex lines to start with 1
        \array_unshift($lines, null);
        unset($lines[0]);
        if ($start !== null || $length !== null) {
            $lines = self::sliceLines($lines, $start, $length);
        }
        return $lines;
    }

    /**
     * Attempt to find code that was eval'd
     *
     * @param array $backtrace Backtrace frames
     * @param int   $index     Backtrace index to begin search
     *
     * @return array|false
     */
    private static function findEvalCode(array $backtrace, $index)
    {
        $backtrace = \array_slice($backtrace, $index);
        $lines = self::findEvalCodeLines($backtrace);
        if ($lines === false) {
            return false;
        }
        $lines = \preg_split('/([\r\n])/', $lines, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 0, $n = \count($lines) - 1; $i < $n; $i += 2) {
            $lines[$i] = $lines[$i] . $lines[$i + 1];
            unset($lines[$i + 1]);
        }
        return \array_values($lines);
    }

    /**
     * find eval frame and return first arg (the eval'd code)
     *
     * @param array $frames backtrace frames
     *
     * @return string|false
     */
    private static function findEvalCodeLines($frames)
    {
        foreach ($frames as $frame) {
            if (!isset($frame['function'])) {
                return false;
            }
            if ($frame['function'] !== 'eval') {
                continue;
            }
            break;
        }
        return isset($frame['args'][0])
            ? $frame['args'][0]
            : false;
    }

    /**
     * "slice" lines of code
     *
     * Essentially array_slice but one-based vs zero-based
     *
     * @param array    $lines  lines of code
     * @param int|null $start  line to start on (1 = first line)
     * @param int|null $length number of lines to return
     *
     * @return array
     */
    private static function sliceLines(array $lines, $start = 1, $length = null)
    {
        \reset($lines);
        if (\key($lines) === 0) {
            // reindex lines to start with 1
            \array_unshift($lines, null);
            unset($lines[0]);
        }
        $start  = \max($start - 1, 0);
        $length = (int) $length;
        if ($start || $length) {
            // Get a subset of lines (preserve keys)
            $lines = \array_slice($lines, $start, $length, true);
        }
        return $lines;
    }
}

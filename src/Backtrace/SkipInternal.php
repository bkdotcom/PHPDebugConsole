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

use bdk\Backtrace\Normalizer;
use InvalidArgumentException;

/**
 * Utility for Skipping over frames "internal" to debugger or framework
 *
 * backtrace:
 *    index 0 is current position
 *    file/line are calling _from_
 *    function/class are what's getting called
 */
class SkipInternal
{
    /**
     * @var array
     */
    private static $internalClasses = array(
        'classes' => array(
            __NAMESPACE__ => 0, // the lower the number, the more we'll enforce skipping
        ),
        'levelCurrent' => null,
        'levels' => array(0),
        # 'regex' => '/^bdk\\\Backtrace\b$/',
        'regex' => null,
    );

    /**
     * add a new namespace or classname to be used to determine when to
     * stop iterrating over the backtrace when determining calling info
     *
     * @param array|string $classes classname(s)
     * @param int          $level   "priority".  0 = will never skipp
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public static function addInternalClass($classes, $level = 0)
    {
        if (\is_int($level) === false) {
            throw new InvalidArgumentException(\sprintf('level must be an integer'));
        }
        if (\is_array($classes) === false) {
            $classes = array($classes => $level);
        }
        foreach ($classes as $key => $val) {
            if (\is_int($key)) {
                unset($classes[$key]);
                $classes[$val] = $level;
            }
        }
        self::$internalClasses['classes'] = \array_merge(self::$internalClasses['classes'], $classes);
        self::$internalClasses['levels'] = \array_values(\array_unique(self::$internalClasses['classes']));
        \sort(self::$internalClasses['levels']);
        self::$internalClasses['levelCurrent'] = \end(self::$internalClasses['levels']);
    }

    /**
     * Determine calling backtrace frame
     *
     * @param array    $backtrace Backtrace
     * @param int      $offset    Adjust how far to go back
     * @param int|null $levelMax  (internal)
     *
     * @return int
     */
    public static function getFirstIndex($backtrace, $offset, $levelMax = null)
    {
        $levelMax = self::initSkippableTests($levelMax);
        $count = \count($backtrace);
        for ($i = 1; $i < $count; $i++) {
            if (static::isSkippable($backtrace[$i], $levelMax)) {
                continue;
            }
            break;
        }
        if ($i === $count && $levelMax > 0) {
            // every frame was skipped.. let's try again
            return self::getFirstIndex($backtrace, $offset, $levelMax - 1);
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
        return $i;
    }

    /**
     * Remove internal frames from backtrace
     *
     * @param array $backtrace backtrace
     *
     * @return array
     */
    public static function removeInternalFrames($backtrace)
    {
        $index = static::getFirstIndex($backtrace, 0);
        return \array_slice($backtrace, $index);
    }

    /**
     * Build the quick class check reges
     *
     * @return string
     */
    private static function buildSkipRegex()
    {
        $levelMax = self::$internalClasses['levelCurrent'];
        $classes = array();
        foreach (self::$internalClasses['classes'] as $class => $level) {
            if ($level <= $levelMax) {
                $classes[] = $class;
            }
        }
        self::$internalClasses['regex'] = '/^('
            . \implode('|', \array_map(static function ($class) {
                return \preg_quote($class, '/');
            }, $classes))
            . ')\b/';
        return self::$internalClasses['regex'];
    }

    /**
     * Determine level max and set regex
     *
     * @param int|null $levelMax maximum level
     *
     * @return int levelMax
     */
    private static function initSkippableTests($levelMax)
    {
        if ($levelMax === null) {
            static::$internalClasses['levelCurrent'] = null;
            $levelMax = \end(static::$internalClasses['levels']);
        }
        foreach (static::$internalClasses['levels'] as $i => $level) {
            if ($level > $levelMax) {
                $levelMax = static::$internalClasses['levels'][$i - 1];
                break;
            }
        }
        if ($levelMax !== static::$internalClasses['levelCurrent']) {
            static::$internalClasses['levelCurrent'] = $levelMax;
            static::buildSkipRegex();
        }
        return $levelMax;
    }

    /**
     * Test if frame is skippable
     *
     * @param array $frame    backtrace frame
     * @param int   $levelMax when classes to consider internal
     *
     * @return bool
     */
    private static function isSkippable($frame, $levelMax)
    {
        $frame = \array_merge(array(
            'class' => null,
            'function' => null,
        ), $frame);
        $class = null;
        if ($frame['class']) {
            $class = $frame['class'];
        } elseif (\preg_match('/^(.+)(::|->)/', (string) $frame['function'], $matches)) {
            $class = $matches[1];
        }
        if (!$class) {
            return Normalizer::isInternal($frame);
        }
        if (\preg_match(static::$internalClasses['regex'], $class)) {
            return true;
        }
        if (\in_array($frame['function'], array('__call', '__callStatic'), true)) {
            return true;
        }
        if ($class === 'ReflectionMethod' && \in_array($frame['function'], array('invoke','invokeArgs'), true)) {
            return true;
        }
        return static::isSubclassOfInternal($frame, $levelMax);
    }

    /**
     * Test frame against internal classes
     *
     * @param array $frame    backtrace frame
     * @param int   $levelMax when classes to consider internal
     *
     * @return bool
     */
    private static function isSubclassOfInternal(array $frame, $levelMax)
    {
        if (!isset($frame['object'])) {
            return false;
        }
        foreach (static::$internalClasses['classes'] as $className => $level) {
            if ($level <= $levelMax && \is_subclass_of($frame['object'], $className)) {
                return true;
            }
        }
        return false;
    }
}

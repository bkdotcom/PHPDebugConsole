<?php

/**
 * @package   Backtrace
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2020-2024 Brad Kent
 * @version   v2.2
 * @link      http://www.github.com/bkdotcom/Backtrace
 */

namespace bdk\Backtrace;

use InvalidArgumentException;

/**
 * Utility for Skipping over frames "internal" to debugger or framework
 *
 * backtrace:
 *    index 0 is current position
 *    function/class are what's getting called
 *    file/line are calling _from_
 */
class SkipInternal
{
    /** @var array<string,mixed> */
    private static $internalClasses = array(
        // classes/namespaces
        // the lower the number, the more we'll enforce skipping
        //   if all frames are skipped, we will try lower number
        'classes' => array(
            'ReflectionMethod' => 0,
            __NAMESPACE__ => 0,
        ),
        'levelCurrent' => null,
        'levels' => array(0),
        'regex' => null,
    );

    /** @var non-empty-string */
    private static $classMethodRegex = '/^(?<class>\S+)(?<type>::|->)(?<method>\S+)$/';

    /**
     * Add a new namespace or classname to be used to determine when to
     * stop iterating over the backtrace when determining calling info
     *
     * @param array|string $classes classname(s)
     * @param int          $level   "priority"
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public static function addInternalClass($classes, $level = 0)
    {
        if (\is_int($level) === false) {
            throw new InvalidArgumentException(\sprintf(
                'level must be an integer. %s provided.',
                \gettype($level)
            ));
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
     * @param int|null $level     {internal}
     *
     * @return int
     */
    public static function getFirstIndex(array $backtrace, $offset = 0, $level = null)
    {
        $level = self::initSkippableTests($level);
        $count = \count($backtrace);
        for ($i = 0; $i < $count; $i++) {
            if (static::isSkippable($backtrace[$i], $level) === false) {
                break;
            }
        }
        $i = self::getFirstIndexRewind($backtrace, $i);
        if ($i === $count) {
            // every frame was skipped
            return $level > 0
                ? self::getFirstIndex($backtrace, $offset, $level - 1)
                : 0;
        }
        $i--;
        $i = \max($i, 0); // insure we're >= 0
        return isset($backtrace[$i + $offset])
            ? $i + $offset
            : $i;
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
        $index = static::getFirstIndex($backtrace);
        return \array_slice($backtrace, $index);
    }

    /**
     * Build the quick class check reges
     *
     * @return string
     */
    private static function buildSkipRegex()
    {
        $levelCurrent = self::$internalClasses['levelCurrent'];
        $classes = array();
        foreach (self::$internalClasses['classes'] as $class => $level) {
            if ($level <= $levelCurrent) {
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
     * getFirstIndex may have skipped over (non object)function calls
     * back it up
     *
     * @param array $backtrace Backtrace
     * @param int   $index     Index after skipping frames
     *
     * @return int [description]
     */
    private static function getFirstIndexRewind(array $backtrace, $index)
    {
        $count = \count($backtrace);
        if ($index && $index === $count && self::getClass($backtrace[$index - 1]) === null) {
            // every frame was skipped and first frame is include, or similar
            return $index;
        }
        for ($i = $index - 1; $i > 0; $i--) {
            $class = self::getClass($backtrace[$i]);
            if (\in_array($class, array(null, 'ReflectionMethod'), true) === false) {
                // class method (but not ReflectionMethod)
                break;
            }
            $index = $i;
        }
        return $index;
    }

    /**
     * Determine level max and set regex
     *
     * @param int|null $levelMax Maximum level
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
     * "Skippable" if
     *    * not a class method
     *    * class belongs to internalClasses
     *
     * @param array $frame backtrace frame
     * @param int   $level when classes to consider internal
     *
     * @return bool
     */
    private static function isSkippable($frame, $level)
    {
        $class = self::getClass($frame);
        if (!$class) {
            return true;
        }
        if (\preg_match(static::$internalClasses['regex'], $class)) {
            return true;
        }
        return static::isSubclassOfInternal($class, $level);
    }

    /**
     * Test frame against internal classes
     *
     * @param class-string $class    class name
     * @param int          $levelMax MAximum level
     *
     * @return bool
     */
    private static function isSubclassOfInternal($class, $levelMax)
    {
        foreach (static::$internalClasses['classes'] as $classNameInternal => $level) {
            if ($level <= $levelMax && \class_exists($classNameInternal, false) && \is_subclass_of($class, $classNameInternal)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get classname of called class/method
     *
     * @param array $frame Normalized backtrace frame
     *
     * @return string|null
     */
    private static function getClass(array $frame)
    {
        return \preg_match(self::$classMethodRegex, (string) $frame['function'], $matches)
            ? $matches['class']
            : null;
    }
}

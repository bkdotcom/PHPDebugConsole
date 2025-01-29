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

use InvalidArgumentException;
use ReflectionFunction;

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
        'levels' => [0],
        'regex' => null,
    );

    /** @var non-empty-string */
    private static $classMethodRegex = '/^(?<class>\S+)(?<type>::|->)(?<method>\S+)$/';

    private static $viaRemoveInternalFrames = false;

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

        $i = self::getFirstIndexRewind($backtrace, $i, $level);
        if ($i === $count && $level > 0) {
            // every frame was skipped
            return self::getFirstIndex($backtrace, $offset, $level - 1);
        }
        $i = \max($i, 0); // ensure we're >= 0
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
        self::$viaRemoveInternalFrames = true;
        $index = static::getFirstIndex($backtrace);
        self::$viaRemoveInternalFrames = false;
        return $index === \count($backtrace) - 1
            ? $backtrace
            : \array_slice($backtrace, $index);
    }

    /**
     * Build the quick class check reges
     *
     * @return string
     */
    private static function buildSkipRegex()
    {
        $levelCurrent = self::$internalClasses['levelCurrent'];
        $classes = [];
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
     * getFirstIndex may have skipped over (non object) function calls
     *
     * back it up
     *
     * @param array $backtrace Backtrace
     * @param int   $index     Index after skipping frames
     * @param int   $level     current level
     *
     * @return int
     */
    private static function getFirstIndexRewind(array $backtrace, $index, $level)
    {
        $count = \count($backtrace);
        $index = \min($index, $count - 1);
        if ($index === $count - 1 && self::$viaRemoveInternalFrames) {
            return $index;
        }
        for ($i = $index; $i > 0; $i--) {
            $frame = $backtrace[$i];
            $isPhpFuncOrClosure = self::isPhpDefinedFunction($frame['function']) || $frame['function'] === '{closure}';
            if (self::isSkippable($frame, $level) && $isPhpFuncOrClosure === false) {
                break;
            }
        }
        return $i;
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
     * Checks whether the function is internal, as opposed to user-defined.
     *
     * @param string $function Function name
     *
     * @return bool
     */
    private static function isPhpDefinedFunction($function)
    {
        if (\in_array($function, ['include', 'include_once', 'include or require', 'require', 'require_once'], true)) {
            return true;
        }
        if (\function_exists((string) $function) === false) {
            return false;
        }
        $refFunction = new ReflectionFunction($function);
        return $refFunction->isInternal();
    }

    /**
     * Test if frame is skippable
     *
     * "Skippable" if
     *    * not a class method
     *    * class belongs to internalClasses
     *
     * @param array $frame backtrace frame
     * @param int   $level which classes to consider internal
     *
     * @return bool
     */
    private static function isSkippable($frame, $level)
    {
        $class = self::getClass($frame);
        if (!$class) {
            return self::isPhpDefinedFunction($frame['function']) || $frame['function'] === '{closure}';
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
     * @param int          $levelMax Maximum level
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
        return \preg_match(\bdk\Backtrace::REGEX_FUNCTION, (string) $frame['function'], $matches)
            ? $matches['classname']
            : null;
    }
}

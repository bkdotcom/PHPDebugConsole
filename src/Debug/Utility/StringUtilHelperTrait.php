<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\ArrayUtil;
use bdk\Debug\Utility\Php;
use InvalidArgumentException;

/**
 * String utility helper methods
 */
trait StringUtilHelperTrait
{
    /**
     * Get the strings to process for self::commonPrefix()
     *
     * @param string[] $values List of strings
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private static function assertStrings($values)
    {
        foreach ($values as $i => $value) {
            if (\is_string($value)) {
                continue;
            }
            throw new InvalidArgumentException(\sprintf(
                'commonPrefix() - Expects a list of strings.  Found %s at index %s',
                Php::getDebugType($value),
                $i
            ));
        }
    }

    /**
     * Typecast values for comparison like Php 8 does it
     *
     * @param mixed $valA Value a
     * @param mixed $valB Value b
     *
     * @return array $valA & $valB
     *
     * @link https://www.php.net/releases/8.0/en.php#saner-string-to-number-comparisons
     */
    private static function compareTypeJuggle($valA, $valB)
    {
        $isNumericA = \is_numeric($valA);
        $isNumericB = \is_numeric($valB);
        if ($isNumericA && $isNumericB) {
            $valA = $valA * 1;
            $valB = $valB * 1;
        } elseif ($isNumericA && \is_string($valB)) {
            $valA = (string) $valA;
        } elseif ($isNumericB && \is_string($valA)) {
            $valB = (string) $valB;
        }
        return [$valA, $valB];
    }

    /**
     * Compare two values specifying operator
     *
     * @param mixed  $valA     Value A
     * @param mixed  $valB     Value B
     * @param string $operator (strcmp) Comparison operator
     *
     * @return bool|int
     */
    private static function doCompare($valA, $valB, $operator)
    {
        switch ($operator) {
            case '==':
                return $valA == $valB;
            case '===':
                return $valA === $valB;
            case '!=':
                return $valA != $valB;
            case '!==':
                return $valA !== $valB;
            case '>=':
                return $valA >= $valB;
            case '<=':
                return $valA <= $valB;
            case '>':
                return $valA >  $valB;
            case '<':
                return $valA <  $valB;
        }
        $ret = \call_user_func($operator, $valA, $valB);
        $ret = \min(\max($ret, -1), 1);
        return $ret;
    }

    /**
     * Test if character distribution is what we would expect for a base 64 string
     * This is quite unreliable as encoding isn't random
     *
     * @param string $val string already stripped of whitespace
     *
     * @return bool
     */
    private static function isBase64EncodedTestStats($val)
    {
        $valNoPadding = \rtrim($val, '=');
        $strlen = \strlen($valNoPadding);
        if ($strlen < \strlen($val)) {
            // if val ends with "=" it's pretty safe to assume base64
            return true;
        }
        if ($strlen === 0) {
            return false;
        }
        $stats = array(
            // how many chars found, percent expected for random binary, allowed deviation
            'lower' => [\preg_match_all('/[a-z]/', $val), 40.626, 10],
            'num' => [\preg_match_all('/[0-9]/', $val), 15.625, 8],
            'other' => [\preg_match_all('/[+\/]/', $val), 3.125, 5],
            'upper' => [\preg_match_all('/[A-Z]/', $val), 40.625, 10],
        );
        foreach ($stats as $stat) {
            $per = $stat[0] * 100 / $strlen;
            $diff = \abs($per - $stat[1]);
            if ($diff > $stat[2]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Test if value matches basic base64 regex
     *
     * @param string $val string to test
     *
     * @return bool
     */
    private static function isBase64RegexTest($val)
    {
        if (\is_string($val) === false) {
            return false;
        }
        $val = \trim($val);
        $isHex = \preg_match('/^[0-9A-F]+$/i', $val) === 1;
        if ($isHex) {
            return false;
        }
        // only allow whitespace at beginning and end of lines
        $regex = '#^'
            . '([ \t]*[a-zA-Z0-9+/]*[ \t]*[\r\n]+)*'
            . '([ \t]*[a-zA-Z0-9+/]*={0,2})' // last line may have "=" padding at the end"
            . '$#';
        return \preg_match($regex, $val) === 1;
    }

    /**
     * Test self::interpolate's $message and $context values
     *
     * @param string|Stringable $message message value to test
     * @param array|object      $context context value to test
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private static function interpolateAssertArgs($message, $context)
    {
        if (
            \count(\array_filter([
                \is_string($message),
                \is_object($message) && \method_exists($message, '__toString'),
            ])) === 0
        ) {
            throw new \InvalidArgumentException(\sprintf(
                __NAMESPACE__ . '::interpolate()\'s $message expects string or Stringable object. %s provided.',
                Php::getDebugType($message)
            ));
        }
        if (
            \count(\array_filter([
                \is_array($context),
                \is_object($context),
            ])) === 0
        ) {
            throw new \InvalidArgumentException(\sprintf(
                __NAMESPACE__ . '::interpolate()\'s $context expects array or object. %s provided.',
                Php::getDebugType($context)
            ));
        }
    }

    /**
     * Get substitution values for `interpolate()`
     *
     * @param array $placeholders keys
     *
     * @return string[] key->value array
     */
    private static function interpolateValues($placeholders)
    {
        $replace = array();
        foreach ($placeholders as $placeholder) {
            $val = self::interpolateValue($placeholder);
            if (
                \array_filter([
                    $val === null,
                    \is_array($val),
                    \is_object($val) && \method_exists($val, '__toString') === false,
                ])
            ) {
                continue;
            }
            $replace['{' . $placeholder . '}'] = (string) $val;
        }
        return $replace;
    }

    /**
     * Pull placeholder value from context
     *
     * @param string $placeholder Placeholder from message
     *
     * @return mixed
     */
    private static function interpolateValue($placeholder)
    {
        $path = \array_filter(\preg_split('#[\./]#', $placeholder), 'strlen');
        $key0 = $path[0];
        $noValue = "\x00noValue\x00";
        $val = self::$interpIsArrayAccess
            ? (\array_key_exists($key0, self::$interpContext) ? self::$interpContext[$key0] : $noValue)
            : (isset(self::$interpContext->{$key0}) ? self::$interpContext->{$key0} : $noValue);
        if (\count($path) > 1) {
            $val = ArrayUtil::pathGet($val, \array_slice($path, 1), $noValue);
        }
        if ($val === $noValue) {
            return null; // will not replace token
        }
        if ($val === null) {
            return '';
        }
        return $val;
    }
}

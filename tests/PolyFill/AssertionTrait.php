<?php

namespace bdk\DebugTests\PolyFill;

use ArrayAccess;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

trait AssertionTrait
{

    public static function assertArraySubset($expected, $actual, $strict = false, $message = ''): void
    {
        if (false && \method_exists('\\PHPUnit\\Framework\\TestCase', __FUNCTION__)) {
            TestCase::assertArraySubset($expected, $actual, $strict, $message);
            return;
        }
        if (!(\is_array($expected) || $expected instanceof ArrayAccess)) {
            throw InvalidArgumentException::create(
                1,
                'array or ArrayAccess'
            );
        }
        if (!(\is_array($actual) || $actual instanceof ArrayAccess)) {
            throw InvalidArgumentException::create(
                2,
                'array or ArrayAccess'
            );
        }
        // $patched = \array_replace_recursive($other, $expected);
        $patched = \array_intersect_key($actual, $expected);
        $isMatch = $strict
            ? $patched === $expected
            : $patched == $expected;
        if (!$isMatch) {
            throw new AssertionFailedError('an array has the subset ' . \print_r($expected, true));
        }
    }

    public static function assertIsArray($actual, $message = ''): void
    {
        if (!\is_array($actual)) {
            throw new AssertionFailedError($message ?: 'Not an array');
        }
    }

    public static function assertIsBool($actual, $message = ''): void
    {
        if (!\is_bool($actual)) {
            throw new AssertionFailedError($message ?: 'Not boolean');
        }
    }

    public static function assertIsCallable($actual, $message = ''): void
    {
        if (!\is_callable($actual)) {
            throw new AssertionFailedError($message ?: 'Not callable');
        }
    }

    public static function assertIsFloat($actual, $message = ''): void
    {
        if (!\is_float($actual)) {
            throw new AssertionFailedError($message ?: 'Not float');
        }
    }

    public static function assertIsInt($actual, $message = ''): void
    {
        if (!\is_integer($actual)) {
            throw new AssertionFailedError($message ?: 'Not int');
        }
    }

    public static function assertIsIterable($actual, $message = ''): void
    {
        if (!\is_array($actual) && !($actual instanceof \Traversable)) {
            throw new AssertionFailedError($message ?: 'Not iterable');
        }
    }

    public static function assertIsNumeric($actual, $message = ''): void
    {
        if (!\is_numeric($actual)) {
            throw new AssertionFailedError($message ?: 'Not numeric');
        }
    }

    public static function assertIsObject($actual, $message = ''): void
    {
        if (!\is_object($actual)) {
            throw new AssertionFailedError($message ?: 'Not object');
        }
    }

    public static function assertIsResource($actual, $message = ''): void
    {
        if (!\is_resource($actual)) {
            throw new AssertionFailedError($message ?: 'Not resource');
        }
    }

    public static function assertIsScalar($actual, $message = ''): void
    {
        if (!\is_scalar($ExpectationFailedException)) {
            throw new AssertionFailedError($message ?: 'Not scalar');
        }
    }

    public static function assertIsString($actual, $message = ''): void
    {
        if (!\is_string($actual)) {
            throw new AssertionFailedError($message ?: 'Not string');
        }
    }

    public static function assertStringContainsString($needle, $haystack, $message = ''): void
    {
        if (\strpos($haystack, $needle) === false) {
            throw new AssertionFailedError($message ?: 'Does not contain string');
        }
    }

    public static function assertStringNotContainsString($needle, $haystack, $message = ''): void
    {
        if (\strpos($haystack, $needle) !== false) {
            throw new AssertionFailedError($message ?: 'String contains string');
        }
    }

    public static function assertMatchesRegularExpression($pattern, $string, $message = ''): void
    {
        throw new AssertionFailedError('assertMatchesRegularExpression not yet implemented');
    }
}

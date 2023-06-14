<?php

namespace bdk\Test\PolyFill;

use ArrayAccess;
use BadMethodCallException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit v10 declares methods as final, so we implement these polyFills via __call & __callStatic
 */
trait AssertionTrait
{
    public function __call($method, $args)
    {
        $this->__callStatic($method, $args);
    }

    public static function __callStatic($method, $args)
    {
        $methodTry = 'poly' . \ucfirst($method);
        if (\method_exists(__CLASS__, $methodTry)) {
            \call_user_func_array(array(__CLASS__, $methodTry), $args);
            return;
        }
        throw new BadMethodCallException('Call to undefined method ' . __CLASS__ . '::' . $method);
    }

    private static function polyAssertArraySubset($expected, $actual, $strict = false, $message = ''): void
    {
        /*
        if (\method_exists('PHPUnit\\Framework\\TestCase', __FUNCTION__)) {
            TestCase::assertArraySubset($expected, $actual, $strict, $message);
            return;
        }
        */
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
        $patched = \array_intersect_key($actual, $expected);
        $isMatch = $strict
            ? $patched === $expected
            : $patched == $expected;
        if (!$isMatch) {
            throw new AssertionFailedError('an array has the subset ' . \print_r($expected, true));
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsArray($actual, $message = ''): void
    {
        if (\is_array($actual) === false) {
            throw new AssertionFailedError($message ?: 'Not an array');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsBool($actual, $message = ''): void
    {
        if (\is_bool($actual) === false) {
            throw new AssertionFailedError($message ?: 'Not boolean');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsCallable($actual, $message = ''): void
    {
        if (\is_callable($actual) === false) {
            throw new AssertionFailedError($message ?: 'Not callable');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsFloat($actual, $message = ''): void
    {
        if (\is_float($actual) === false) {
            throw new AssertionFailedError($message ?: 'Not float');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsInt($actual, $message = ''): void
    {
        if (\is_integer($actual) === false) {
            throw new AssertionFailedError($message ?: 'Not int');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsIterable($actual, $message = ''): void
    {
        if (\is_array($actual) === false && ($actual instanceof \Traversable) === false) {
            throw new AssertionFailedError($message ?: 'Not iterable');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsNumeric($actual, $message = ''): void
    {
        if (\is_numeric($actual) === false) {
            throw new AssertionFailedError($message ?: 'Not numeric');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsObject($actual, $message = ''): void
    {
        if (\is_object($actual) === false) {
            throw new AssertionFailedError($message ?: 'Not object');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsResource($actual, $message = ''): void
    {
        if (\is_resource($actual) === false) {
            throw new AssertionFailedError($message ?: 'Not resource');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsScalar($actual, $message = ''): void
    {
        if (\is_scalar($ExpectationFailedException) === false) {
            throw new AssertionFailedError($message ?: 'Not scalar');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertIsString($actual, $message = ''): void
    {
        if (\is_string($actual) === false) {
            throw new AssertionFailedError($message ?: 'Not string');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertStringContainsString($needle, $haystack, $message = ''): void
    {
        if (\strpos($haystack, $needle) === false) {
            throw new AssertionFailedError($message ?: 'Does not contain string');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertStringNotContainsString($needle, $haystack, $message = ''): void
    {
        if (\strpos($haystack, $needle) !== false) {
            throw new AssertionFailedError($message ?: 'String contains string');
        }
        TestCase::assertTrue(true);
    }

    private static function polyAssertMatchesRegularExpression($pattern, $string, $message = ''): void
    {
        if (\preg_match($pattern, $string) !== 1) {
            throw new AssertionFailedError($message ?: 'String does not match pattern');
        }
        TestCase::assertTrue(true);
    }
}

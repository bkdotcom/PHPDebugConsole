<?php

namespace bdk\Teams;

use bdk\HttpMessage\Utility\Uri as UriUtility;
use bdk\Teams\ItemInterface;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use ReflectionClass;

/**
 * Helper methods
 */
trait CardUtilityTrait
{
    private static $constants;

    /**
     * Itterate over supplied tests.
     *
     * @param mixed      $val     Value to test
     * @param callable[] $tests   tests.  Each test may throw exception or return false to indicate failure
     * @param string     $message InvalidArgumentException message to throw if all tests fail
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected static function assertAnyOf($val, $tests, $message)
    {
        foreach ($tests as $callable) {
            try {
                $ret = $callable($val);
                if ($ret === false) {
                    // test failed
                    continue;
                }
                return;
            } catch (InvalidArgumentException $e) {
                // test failed
            }
        }
        throw new InvalidArgumentException($message);
    }

    /**
     * Assert given value is bool
     *
     * @param mixed  $val  Value to test
     * @param string $name Name of value
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected static function assertBool($val, $name)
    {
        if ($val === null) {
            // we'll allow null
            return;
        }
        if (\is_bool($val) === true) {
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            '%s must be bool. %s provided',
            $name,
            self::getDebugType($val)
        ));
    }

    /**
     * Assert that value is one of the constants beginning with prefix
     *
     * @param string $value     Value to check
     * @param string $prefix    Prefix (such as "HEIGHT_")
     * @param string $paramName Optional paramNAme to use in exception message
     * @param bool   $allowNull Is `null` and allowed value?
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected static function assertEnumValue($value, $prefix, $paramName = null, $allowNull = true)
    {
        $allowedValues = self::getConstantsWithPrefix($prefix);
        $message = '%s should must be one of the %s::%s_x constants';
        if ($allowNull) {
            $allowedValues[] = null;
            $message .= ' (or null)';
        }
        if (\in_array($value, $allowedValues, true)) {
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            $message,
            $paramName ?: 'value',
            'bdk\\Teams\\Enums',
            $prefix
        ));
    }

    /**
     * Assert valid fallback
     *
     * @param objec|Enums::FALLBACK_x $fallback   value to test
     * @param classname               $instanceOf check val is instance of this class
     * @param string                  $message    exception message
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertFallback($fallback, $instanceOf, $message)
    {
        $tests = array(
            static function ($val) {
                return $val === null;
            },
            static function ($val) use ($instanceOf) {
                return \is_a($val, $instanceOf);
            },
            static function ($val) {
                self::assertEnumValue($val, 'FALLBACK_', 'width');
            },
        );
        self::assertAnyOf($fallback, $tests, $message);
    }

    /**
     * Assert value is of the form "123px"
     *
     * @param mixed  $val       value to test
     * @param string $method    method performing the assertion
     * @param string $paramName name of the parameter we're testing
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected static function assertPx($val, $method = null, $paramName = null)
    {
        if ($val === null) {
            // we'll allow null
            return;
        }
        if (\is_string($val) && \preg_match('/^\d+\.?\d*px$/', $val) === 1) {
            return;
        }
        $message = 'Invalid pixel value (ie "42px")';
        if ($method) {
            $message = $method . ' - ' . $message;
        }
        $message = $paramName
            ? $message . ' supplied for ' . $paramName . '.'
            : $message . '.';
        $message .= ' ' . self::getDebugType($val) . ' provided.';
        throw new InvalidArgumentException($message);
    }

    /**
     * Returns value as string
     *
     * @param mixed  $val       value to test
     * @param bool   $allowNull Allow null value?
     * @param string $method    method performing the assertion
     * @param string $paramName name of the parameter we're testing
     *
     * @return string|null
     *
     * @throws InvalidArgumentException
     */
    protected static function asString($val, $allowNull, $method, $paramName = null)
    {
        if (self::isStringable($val)) {
            return (string) $val;
        }
        if ($val === null && $allowNull) {
            return $val;
        }
        $allowedTypes = $allowNull
            ? 'string, numeric, stringable obj, or null'
            : 'string, numeric, or stringable obj';
        $message = $paramName
            ? '%s - ' . $paramName . ' should be a ' . $allowedTypes . '. %s provided'
            : '%s expects a ' . $allowedTypes . '. %s provided.';
        throw new InvalidArgumentException(\sprintf(
            $message,
            $method,
            self::getDebugType($val)
        ));
    }

    /**
     * Can value be coerced to string?
     * (string, numeric, or Stringable)
     *
     * @param mixed $val Value to test
     *
     * @return bool
     */
    private static function isStringable($val)
    {
        if (\is_string($val) || \is_numeric($val)) {
            return true;
        }
        return \is_object($val) && \method_exists($val, '__toString');
    }

    /**
     * Assert that value is a URL
     *
     * @param mixed $val          Value to test
     * @param bool  $allowDataUrl (false) allow data uri
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected static function assertUrl($val, $allowDataUrl = false)
    {
        if (\is_string($val) === false && !($val instanceof UriInterface)) {
            throw new InvalidArgumentException(\sprintf(
                'Url should be a string or UriInterface. %s provided.',
                self::getDebugType($val)
            ));
        }
        if (
            $allowDataUrl
            && \preg_match('#^data:\w+/\w+;base64,(.*)$#', (string) $val, $matches)
            && self::isBase64RegexTest($matches[1])
        ) {
            return;
        }
        $message = $allowDataUrl
            ? 'Invalid url (or data url)'
            : 'Invalid url';
        $urlParts = UriUtility::parseUrl($val);
        if ($urlParts === false || isset($urlParts['scheme']) === false) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Gets the type name of a variable in a way that is suitable for debugging
     *
     * @param mixed $value Value to inspect
     *
     * @return string
     */
    protected static function getDebugType($value)
    {
        return \is_object($value)
            ? \get_class($value)
            : \gettype($value);
    }

    /**
     * Remove null and empty array values from array
     *
     * @param array $values  Input array
     * @param array $version Card version
     *
     * @return array
     */
    protected static function normalizeContent(array $values, $version = null)
    {
        // remove empty
        $values = \array_filter($values, static function ($value) {
            $empty = $value === null || $value === array();
            return $empty === false;
        });
        $values = \array_map(static function ($value) use ($version) {
            if (\is_array($value)) {
                return self::normalizeContent($value, $version);
            }
            if ($value instanceof ItemInterface) {
                $value = $value->getContent($version);
            }
            return $value;
        }, $values);
        \ksort($values);
        if (isset($values['type'])) {
            $values = array('type' => $values['type']) + $values;
        }
        return $values;
    }

    /**
     * Return all constant values where constant name begins with prefix
     *
     * @param string $prefix Constant name prefix
     *
     * @return array
     */
    private static function getConstantsWithPrefix($prefix)
    {
        if (self::$constants === null) {
            $refClass = new ReflectionClass('bdk\\Teams\\Enums');
            self::$constants = $refClass->getConstants();
        }
        // array_filter / ARRAY_FILTER_USE_KEY is php 5.6
        $filteredByKey = array();
        foreach (self::$constants as $key => $value) {
            if (\strpos($key, $prefix) === 0) {
                $filteredByKey[$key] = $value;
            }
        }
        return $filteredByKey;
    }

    /**
     * Basic test to check if base64 encoded data
     *
     * @param string $val string to test
     *
     * @return bool
     */
    private static function isBase64RegexTest($val)
    {
        $val = \trim($val);
        // only allow whitspace at beginning and end of lines
        $regex = '#^'
            . '([ \t]*[a-zA-Z0-9+/]*[ \t]*[\r\n]+)*'
            . '([ \t]*[a-zA-Z0-9+/]*={0,2})' // last line may have "=" padding at the end"
            . '$#';
        return \preg_match($regex, $val) === 1;
    }
}

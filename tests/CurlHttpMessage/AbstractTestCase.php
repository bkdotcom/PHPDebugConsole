<?php

namespace bdk\Test\CurlHttpMessage;

use bdk\CurlHttpMessage\Factory;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase as TestCaseBase;
use ReflectionClass;
use ReflectionProperty;

/**
 *
 */
abstract class AbstractTestCase extends TestCaseBase
{
    use ExpectExceptionTrait;

    protected $baseUrl = 'http://127.0.0.1:8080';

    // ::class introduced in PHP 5.5...  we support 5.4
    protected $classes = array(
        // 'BadMethodCallException' => 'BadMethodCallException',
        'BadResponseException' => 'bdk\\CurlHttpMessage\\Exception\\BadResponseException',
        'Client' => 'bdk\\CurlHttpMessage\\Client',
        'Closure' => 'Closure',
        'HandlerStack' => 'bdk\\CurlHttpMessage\\HandlerStack',
        'InvalidArgumentException' => 'InvalidArgumentException',
        'NetworkException' => 'bdk\\CurlHttpMessage\\Exception\\NetworkException',
        'OutOfBoundsException' => 'OutOfBoundsException',
        // 'OverflowException' => 'OverflowException',
        'PromiseInterface' => 'bdk\\Promise\\PromiseInterface',
        'RequestException' => 'bdk\\CurlHttpMessage\\Exception\\RequestException',
        'Response' => 'bdk\\HttpMessage\\Response',
        'ResponseInterface' => 'Psr\\Http\\Message\\ResponseInterface',
        'RuntimeException' => 'RuntimeException',
        'UnderflowException' => 'UnderflowException',
    );

    protected static $factory;

    /**
     * {@inheritDoc}
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        self::$factory = new Factory();
        parent::__construct($name, $data, $dataName);
    }

    public function __get($name)
    {
        if ($name === 'factory') {
            return self::$factory;
        }
        throw new RuntimeException('Access to unavailable property ' . __CLASS__ . '::' . $name);
    }

    /**
     * Get inaccessible property value via reflection
     *
     * @param object|classname $obj  object instance
     * @param string           $prop property name
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public static function propGet($obj, $prop)
    {
        $refProp = static::getReflectionProperty($obj, $prop);
        if ($refProp->isStatic()) {
            return $refProp->getValue();
        }
        if (\is_object($obj) === false) {
            throw new InvalidArgumentException(\sprintf(
                'propGet: object must be provided to retrieve instance value %s',
                $prop
            ));
        }
        return $refProp->getValue($obj);
    }

        /**
     * Get ReflectionProperty
     *
     * @param object|classname $obj  object or classname
     * @param string           $prop property name
     *
     * @return ReflectionProperty
     * @throws OutOfBoundsException
     */
    private static function getReflectionProperty($obj, $prop)
    {
        $refProp = null;
        $ref = new ReflectionClass($obj);
        do {
            if ($ref->hasProperty($prop)) {
                $refProp = $ref->getProperty($prop);
                break;
            }
            $ref = $ref->getParentClass();
        } while ($ref);
        if ($refProp === null) {
            throw new OutOfBoundsException(\sprintf(
                'Property %s::$%s does not exist',
                \is_string($obj)
                    ? $obj
                    : \get_class($obj),
                $prop
            ));
        }
        if (PHP_VERSION_ID < 80100) {
            $refProp->setAccessible(true);
        }
        return $refProp;
    }
}

<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\Reflection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use Reflector;

function testFunc()
{
}

/**
 * PHPUnit tests for Reflection utility class
 *
 * @covers \bdk\Debug\Utility\Reflection
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class ReflectionTest extends TestCase
{
    /**
     * @dataProvider providerGetParentReflector
     */
    public function testGetParentReflector(Reflector $what, callable $callable)
    {
        $parentReflector = Reflection::getParentReflector($what);
        $callable($parentReflector);
    }

    /**
     * @dataProvider providerTests
     */
    public function testReflector($what, $returnSelf, $expect)
    {
        $reflector = Reflection::getReflector($what, $returnSelf);

        if ($expect['instanceOf'] === false) {
            self::assertFalse($reflector);
            return;
        }

        self::assertInstanceOf($expect['instanceOf'], $reflector);
        self::assertSame($expect['classname'], Reflection::classname($reflector));
        self::assertSame($expect['hash'], Reflection::hash($reflector));
    }

    public function providerGetParentReflector()
    {
        $testObj = new \bdk\Test\Debug\Fixture\TestObj();
        $declaringClassName = 'bdk\\Test\\Debug\\Fixture\\TestBase';

        $implements = new \bdk\Test\Debug\Fixture\Utility\PhpDocImplements();
        $interfaceClassName = 'bdk\\Test\\Debug\\Fixture\\SomeInterface';

        return array(
            'noParent' => array(
                new ReflectionClass('bdk\\Test\\Debug\\Fixture\\TestBase'),
                static function ($parent) {
                    self::assertFalse($parent);
                },
            ),

            'reflectionClass' => array(
                new ReflectionClass($testObj),
                static function (ReflectionClass $parent) use ($declaringClassName) {
                    self::assertSame($declaringClassName, $parent->getName());
                },
            ),
            'reflectionObject' => array(
                new ReflectionObject($testObj),
                static function (ReflectionClass $parent) use ($declaringClassName) {
                    self::assertSame($declaringClassName, $parent->getName());
                },
            ),
            'reflectionConstant' => array(
                new ReflectionClassConstant($testObj, 'MY_CONSTANT'),
                static function (ReflectionClassConstant $parent) use ($declaringClassName) {
                    self::assertSame('MY_CONSTANT', $parent->getName());
                    self::assertSame($declaringClassName, $parent->getDeclaringClass()->getName());
                },
            ),
            'reflectionMethod' => array(
                new ReflectionMethod($testObj, '__construct'),
                static function (ReflectionMethod $parent) use ($declaringClassName) {
                    self::assertSame('__construct', $parent->getName());
                    self::assertSame($declaringClassName, $parent->getDeclaringClass()->getName());
                },
            ),
            'reflectionProperty' => array(
                new ReflectionProperty($testObj, 'propPublic'),
                static function (ReflectionProperty $parent) use ($declaringClassName) {
                    self::assertSame('propPublic', $parent->getName());
                    self::assertSame($declaringClassName, $parent->getDeclaringClass()->getName());
                },
            ),

            // implements

            'reflectionImplementsClass' => array(
                new ReflectionClass($implements),
                static function (ReflectionClass $parent) use ($interfaceClassName) {
                    self::assertSame($interfaceClassName, $parent->getName());
                },
            ),
            'reflectionImplementsObject' => array(
                new ReflectionObject($implements),
                static function (ReflectionClass $parent) use ($interfaceClassName) {
                    self::assertSame($interfaceClassName, $parent->getName());
                },
            ),
            'reflectionImplementsConstant' => array(
                new ReflectionClassConstant($implements, 'SOME_CONSTANT'),
                static function (ReflectionClassConstant $parent) use ($interfaceClassName) {
                    self::assertSame('SOME_CONSTANT', $parent->getName());
                    self::assertSame($interfaceClassName, $parent->getDeclaringClass()->getName());
                },
            ),
            'reflectionImplementsMethod' => array(
                new ReflectionMethod($implements, 'someMethod'),
                static function (ReflectionMethod $parent) use ($interfaceClassName) {
                    self::assertSame('someMethod', $parent->getName());
                    self::assertSame($interfaceClassName, $parent->getDeclaringClass()->getName());
                },
            ),
            'reflectionImplementsProperty' => array(
                new ReflectionProperty($implements, 'someProperty'),
                static function ($parent) {
                    self::assertFalse($parent);
                },
            ),
        );
    }

    public function providerTests()
    {
        $testObj = new \bdk\Test\Debug\Fixture\TestObj();
        $classname = 'bdk\\Test\\Debug\\Fixture\\TestObj';

        $tests = array(
            'int' => array(
                123,
                false,
                array(
                    'instanceOf' => false,
                ),
            ),

            'bogus string' => array(
                'bogus string',
                false,
                array(
                    'instanceOf' => false,
                ),
            ),

            'function' => array(
                'var_dump()',
                false,
                array(
                    'instanceOf' => 'ReflectionFunction',
                    'classname' => false,
                    'hash' => \md5('var_dump()'),
                ),
            ),
            'functionNamespace' => array(
                __NAMESPACE__ . '\\testFunc()',
                false,
                array(
                    'instanceOf' => 'ReflectionFunction',
                    'classname' => false,
                    'hash' => \md5(__NAMESPACE__ . '\\testFunc()'),
                ),
            ),
            'functionUndefined' => array(
                'no_such_function()',
                false,
                array(
                    'instanceOf' => false,
                ),
            ),

            'reflectorSelf' => array(
                new ReflectionClass($testObj),
                true,
                array(
                    'instanceOf' => 'ReflectionClass',
                    'classname' => $classname,
                    'hash' => \md5($classname),
                ),
            ),
            'reflectorNotSelf' => array(
                new ReflectionClass($testObj),
                false,
                array(
                    'instanceOf' => 'ReflectionClass',
                    'classname' => 'ReflectionClass',
                    'hash' => \md5('ReflectionClass'),
                ),
            ),

            'classString' => array(
                'bdk\\Test\\Debug\\Fixture\\TestObj',
                false,
                array(
                    'instanceOf' => 'ReflectionClass',
                    'classname' => $classname,
                    'hash' => \md5($classname),
                ),
            ),
            'classStringUndefined' => array(
                'bdk\\Test\\Debug\\Fixture\\NoSuchClass',
                false,
                array(
                    'instanceOf' => false,
                ),
            ),

            'propertyString' => array(
                'bdk\\Test\\Debug\\Fixture\\TestObj::$someArray',
                false,
                array(
                    'instanceOf' => 'ReflectionProperty',
                    'classname' => $classname,
                    'hash' => \md5($classname . '::$someArray'),
                ),
            ),
            'propertyStringUndefined' => array(
                'bdk\\Test\\Debug\\Fixture\\TestObj::$noSuchProperty',
                false,
                array(
                    'instanceOf' => false,
                ),
            ),

            'methodString' => array(
                'bdk\\Test\\Debug\\Fixture\\TestObj::methodPublic()',
                false,
                array(
                    'instanceOf' => 'ReflectionMethod',
                    'classname' => $classname,
                    'hash' => \md5($classname . '::methodPublic()'),
                ),
            ),
            'methodStringUndefined' => array(
                'bdk\\Test\\Debug\\Fixture\\TestObj::noSuchProperty()',
                false,
                array(
                    'instanceOf' => false,
                ),
            ),

            'object' => array(
                $testObj,
                false,
                array(
                    'instanceOf' => 'ReflectionObject',
                    'classname' => $classname,
                    'hash' => \md5($classname),
                ),
            ),
        );

        if (PHP_VERSION_ID < 70100) {
            return;
        }

        $tests['constantString'] = array(
            'bdk\\Test\\Debug\\Fixture\\TestObj::MY_CONSTANT',
            false,
            array(
                'instanceOf' => 'ReflectionClassConstant',
                'classname' => $classname,
                'hash' => \md5($classname . '::MY_CONSTANT'),
            ),
        );
        $tests['constantStringUndefined'] = array(
            'bdk\\Test\\Debug\\Fixture\\TestObj::NO_SUCH_CONSTANT',
            false,
            array(
                'instanceOf' => false,
            ),
        );

        if (PHP_VERSION_ID < 80100) {
            return;
        }

        $tests = \array_merge($tests, array(
            'enumAsString' => array(
                'bdk\\Test\\Debug\\Fixture\\Enum\\Meals',
                false,
                array(
                    'instanceOf' => 'ReflectionEnum',
                    'classname' => 'bdk\\Test\\Debug\\Fixture\\Enum\\Meals',
                    'hash' => \md5('bdk\\Test\\Debug\\Fixture\\Enum\\Meals'),
                ),
            ),
            'enumUnitCase' => array(
                \bdk\Test\Debug\Fixture\Enum\Meals::DINNER,
                false,
                array(
                    'instanceOf' => 'ReflectionEnumUnitCase',
                    'classname' => 'bdk\\Test\\Debug\\Fixture\\Enum\\Meals',
                    'hash' => \md5('bdk\\Test\\Debug\\Fixture\\Enum\\Meals::DINNER'),
                ),
            ),
            'enumUnitCaseAsString' => array(
                'bdk\\Test\\Debug\\Fixture\\Enum\\Meals::DINNER',
                false,
                array(
                    'instanceOf' => 'ReflectionEnumUnitCase',
                    'classname' => 'bdk\\Test\\Debug\\Fixture\\Enum\\Meals',
                    'hash' => \md5('bdk\\Test\\Debug\\Fixture\\Enum\\Meals::DINNER'),
                ),
            ),

            'enumBackedAsString' => array(
                'bdk\\Test\\Debug\\Fixture\\Enum\\MealsBacked',
                false,
                array(
                    'instanceOf' => 'ReflectionEnum',
                    'classname' => 'bdk\\Test\\Debug\\Fixture\\Enum\\MealsBacked',
                    'hash' => \md5('bdk\\Test\\Debug\\Fixture\\Enum\\MealsBacked'),
                ),
            ),
            'enumBackedCase' => array(
                \bdk\Test\Debug\Fixture\Enum\MealsBacked::DINNER,
                false,
                array(
                    'instanceOf' => 'ReflectionEnumBackedCase',
                    'classname' => 'bdk\\Test\\Debug\\Fixture\\Enum\\MealsBacked',
                    'hash' => \md5('bdk\\Test\\Debug\\Fixture\\Enum\\MealsBacked::DINNER'),
                ),
            ),
            'enumBackedCaseAsString' => array(
                'bdk\\Test\\Debug\\Fixture\\Enum\\MealsBacked::DINNER',
                false,
                array(
                    'instanceOf' => 'ReflectionEnumBackedCase',
                    'classname' => 'bdk\\Test\\Debug\\Fixture\\Enum\\MealsBacked',
                    'hash' => \md5('bdk\\Test\\Debug\\Fixture\\Enum\\MealsBacked::DINNER'),
                ),
            ),

            'enumConstantAsString' => array(
                'bdk\\Test\\Debug\\Fixture\\Enum\\Meals::REGULAR_CONSTANT',
                false,
                array(
                    'instanceOf' => 'ReflectionClassConstant',
                    'classname' => 'bdk\\Test\\Debug\\Fixture\\Enum\\Meals',
                    'hash' => \md5('bdk\\Test\\Debug\\Fixture\\Enum\\Meals::REGULAR_CONSTANT'),
                ),
            ),
        ));

        return $tests;
    }
}

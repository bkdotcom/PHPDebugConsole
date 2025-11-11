<?php

namespace bdk\Test\Container;

use bdk\Container;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Container
 * @covers \bdk\Container\ObjectBuilder
 */
class ObjectBuilderTest extends TestCase
{
    use ExpectExceptionTrait;

    private $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testBuildWithExistingClassInContainer()
    {
        $classname = 'SomeClass';
        $instance = new \stdClass();
        $this->container[$classname] = static function () use ($instance) {
            return $instance;
        };
        $this->assertSame($instance, $this->container->getObject($classname));
    }

    public function testBuildWithClassWithoutConstructor()
    {
        $classname = 'stdClass';
        $this->assertInstanceOf($classname, $this->container->getObject($classname));
    }

    public function testBuildWithClassWithConstructor()
    {
        $classname = 'bdk\Test\Container\Fixture\ResolvableConstructor';

        // test builds dependency from scratch
        $builtObj = $this->container->getObject($classname);
        $this->assertInstanceOf($classname, $builtObj);
        $this->assertSame($this->container['stdClass'], $builtObj->dependency);

        // test gets dependency from container
        unset($this->container[$classname]);
        $builtObj->dependency->foo = 'bar';
        $builtObj = $this->container->getObject($classname);
        $this->assertSame('bar', $builtObj->dependency->foo);

        if (PHP_VERSION_ID < 70000) {
            return;
        }

        unset($this->container[$classname]);
        $refObjBuilder = new \ReflectionProperty($this->container, 'objectBuilder');
        if (PHP_VERSION_ID < 80100) {
            $refObjBuilder->setAccessible(true);
        }
        $objectBuilder = $refObjBuilder->getValue($this->container);
        $refUseGetType = new \ReflectionProperty($objectBuilder, 'useGetType');
        if (PHP_VERSION_ID < 80100) {
            $refUseGetType->setAccessible(true);
        }
        $refUseGetType->setValue($objectBuilder, false);
        $builtObj = $this->container->getObject($classname);
        $this->assertInstanceOf($classname, $builtObj);
        $this->assertSame($this->container['stdClass'], $builtObj->dependency);
        $refUseGetType->setValue($objectBuilder, true);
    }

    public function testBuildResolveViaPhpDoc()
    {
        $classname = 'bdk\Test\Container\Fixture\ResolvableConstructorPhpDoc';
        $builtObj = $this->container->getObject($classname);
        $this->assertInstanceOf($classname, $builtObj);
        $this->assertInstanceOf('stdClass', $builtObj->dependency1);
        $this->assertInstanceOf('bdk\Test\Container\Fixture\ResolvableConstructor', $builtObj->dependency2);
        $this->assertInstanceOf('bdk\Test\Container\Fixture\Service', $builtObj->dependency3);
        $this->assertSame(42, $builtObj->int);
    }

    public function testBuildWithUnresolvableConstructorParameter()
    {
        $classname = 'bdk\Test\Container\Fixture\UnresolvableConstructor';

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('GetObject(' . $classname . ') : Cannot resolve parameter "dependency"');

        $this->container->getObject($classname);
    }

    public function testUnionAndIntersectionType()
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Intersection type requires php 8.1');
        }

        $this->container['bdk\Test\Container\Fixture\ServiceProvider'] = static function () {
            return new \bdk\Test\Container\Fixture\ServiceProvider();
        };
        $this->container->addAlias('bdk\Container\ServiceProviderInterface', 'bdk\Test\Container\Fixture\ServiceProvider');

        $classname = 'bdk\Test\Container\Fixture\ResolvableConstructorUnion';
        $builtObj = $this->container->getObject($classname);
        $this->assertInstanceOf($classname, $builtObj);
        $this->assertInstanceOf('stdClass', $builtObj->dependency1);
        $this->assertInstanceOf('bdk\Test\Container\Fixture\ServiceProvider', $builtObj->dependency2);
    }
}

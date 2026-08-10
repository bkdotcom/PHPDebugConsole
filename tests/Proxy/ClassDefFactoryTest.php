<?php

namespace bdk\Test\Proxy;

use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Proxy\ClassDefFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Proxy\ClassDefFactory
 */
class ClassDefFactoryTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    public function testGetClassDefForNonExistingInterface(): void
    {
        $this->expectException('ReflectionException');
        $this->expectExceptionMessage('Class (or interface) "bdk\Test\Proxy\Fixture\NonExistingNodeInterface" does not exist');

        $factory = new ClassDefFactory();
        $factory->getClassDef('bdk\Test\Proxy\Fixture\NonExistingNodeInterface');
    }

    /*
    public function testGetClassDefForInterface(): void
    {
        $factory = new ClassDefFactory();
        $definition = $factory->getClassDef(NodeInterface::class);
        $expectedDefinition = array(
            'interfaces' => [
                'Countable',
                'bdk\Test\Proxy\Fixture\NodeParentInterface',
                'bdk\Test\Proxy\Fixture\NodeGrandParentInterface',
            ],
            'isInterface' => true,
            'namespace' => 'bdk\Test\Proxy\Fixture',
            'modifiers' => [],
            'name' => 'bdk\Test\Proxy\Fixture\NodeInterface',
            'methods' => [
                'nodeInterfaceMethod1' => array(
                    'modifiers' => [
                        'abstract',
                        'public',
                        'static',
                    ],
                    'name' => 'nodeInterfaceMethod1',
                    'parameters' => [
                        array(
                            'type' => null,
                            'name' => 'param1',
                            'isDefaultValueAvailable' => false,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => null,
                        ),
                        array(
                            'type' => 'int',
                            'name' => 'param2',
                            'isDefaultValueAvailable' => false,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => null,
                        ),
                        array(
                            'type' => 'ArrayIterator',
                            'name' => 'param3',
                            'isDefaultValueAvailable' => false,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => null,
                        ),
                        array(
                            'type' => 'mixed',
                            'name' => 'param4',
                            'isDefaultValueAvailable' => false,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => null,
                        ),
                        array(
                            'type' => '?bool',
                            'name' => 'param5',
                            'isDefaultValueAvailable' => false,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => null,
                        ),
                        array(
                            'type' => 'float',
                            'name' => 'param6',
                            'isDefaultValueAvailable' => true,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => 3.5,
                        ),
                        array(
                            'type' => 'array',
                            'name' => 'param7',
                            'isDefaultValueAvailable' => true,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => [],
                        ),
                        array(
                            'type' => 'string',
                            'name' => 'param8',
                            'isDefaultValueAvailable' => true,
                            'isDefaultValueConstant' => true,
                            'defaultValue' => 'bdk\Test\Proxy\Fixture\CONST1',
                        ),
                    ],
                    'returnType' => '?int',
                ),
                'nodeInterfaceMethod2' => array(
                    'modifiers' => [
                        'abstract',
                        'public',
                    ],
                    'name' => 'nodeInterfaceMethod2',
                    'parameters' => [],
                    'returnType' => null,
                ),
                'nodeInterfaceMethod3' => array(
                    'modifiers' => [
                        'abstract',
                        'public',
                    ],
                    'name' => 'nodeInterfaceMethod3',
                    'parameters' => [
                        'param1' => array(
                            'type' => 'bool',
                            'name' => 'param1',
                            'isDefaultValueAvailable' => true,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => false,
                        ),
                        'param2' => array(
                            'type' => 'bool',
                            'name' => 'param2',
                            'isDefaultValueAvailable' => true,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => true,
                        ),
                        'param3' => array(
                            'type' => 'string',
                            'name' => 'param3',
                            'isDefaultValueAvailable' => true,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => 'string',
                        ),
                        'param4' => array(
                            'type' => '?string',
                            'name' => 'param4',
                            'isDefaultValueAvailable' => true,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => null,
                        ),
                        'param5' => array(
                            'type' => 'array',
                            'name' => 'param5',
                            'isDefaultValueAvailable' => true,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => [1, 'value'],
                        ),
                        'param6' => array(
                            'type' => 'Stringable|string',
                            'name' => 'param6',
                            'isDefaultValueAvailable' => true,
                            'isDefaultValueConstant' => false,
                            'defaultValue' => 'stringable',
                        ),
                    ],
                    'returnType' => 'void',
                ),
                'count' => array(
                    'modifiers' => [
                        'abstract',
                        'public',
                    ],
                    'name' => 'count',
                    'parameters' => [],
                    'returnType' => PHP_VERSION_ID >= 80100
                        ? 'int'
                        : null,
                ),
                'parentMethod1' => array(
                    'modifiers' => [
                        'abstract',
                        'public',
                    ],
                    'name' => 'parentMethod1',
                    'parameters' => [],
                    'returnType' => PHP_VERSION_ID >= 80500
                        ? 'bdk\Test\Proxy\Fixture\NodeParentInterface'
                        : 'self',
                ),
                'parentMethod2' => array(
                    'modifiers' => [
                        'abstract',
                        'public',
                    ],
                    'name' => 'parentMethod2',
                    'parameters' => [],
                    'returnType' => null,
                ),
                'grandParentMethod1' => array(
                    'modifiers' => [
                        'abstract',
                        'public',
                    ],
                    'name' => 'grandParentMethod1',
                    'parameters' => [],
                    'returnType' => 'ArrayObject',
                ),
                'grandParentMethod2' => array(
                    'modifiers' => [
                        'abstract',
                        'public',
                    ],
                    'name' => 'grandParentMethod2',
                    'parameters' => [],
                    'returnType' => 'ArrayObject',
                ),
                'grandParentMethod3' => array(
                    'modifiers' => [
                        'abstract',
                        'public',
                    ],
                    'name' => 'grandParentMethod3',
                    'parameters' => [],
                    'returnType' => 'bdk\Test\Proxy\Fixture\Node',
                ),
                'grandParentMethod4' => array(
                    'modifiers' => [
                        'abstract',
                        'public',
                    ],
                    'name' => 'grandParentMethod4',
                    'parameters' => [],
                    'returnType' => 'bdk\Test\Proxy\Fixture\Node',
                ),
            ],
            'parentClass' => null,
            'shortName' => 'NodeInterface',
        );

        $this->assertEquals($expectedDefinition, $definition);
    }
    */

    public function testGetClassDefForClass(): void
    {
        $factory = new ClassDefFactory();
        $definition = $factory->getClassDef('bdk\\Test\\Proxy\\Fixture\\Widget');
        $expectedDefinition = array(
            'interfaces' => ['bdk\Test\Proxy\Fixture\WidgetInterface'],
            'isInterface' => false,
            'methods' => [
                '__construct' => array(
                    'modifiers' => ['public'],
                    'name' => '__construct',
                    'parameters' => [
                        array(
                            'defaultValue' => array(),
                            'isDefaultValueAvailable' => true,
                            'isDefaultValueConstant' => false,
                            'isPassedByReference' => false,
                            'isVariadic' => false,
                            'name' => 'values',
                            'type' => null,
                        ),
                    ],
                    'returnType' => null,
                ),
                '__get' => array(
                    'modifiers' => ['public'],
                    'name' => '__get',
                    'parameters' => [
                        array(
                            'defaultValue' => null,
                            'isDefaultValueAvailable' => false,
                            'isDefaultValueConstant' => false,
                            'isPassedByReference' => false,
                            'isVariadic' => false,
                            'name' => 'name',
                            'type' => null,
                        ),
                    ],
                    'returnType' => null,
                ),
                '__set' => array(
                    'modifiers' => ['public'],
                    'name' => '__set',
                    'parameters' => [
                        array(
                            'defaultValue' => null,
                            'isDefaultValueAvailable' => false,
                            'isDefaultValueConstant' => false,
                            'isPassedByReference' => false,
                            'isVariadic' => false,
                            'name' => 'name',
                            'type' => null,
                        ),
                        array(
                            'defaultValue' => null,
                            'isDefaultValueAvailable' => false,
                            'isDefaultValueConstant' => false,
                            'isPassedByReference' => false,
                            'isVariadic' => false,
                            'name' => 'value',
                            'type' => null,
                        ),
                    ],
                    'returnType' => null,
                ),
                'test' => array(
                    'modifiers' => ['public'],
                    'name' => 'test',
                    'parameters' => [
                        array(
                            'defaultValue' => null,
                            'isDefaultValueAvailable' => false,
                            'isDefaultValueConstant' => false,
                            'isPassedByReference' => false,
                            'isVariadic' => false,
                            'name' => 'param',
                            'type' => null,
                        ),
                    ],
                    'returnType' => null,
                ),
                'getInstance' => array(
                    'modifiers' => ['public'],
                    'name' => 'getInstance',
                    'parameters' => [],
                    'returnType' => null,
                ),
                'factory' => array(
                    'modifiers' => ['public', 'static'],
                    'name' => 'factory',
                    'parameters' => [],
                    'returnType' => null,
                ),
                'broken' => array(
                    'modifiers' => ['public'],
                    'name' => 'broken',
                    'parameters' => [],
                    'returnType' => null,
                ),
            ],
            'modifiers' => [],
            'name' => 'bdk\Test\Proxy\Fixture\Widget',
            'namespace' => 'bdk\Test\Proxy\Fixture',
            'parentClass' => null,
            'properties' => [],
            'shortName' => 'Widget',
        );

        $this->assertSame($expectedDefinition, $definition);
    }
}

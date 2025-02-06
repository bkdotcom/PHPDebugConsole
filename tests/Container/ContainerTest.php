<?php

namespace bdk\Test\Container;

use bdk\Container;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author BradKent <bkfake-github@yahoo.com>
 *
 * @covers \bdk\Container
 * @covers \bdk\Container\Utility
 */
class ContainerTest extends TestCase
{
    use ExpectExceptionTrait;

    public function testDebugInfo()
    {
        $params = array('param' => 'value');
        $container = new Container($params);

        $expect = array(
            'cfg' => array(
                'allowOverride' => false,
                'onInvoke' => null,
            ),
            'invoked' => array(),
            'keys' => array(
                'param' => true,
            ),
            'raw' => "\x00notInspected\x00",
            'values' => "\x00notInspected\x00",
        );
        $this->assertSame($expect, $container->__debugInfo());
    }

    public function testConstructorParams()
    {
        $params = array('param' => 'value');
        $container = new Container($params);

        $this->assertSame($params['param'], $container['param']);
    }

    public function testAllowOverride()
    {
        $container = new Container(array(), array(
            'allowOverride' => true,
        ));
        $container['service'] = function () {
            return new Fixture\Service();
        };
        $container->get('service');
        $container['service'] = function () {
            return 'And now for something completely different';
        };
        $this->assertSame('And now for something completely different', $container['service']);
    }

    public function testExtend()
    {
        $container = new Container();
        $container['service'] = function () {
            return new Fixture\Service();
        };
        $container->extend('service', static function (Fixture\Service $service, Container $container) {
            $service->value = 'extended';
            return $service;
        });

        $service = $container['service'];
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $service);
        $this->assertSame('extended', $service->value);
    }

    public function testOnInvokeCallback()
    {
        $args = array();
        $container = new Container(array(), array(
            'onInvoke' => function ($val, $id, Container $container) use (&$args) {
                $args = \func_get_args();
            },
        ));
        $container['int'] = 42;
        $container['service'] = function () {
            return new Fixture\Service();
        };
        $container['factory'] = $container->factory(function () {
            return new Fixture\Service();
        });

        $container->get('service');
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $args[0]);
        $this->assertSame('service', $args[1]);
        $this->assertSame($container, $args[2]);

        $container->get('factory');
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $args[0]);
        $this->assertSame('factory', $args[1]);
        $this->assertSame($container, $args[2]);
    }

    public function testSetCfg()
    {
        $container = new Container();
        $container['service'] = function () {
            return new Fixture\Service();
        };
        // can override if not yet invoked
        $container['service'] = function () {
            return 'new';
        };
        // invoke it
        $this->assertSame('new', $container['service']);
        $exceptionMessage = null;
        try {
            $container['service'] = function () {
                return 'new 2';
            };
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();
        }
        $this->assertSame('Cannot update "service" after it has been instantiated.', $exceptionMessage);
        $args = array();
        $container->setCfg('allowOverride', true);
        $container->setCfg(array(
            'onInvoke' => function ($val, $id, Container $container) use (&$args) {
                $args = \func_get_args();
            },
        ));
        $container['service'] = function () {
            return 'new 3';
        };
        $this->assertSame('new 3', $container->get('service'));
        $this->assertSame('new 3', $args[0]);
        $this->assertSame('service', $args[1]);
        $this->assertSame($container, $args[2]);
    }

    public function testWithString()
    {
        $container = new Container();
        $container['param'] = 'value';
        $this->assertEquals('value', $container['param']);
    }

    public function testWithClosure()
    {
        $container = new Container();
        $container['service'] = function () {
            return new Fixture\Service();
        };
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $container['service']);
    }

    public function testWithGlobalFunctionName()
    {
        $container = new Container();
        $container['param'] = 'strlen';
        $this->assertSame('strlen', $container['param']);
    }

    public function testWithInvokableObject()
    {
        $container = new Container();
        $container['invokable'] = new Fixture\Invokable();
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $container['invokable']);
    }

    public function testWithNonInvokableObject()
    {
        $container = new Container();
        $container['non_invokable'] = new Fixture\NonInvokable();
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\NonInvokable', $container['non_invokable']);
    }

    public function testFactoryValuesDifferent()
    {
        $container = new Container();
        $container['service'] = $container->factory(function () {
            return new Fixture\Service();
        });

        $serviceOne = $container['service'];
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $serviceOne);

        $serviceTwo = $container['service'];
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $serviceTwo);

        $this->assertNotSame($serviceOne, $serviceTwo);
    }

    /**
     * @dataProvider providerBadServiceDefinition
     */
    public function testFactoryFailsForInvalidServiceDefinitions($service)
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Closure or invokable object expected.');

        $container = new Container();
        $container->factory($service);
    }

    /**
     * @dataProvider providerServiceDefinition
     */
    public function testProtect($service)
    {
        $container = new Container();
        $container['protected'] = $container->protect($service);

        $this->assertSame($service, $container['protected']);
    }

    /**
     * @dataProvider providerBadServiceDefinition
     */
    public function testProtectFailsForInvalidServiceDefinitions($service)
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Closure or invokable object expected.');

        $container = new Container();
        $container->protect($service);
    }

    /**
     * @dataProvider providerServiceDefinition
     */
    public function testServiceValuesSame($service)
    {
        $container = new Container();
        $container['service'] = $service;

        $serviceOne = $container['service'];
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $serviceOne);

        $serviceTwo = $container['service'];
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $serviceTwo);

        $this->assertSame($serviceOne, $serviceTwo);
    }

    public function testShouldPassContainerAsParameter()
    {
        $container = new Container(array(
            'service' => function () {
                return new Fixture\Service();
            },
            'container' => function ($container) {
                return $container;
            },
        ));

        $this->assertNotSame($container, $container['service']);
        $this->assertSame($container, $container['container']);
    }

    public function testGet()
    {
        $container = new Container();
        $container->registerProvider(new Fixture\ServiceProvider());
        /*
        $container['string'] = 'foo';
        $container['service'] = function () {
            return new Fixture\Service();
        };
        $container['factoryService'] = $container->factory(function () {
            return new Fixture\Service();
        });
        $closure = function () {
            return 'this is a test';
        };
        $container['protected'] = $container->protect($closure);
        */
        $this->assertSame('value', $container->get('param'));
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $container->get('service'));
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $container->get('factory'));
        $this->assertTrue(\method_exists($container->get('protected'), '__invoke'));
    }

    public function testNeedsInvoked()
    {
        $container = new Container();
        $container->registerProvider(new Fixture\ServiceProvider());
        $this->assertFalse($container->needsInvoked('param'));
        $this->assertTrue($container->needsInvoked('service'));
        $this->assertTrue($container->needsInvoked('factory'));
        $this->assertFalse($container->needsInvoked('protected'));
        $container->get('param');
        $container->get('service');
        $container->get('factory');
        $container->get('protected');
        $this->assertFalse($container->needsInvoked('param'));
        $this->assertFalse($container->needsInvoked('service'));
        $this->assertTrue($container->needsInvoked('factory'));
        $this->assertFalse($container->needsInvoked('protected'));
    }

    public function testSetValues()
    {
        $container = new Container();
        $container['hereFirst'] = 'I was here first';
        $closure = function () {
            return 'this is a test';
        };
        $container->setValues(array(
            'string' => 'foo',
            'service' => function () {
                return new Fixture\Service();
            },
            'factoryService' => $container->factory(function () {
                return new Fixture\Service();
            }),
            'protected' => $container->protect($closure),
        ));
        $this->assertSame(array(
            'hereFirst',
            'string',
            'service',
            'factoryService',
            'protected',
        ), $container->keys());
        $this->assertSame('I was here first', $container->get('hereFirst'));
        $this->assertSame('foo', $container->get('string'));
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $container->get('service'));
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $container->get('factoryService'));
        $this->assertSame($closure, $container->get('protected'));
    }

    public function testKeys()
    {
        $container = new Container();
        $container['foo'] = 123;
        $container['bar'] = 123;

        $this->assertEquals(['foo', 'bar'], $container->keys());
    }

    public function testIsset()
    {
        $container = new Container(array(
            'param' => 'value',
            'service' => function () {
                return new Fixture\Service();
            },
            'null' => null,
        ));

        $this->assertTrue(isset($container['param']));
        $this->assertTrue(isset($container['service']));
        $this->assertTrue(isset($container['null']));

        $this->assertTrue($container->has('param'));
        $this->assertTrue($container->has('service'));
        $this->assertTrue($container->has('null'));

        $this->assertFalse(isset($container['non_existent']));
        $this->assertFalse($container->has('non_existent'));
    }

    public function testUnset()
    {
        $container = new Container();
        $container['param'] = 'value';
        $container['service'] = function () {
            return new Fixture\Service();
        };

        unset($container['undefined'], $container['param'], $container['service']);
        $this->assertFalse(isset($container['param']));
        $this->assertFalse(isset($container['service']));
        $this->assertFalse($container->has('param'));
        $this->assertFalse($container->has('service'));
    }

    public function testOffsetGetAssertsValidKey()
    {
        $this->expectException('OutOfBoundsException');
        $this->expectExceptionMessage('Unknown identifier: "foo"');

        $container = new Container();
        $container['foo'];
    }

    public function testOffsetGetHonorsNullValues()
    {
        $container = new Container();
        $container['foo'] = null;
        $this->assertNull($container['foo']);
    }

    public function testRaw()
    {
        $container = new Container();
        $service = function () {
            return 'service';
        };
        $container['service'] = $service;
        $factory = $container->factory(function () {
            return 'factory';
        });
        $container['factory'] = $factory;

        $this->assertSame($service, $container->raw('service'));
        $container['service'];
        $this->assertSame($service, $container->raw('service'));

        $this->assertSame($factory, $container->raw('factory'));
        $container['factory'];
        $this->assertSame($factory, $container->raw('factory'));
    }

    public function testRawAssertsValidKey()
    {
        $this->expectException('OutOfBoundsException');
        $this->expectExceptionMessage('Unknown identifier: "foo"');

        $container = new Container();
        $container->raw('foo');
    }

    public function testRawHonorsNullValues()
    {
        $container = new Container();
        $container['foo'] = null;
        $this->assertNull($container->raw('foo'));
    }

    public function testRegisterProvider()
    {
        $container = new Container();

        $serviceProvider = new Fixture\ServiceProvider();
        $this->assertSame($container, $container->registerProvider($serviceProvider));

        $this->assertEquals([
            'param',
            'service',
            'factory',
            'protected',
        ], $container->keys());
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $container['service']);
        $this->assertInstanceOf('bdk\\Test\\Container\\Fixture\\Service', $container['factory']);
    }

    public function testDefiningNewServiceAfterInvoke()
    {
        $container = new Container(array(
            'foo' => function () {
                return 'foo';
            },
        ));

        // invoke it
        $container['foo'];

        $container['bar'] = function () {
            return 'bar';
        };
        $this->assertSame('bar', $container['bar']);
    }

    public function testOverridingInvokedService()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Cannot update "foo" after it has been instantiated.');

        $container = new Container(array(
            'foo' => function () {
                return 'foo';
            },
        ));

        // invoke it
        $container['foo'];

        $container['foo'] = function () {
            return 'bar';
        };
    }

    public function testRemovingServiceAfterInvoke()
    {
        $container = new Container(array(
            'foo' => function () {
                return 'foo';
            }
        ));

        // invoke it
        $container['foo'];

        unset($container['foo']);
        $container['foo'] = function () {
            return 'bar';
        };
        $this->assertSame('bar', $container['foo']);
    }

    /**
     * Provider for invalid service definitions.
     */
    public static function providerBadServiceDefinition()
    {
        return [
            [123],
            [new Fixture\NonInvokable()],
        ];
    }

    /**
     * Provider for service definitions.
     */
    public static function providerServiceDefinition()
    {
        return [
            [function ($value) {
                $service = new Fixture\Service();
                $service->value = $value;

                return $service;
            }],
            [new Fixture\Invokable()],
        ];
    }
}

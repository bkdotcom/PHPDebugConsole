<?php

namespace bdk\Test\Container;

use bdk\Container;
use bdk\Container\Utility;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Test\Container\Fixture\ServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Container\Utility
 */
class UtilityTest extends TestCase
{
    use ExpectExceptionTrait;

    public function testToRawValues()
    {
        // container
        $container = new Container(array(
            'foo' => 'bar',
            'service' => static function () {
                return new \stdClass();
            },
        ));
        $rawValues = Utility::toRawValues($container);
        self::assertSame('bar', $rawValues['foo']);
        self::assertInstanceOf('Closure', $rawValues['service']);

        // serviceProvider
        $serviceProvider = new ServiceProvider();
        $rawValues = Utility::toRawValues($serviceProvider);
        self::assertSame('value', $rawValues['param']);
        self::assertInstanceOf('Closure', $rawValues['service']);
        self::assertInstanceOf('Closure', $rawValues['factory']);
        self::assertInstanceOf('Closure', $rawValues['protected']);

        // callable
        $callable = static function (Container $container) {
            $container['thing'] = 'stick';
            $container['service']  = static function () {
                return new \stdClass();
            };
        };
        $rawValues = Utility::toRawValues($callable);
        self::assertSame('stick', $rawValues['thing']);
        self::assertInstanceOf('Closure', $rawValues['service']);

        // array
        $array = array(
            'foo' => 'bar',
            'baz' => array(
                'qux' => 'quux',
            ),
        );
        $rawValues = Utility::toRawValues($array);
        self::assertSame($array, $rawValues);
    }

    public function testToRawValuesExceeption()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('toRawValues expects Container, ServiceProviderInterface, callable, or key->value array. string provided');
        Utility::toRawValues('string');
    }
}

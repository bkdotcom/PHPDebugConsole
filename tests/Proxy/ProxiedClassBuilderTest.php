<?php

namespace bdk\Test\Proxy;

use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\Proxy\ClassDefFactory;
use bdk\Proxy\Manager;
use bdk\Proxy\ProxiedClassBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Proxy\ClassDefFactory
 * @covers \bdk\Proxy\Manager
 * @covers \bdk\Proxy\ProxiedClassBuilder
 */
class ProxiedClassBuilderTest extends TestCase
{
    use AssertionTrait;

    public function testBuildInterface(): void
    {
        $manager = new Manager();
        $builder = new ProxiedClassBuilder();
        $definition = $manager->getClassDef('bdk\\Test\\Proxy\\Fixture\\WidgetInterface');
        $code = $builder->build($definition);
        $codeExpected = 'use bdk\Proxy\ProxyTrait;

class bdk_Test_Proxy_Fixture_WidgetInterfaceProxy implements bdk\Test\Proxy\Fixture\WidgetInterface
{
    use ProxyTrait;

    public static ' . (PHP_VERSION_ID >= 70400 ? 'bool ' : '') . '$proxyExtendOnly = false;

    private static ' . (PHP_VERSION_ID >= 70400 ? '?string ' : '') . '$proxyParentClassName = null;

    private static ' . (PHP_VERSION_ID >= 70400 ? 'array ' : '') . '$proxyParentPublicProperties = array();

    public function __call($method, $args)
    {
        return $this->proxyCall($method, $args);
    }

    public static function __callStatic($method, $args)
    {
        return self::proxyCallStatic($method, $args);
    }

    public function test($param)
    {
        return $this->proxyCall(\'test\', func_get_args());
    }
}';
        // \bdk\Debug::varDump('actual', $code);
        // \bdk\Debug::varDump('expect', $codeExpected);
        $this->assertSame($codeExpected, $code);
    }

    /*
    public function testBuildClassWithoutParent(): void
    {
        $factory = new ClassDefFactory();
        $definition = $factory->getProxyDef(EmptyClass::class);
        $builder = new ProxiedClassBuilder();

        $code = $builder->build($definition);
        $codeExpected = <<<'EOD'
use bdk\Proxy\ProxyTrait;

class bdk_Test_Proxy_Fixture_EmptyClassProxy extends bdk\Test\Proxy\Fixture\EmptyClass
{
    use ProxyTrait;
}
EOD;
        $this->assertSame($codeExpected, $code);
    }
    */

    public function testBuildFinalNoImplement(): void
    {
        $factory = new ClassDefFactory();
        $builder = new ProxiedClassBuilder();
        $definition = $factory->getClassDef('bdk\\Test\\Proxy\\Fixture\\FinalNoImplement');
        $code = $builder->build($definition);
        $codeExpected = <<<'EOD'
use bdk\Proxy\ProxyTrait;

final class bdk_Test_Proxy_Fixture_FinalNoImplementProxy
{
    use ProxyTrait;

    public function count()
    {
        return $this->proxyCall('count', func_get_args());
    }
}
EOD;

        $this->assertSame($codeExpected, $code);
    }

    public function testBuildFinalImplements(): void
    {
        $manager = new Manager();
        $builder = new ProxiedClassBuilder();
        $definition = $manager->getClassDef('bdk\\Test\\Proxy\\Fixture\\FinalImplements');
        $code = $builder->build($definition);
        $codeExpected = 'use bdk\Proxy\ProxyTrait;

final class bdk_Test_Proxy_Fixture_FinalImplementsProxy implements bdk\Test\Proxy\Fixture\WidgetInterface
{
    use ProxyTrait;

    public static ' . (PHP_VERSION_ID >= 70400 ? 'bool ' : '') . '$proxyExtendOnly = false;

    private static ' . (PHP_VERSION_ID >= 70400 ? '?string ' : '') . '$proxyParentClassName = \'bdk\\\Test\\\Proxy\\\Fixture\\\FinalImplements\';

    private static ' . (PHP_VERSION_ID >= 70400 ? 'array ' : '') . '$proxyParentPublicProperties = array();

    public function __construct($values = array())
    {
        $this->proxyCall(\'__construct\', func_get_args());
    }

    public function test($param = null)
    {
        return $this->proxyCall(\'test\', func_get_args());
    }
}';

        $this->assertSame($codeExpected, $code);
    }

    public function testVariadicAndReferenceParameters(): void
    {
        if (PHP_VERSION_ID < 50600) {
            $this->markTestSkipped('Variadic params require PHP 5.6');
        }
        $factory = new ClassDefFactory();
        $builder = new ProxiedClassBuilder();
        $definition = $factory->getClassDef('bdk\\Test\\Proxy\\Fixture\\VariadicAndReference');
        $code = $builder->build($definition);
        $codeExpected = <<<'EOD'
use bdk\Proxy\ProxyTrait;

class bdk_Test_Proxy_Fixture_VariadicAndReferenceProxy extends bdk\Test\Proxy\Fixture\VariadicAndReference
{
    use ProxyTrait;

    public static function byRef(&$paramByRef)
    {
        return self::proxyCallStatic('byRef', [&$paramByRef]);
    }

    public function variadic(...$params)
    {
        return $this->proxyCall('variadic', [...$params]);
    }

    public function variadicByRef(&...$params)
    {
        return $this->proxyCall('variadicByRef', [...$params]);
    }
}
EOD;
        if (PHP_VERSION_ID < 70400) {
            $codeExpected = <<<'EOD'
use bdk\Proxy\ProxyTrait;

class bdk_Test_Proxy_Fixture_VariadicAndReferenceProxy extends bdk\Test\Proxy\Fixture\VariadicAndReference
{
    use ProxyTrait;

    public static function byRef(&$paramByRef)
    {
        return self::proxyCallStatic('byRef', [&$paramByRef]);
    }

    public function variadic(...$params)
    {
        $proxyParamsTemp = [];
        foreach ($params as &$value) {
            $proxyParamsTemp[] = &$value;
        }
        return $this->proxyCall('variadic', $proxyParamsTemp);
    }

    public function variadicByRef(&...$params)
    {
        $proxyParamsTemp = [];
        foreach ($params as &$value) {
            $proxyParamsTemp[] = &$value;
        }
        return $this->proxyCall('variadicByRef', $proxyParamsTemp);
    }
}
EOD;

        }

        $this->assertSame($codeExpected, $code);
    }


    /**
     * @requires PHP >= 8.2
     */
    public function testTypesDisjunctiveNormalForm(): void
    {
        if (PHP_VERSION_ID < 80200) {
            $this->markTestSkipped('Types in disjunctive normal form require PHP 8.2');
        }
        $factory = new ClassDefFactory();
        $builder = new ProxiedClassBuilder();
        $definition = $factory->getClassDef('bdk\\Test\\Proxy\\Fixture\\TypesDisjunctiveNormalForm');
        $code = $builder->build($definition);
        $codeExpected = <<<'EOD'
use bdk\Proxy\ProxyTrait;

class bdk_Test_Proxy_Fixture_TypesDisjunctiveNormalFormProxy extends bdk\Test\Proxy\Fixture\TypesDisjunctiveNormalForm
{
    use ProxyTrait;

    public function test((Stringable&Countable)|string|int|null $param): bdk\Test\Proxy\Fixture\WidgetInterface|(Stringable&Countable)|null
    {
        return $this->proxyCall('test', func_get_args());
    }
}
EOD;

        $this->assertSame($codeExpected, $code);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testTypesIntersection(): void
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Intersection types require PHP 8.1');
        }
        $factory = new ClassDefFactory();
        $builder = new ProxiedClassBuilder();
        $definition = $factory->getClassDef('bdk\\Test\\Proxy\\Fixture\\TypesIntersection');
        $code = $builder->build($definition);
        $codeExpected = <<<'EOD'
use bdk\Proxy\ProxyTrait;

class bdk_Test_Proxy_Fixture_TypesIntersectionProxy extends bdk\Test\Proxy\Fixture\TypesIntersection
{
    use ProxyTrait;

    public function test(Stringable&Countable $param): Stringable&Countable
    {
        return $this->proxyCall('test', func_get_args());
    }
}
EOD;

        $this->assertSame($codeExpected, $code);
    }

    /**
     * @requires PHP >= 8.0
     */
    public function testTypesUnion(): void
    {
        if (PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Union types require PHP 8.0');
        }
        $factory = new ClassDefFactory();
        $builder = new ProxiedClassBuilder();
        $definition = $factory->getClassDef('bdk\\Test\\Proxy\\Fixture\\TypesUnion');
        $code = $builder->build($definition);
        $codeExpected = <<<'EOD'
use bdk\Proxy\ProxyTrait;

class bdk_Test_Proxy_Fixture_TypesUnionProxy extends bdk\Test\Proxy\Fixture\TypesUnion
{
    use ProxyTrait;

    public function test(string|int|null $param): string|int|null
    {
        return $this->proxyCall('test', func_get_args());
    }
}
EOD;

        $this->assertSame($codeExpected, $code);
    }
}

<?php

namespace bdk\Test\Proxy;

use bdk\Proxy\ClassDefFactory;
use bdk\Proxy\Manager;
use bdk\Proxy\ProxiedClassBuilder;
use bdk\Test\Proxy\Fixture\FinalImplements;
use bdk\Test\Proxy\Fixture\FinalNoImplement;
use bdk\Test\Proxy\Fixture\TypesDisjunctiveNormalForm;
use bdk\Test\Proxy\Fixture\TypesIntersection;
use bdk\Test\Proxy\Fixture\TypesUnion;
use bdk\Test\Proxy\Fixture\VariadicAndReference;
use bdk\Test\Proxy\Fixture\WidgetInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Proxy\ClassDefFactory
 * @covers \bdk\Proxy\Manager
 * @covers \bdk\Proxy\ProxiedClassBuilder
 */
class ProxiedClassBuilderTest extends TestCase
{
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
        return $this->proxyCall(\'test\', [$param]);
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
        return $this->proxyCall('count', []);
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
        $this->proxyCall(\'__construct\', [$values]);
    }

    public function test($param = null)
    {
        return $this->proxyCall(\'test\', [$param]);
    }
}';

        $this->assertSame($codeExpected, $code);
    }

    public function testVariadicAndReferenceParameters(): void
    {
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
        $this->assertSame($codeExpected, $code);
    }


    /**
     * @requires PHP >= 8.2
     */
    public function testTypesDisjunctiveNormalForm(): void
    {
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
        return $this->proxyCall('test', [$param]);
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
        return $this->proxyCall('test', [$param]);
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
        return $this->proxyCall('test', [$param]);
    }
}
EOD;

        $this->assertSame($codeExpected, $code);
    }
}

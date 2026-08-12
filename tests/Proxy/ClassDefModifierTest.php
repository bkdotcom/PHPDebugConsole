<?php

namespace bdk\Test\Proxy;

use bdk\Proxy\ClassDefModifier;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Proxy\ClassDefModifier
 */
class ClassDefModifierTest extends TestCase
{
    /**
     * Test an unknown class definition is returned unchanged.
     *
     * @return void
     */
    public function testUnknownClassIsNotModified(): void
    {
        $classDef = array(
			'methods' => array('example' => array('parameters' => array())),
			'name' => 'Example',
        );

        $this->assertSame($classDef, (new ClassDefModifier())->modify($classDef));
    }

    /**
     * Test mysqli's incomplete reflection defaults are added.
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     *
     * @return void
     */
    public function testModifyMysqli(): void // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    {
        $classDef = $this->classDef('mysqli', array(
			'begin_transaction' => 2,
			'commit' => 2,
			'connect' => 6,
			'mysqli' => 6,
			'poll' => 5,
            'query' => 2,
			'real_connect' => 7,
			'rollback' => 2,
			'store_result' => 1,
        ));
        $modified = (new ClassDefModifier())->modify($classDef);

        foreach (array('begin_transaction', 'commit', 'rollback') as $method) {
            $this->assertSame(0, $modified['methods'][$method]['parameters'][0]['defaultValue']);
            $this->assertSame('flags', $modified['methods'][$method]['parameters'][0]['name']);
            $this->assertNull($modified['methods'][$method]['parameters'][1]['defaultValue']);
            $this->assertSame('name', $modified['methods'][$method]['parameters'][1]['name']);
        }
        foreach (array('connect', 'mysqli') as $method) {
            foreach ($modified['methods'][$method]['parameters'] as $parameter) {
                $this->assertNull($parameter['defaultValue']);
                $this->assertTrue($parameter['isDefaultValueAvailable']);
            }
        }
        $this->assertSame(0, $modified['methods']['real_connect']['parameters'][6]['defaultValue']);
        $this->assertSame('flags', $modified['methods']['real_connect']['parameters'][6]['name']);
        $this->assertSame(0, $modified['methods']['poll']['parameters'][4]['defaultValue']);
        $this->assertSame(0, $modified['methods']['store_result']['parameters'][0]['defaultValue']);
    }

    /**
     * Test OAuth's incomplete reflection defaults are added.
     *
     * @return void
     */
    public function testModifyOAuth(): void
    {
        $classDef = $this->classDef('OAuth', array(
            'fetch' => 4,
            'generateSignature' => 3,
            'getAccessToken' => 4,
            'getRequestHeader' => 3,
            'getRequestToken' => 3,
        ));
        $modified = (new ClassDefModifier())->modify($classDef);

        $this->assertSame(array(), $modified['methods']['fetch']['parameters'][1]['defaultValue']);
        $this->assertConstantDefault($modified['methods']['fetch']['parameters'][2], 'OAUTH_HTTP_METHOD_GET');
        $this->assertSame(array(), $modified['methods']['fetch']['parameters'][3]['defaultValue']);
        $this->assertSame(array(), $modified['methods']['generateSignature']['parameters'][2]['defaultValue']);
        $this->assertSame('', $modified['methods']['getAccessToken']['parameters'][1]['defaultValue']);
        $this->assertSame('', $modified['methods']['getAccessToken']['parameters'][2]['defaultValue']);
        $this->assertConstantDefault($modified['methods']['getAccessToken']['parameters'][3], 'OAUTH_HTTP_METHOD_GET');
        $this->assertSame(array(), $modified['methods']['getRequestHeader']['parameters'][2]['defaultValue']);
        $this->assertSame('', $modified['methods']['getRequestToken']['parameters'][1]['defaultValue']);
        $this->assertConstantDefault($modified['methods']['getRequestToken']['parameters'][2], 'OAUTH_HTTP_METHOD_GET');
    }

    /**
     * Test PDO defaults and the legacy overloaded query definition.
     *
     * @return void
     */
    public function testModifyPdoLegacyQueryDefinition(): void
    {
        $classDef = $this->classDef('PDO', array(
			'lastInsertId' => 1,
			'prepare' => 2,
			'query' => 0,
			'quote' => 2,
        ));
        $modified = (new ClassDefModifier())->modify($classDef);

        $this->assertSame(array(), $modified['methods']['prepare']['parameters'][1]['defaultValue']);
        $this->assertNull($modified['methods']['lastInsertId']['parameters'][0]['defaultValue']);
        $this->assertConstantDefault($modified['methods']['quote']['parameters'][1], 'PDO::PARAM_STR');
        // $this->assertTrue($modified['methods']['query']['proxyViaFuncGetArgs']);
        $this->assertSame(
            array('query', 'fetchMode', 'arg3', 'constructorArgs'),
            \array_column($modified['methods']['query']['parameters'], 'name')
        );
        $this->assertConstantDefault($modified['methods']['query']['parameters'][1], 'PDO::FETCH_BOTH');
    }

    /**
     * Test a modern variadic PDO query definition is preserved.
     *
     * @return void
     */
    public function testModifyPdoVariadicQueryDefinitionIsPreserved(): void
    {
        $classDef = $this->classDef('PDO', array(
			'lastInsertId' => 1,
			'prepare' => 2,
			'query' => 3,
			'quote' => 2,
        ));
        $classDef['methods']['query']['parameters'][2]['isVariadic'] = true;
        $classDef['methods']['query']['proxyViaFuncGetArgs'] = false;

        $modified = (new ClassDefModifier())->modify($classDef);

        $this->assertSame($classDef['methods']['query'], $modified['methods']['query']);
    }

    /**
     * Test SoapClient's incomplete reflection defaults are added.
     *
     * @return void
     */
    public function testModifySoapClient(): void
    {
        $classDef = $this->classDef('SoapClient', array(
			'SoapClient' => 2,
			'__doRequest' => 5,
			'__setCookie' => 2,
			'__setLocation' => 1,
			'__setSoapHeaders' => 1,
			'__soapCall' => 5,
        ));
        $modified = (new ClassDefModifier())->modify($classDef);

        $defaults = array(
            array('SoapClient', 1),
            array('__soapCall', 2),
            array('__soapCall', 3),
            array('__soapCall', 4),
            array('__doRequest', 4),
            array('__setCookie', 1),
            array('__setLocation', 0),
            array('__setSoapHeaders', 0),
        );
        foreach ($defaults as $default) {
            $parameter = $modified['methods'][$default[0]]['parameters'][$default[1]];
            $this->assertNull($parameter['defaultValue']);
            $this->assertTrue($parameter['isDefaultValueAvailable']);
        }
    }

    /**
     * Test modern SoapClient definitions without the legacy constructor are preserved.
     *
     * @return void
     */
    public function testSoapClientWithoutLegacyConstructorIsNotModified(): void
    {
        $classDef = array('name' => 'SoapClient', 'methods' => array());

        $this->assertSame($classDef, (new ClassDefModifier())->modify($classDef));
    }

    /**
     * Assert a parameter has the expected constant default.
     *
     * @param array  $parameter Parameter definition
     * @param string $expected  Expected constant name
     *
     * @return void
     */
    private function assertConstantDefault(array $parameter, $expected): void
    {
        $this->assertSame($expected, $parameter['defaultValue']);
        $this->assertTrue($parameter['isDefaultValueAvailable']);
        $this->assertTrue($parameter['isDefaultValueConstant']);
    }

    /**
     * Build a minimal class definition for a modifier test.
     *
     * @param string            $name    Class name
     * @param array<string,int> $methods Method names and their parameter counts
     *
     * @return array<string,mixed>
     */
    private function classDef($name, array $methods)
    {
        $classDef = array('name' => $name, 'methods' => array());
        foreach ($methods as $method => $parameterCount) {
            $parameters = array();
            for ($i = 0; $i < $parameterCount; $i++) {
                $parameters[] = array(
					'isDefaultValueAvailable' => false,
					'isVariadic' => false,
					'name' => 'parameter' . $i,
                );
            }
            $classDef['methods'][$method] = array('parameters' => $parameters);
        }
        return $classDef;
    }
}

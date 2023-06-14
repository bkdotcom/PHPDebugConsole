<?php

namespace bdk\Test\Teams;

use bdk\Test\PolyFill\ExpectExceptionTrait;
use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 *
 */
abstract class AbstractTestCaseWith extends TestCase
{
    use ExpectExceptionTrait;

    protected static $testedSchemaProps = array();
    protected static $testedWithMethods = array();
    protected static $definitions = array();
    protected static $schema = array();

    protected static $unsupportedAttributes = array();
    protected static $withMethods = array();

    public static function setUpBeforeClass(): void
    {
        self::$testedSchemaProps = array();
        self::$testedWithMethods = array();
        self::setDefinitions();
    }

    public static function tearDownAfterClass(): void
    {
        self::assertAllWithMethodsTested();
    }

    /**
     * @dataProvider providerSchemaProps
     */
    public function testSchemaProps($name, $val, $isException = false)
    {
        if ($name === true) {
            self::assertTrue(true);
            return;
        }
        $method = isset(static::$withMethods[$name])
            ? static::$withMethods[$name]
            : 'with' . \ucfirst($name);
        self::$testedWithMethods[$method] = true;
        $obj = static::itemFactory();
        if ($isException) {
            $this->expectException('InvalidArgumentException');
        }
        $objNew = \call_user_func(array($obj, $method), $val);
        self::assertNotSame($obj, $objNew);
        self::assertInstanceOf(\get_class($obj), $objNew);
        self::assertSame($val, $objNew->get($name));
    }

    /**
     * @dataProvider providerWith
     */
    public function testWith($method, $args, $isException = false, $exceptionMessage = null, $callable = null)
    {
        self::$testedWithMethods[$method] = true;
        $obj = static::itemFactory();
        if ($isException) {
            $this->expectException('InvalidArgumentException');
        }
        if ($exceptionMessage !== null) {
            $this->expectExceptionMessage($exceptionMessage);
        }
        $objNew = \call_user_func_array(array($obj, $method), $args);
        self::assertNotSame($obj, $objNew);
        self::assertInstanceOf(\get_class($obj), $objNew);
        if (\is_callable($callable)) {
            \call_user_func($callable, $objNew);
        }
    }

    /**
     * @dataProvider providerGet
     */
    public function testGet($prop, $expect = null)
    {
        $obj = static::itemFactory();
        if ($expect === 'OutOfBoundsException') {
            $this->expectException('OutOfBoundsException');
            $obj->get($prop);
        }
        self::assertNotSame('never this', $obj->get($prop));
    }

    public static function providerGet()
    {
        self::setdefinitions();
        $obj = static::itemFactory();
        $objType = $obj->get('type');

        $tests = array();
        $tests['exception'] = array('noSuchProp', 'OutOfBoundsException');

        if (empty($objType)) {
            // \bdk\Test\Debug\Helper::stderr(\get_class($obj) . ' empty type');
            // self::expectNotToPerformAssertions();
            return $tests;
        }

        $props = \array_keys(self::$definitions[$objType]);
        $props = \array_diff($props, \array_keys(self::$testedSchemaProps), static::$unsupportedAttributes ?: array());
        foreach ($props as $prop) {
            $tests[$prop] = array($prop);
        }
        return $tests;
    }

    public static function providerWith()
    {
        $keys = array();
        $tests = array();
        $withCases = static::withTestCases();
        foreach ($withCases as $vals) {
            $vals = \array_replace(array(
                '',
                array(),
                false,
                null,
                null,
            ), $vals);
            $prop = $vals[0];
            $expectException = $vals[2];
            $method = 'with' . \ucfirst($prop);
            $key = $prop . ($expectException ? ' (w/ exception)' : '');
            $keys[$key] = isset($keys[$key])
                ? $keys[$key] + 1
                : 1;
            $key = $keys[$key] > 1
                ? $key . ' ' . $keys[$key]
                : $key;
            $vals[0] = $method;
            $tests[$key] = $vals;
        }
        return $tests;
    }

    /**
     * This could definitely use some optimization
     */
    public static function providerSchemaProps()
    {
        self::setdefinitions();
        $obj = static::itemFactory();
        $type = $obj->get('type');
        if ($type === null) {
            return array(
                array(true, true),
            );
        }
        $tests = array();
        foreach (self::$definitions[$type] as $name => $info) {
            if ($name === 'type' || \in_array($name, static::$unsupportedAttributes, true)) {
                continue;
            }
            // \bdk\Test\Debug\Helper::stderr($name, $info);
            if (\in_array($info['type'], array('array','object'), true)) {
                $tests[$name . ' empty array'] = array($name, array(), $info['isRequired']);
            } else {
                $tests[$name . ' null'] = array($name, null, $info['isRequired']);
            }
            if (isset($info['type']) && $info['type'] === 'boolean') {
                self::$testedSchemaProps[$name] = true;
                $tests[$name . ' true'] = array($name, true);
                $tests[$name . ' false'] = array($name, false);
                $tests[$name . ' exception'] = array($name, 'foo', true);
                continue;
            }
            if (isset($info['type']) && $info['type'] === 'string') {
                if (\in_array($name, array('text','title','subtitle'), true)) {
                    self::$testedSchemaProps[$name] = true;
                    $tests[$name . ' string'] = array($name, 'some string');
                    $tests[$name . ' exception'] = array($name, array('nope'), true);
                    continue;
                }
                if (isset($info['format']) && $info['format'] === 'uri-reference') {
                    self::$testedSchemaProps[$name] = true;
                    $tests[$name . ' url'] = array($name, 'http://example.com/');
                    $tests[$name . ' exception'] = array($name, '/cat.jpg', true);
                    $tests[$name . ' type exception'] = array($name, false, true);
                    continue;
                }
            }
            // \bdk\Test\Debug\Helper::stderr($name, $info);
            if (isset($info['enum'])) {
                self::$testedSchemaProps[$name] = true;
                foreach ($info['enum'] as $val) {
                    $tests[$name . ' ' . $val] = array($name, $val);
                }
                $tests[$name . ' exception'] = array($name, 'foo', true);
                continue;
            }
            if (isset($info['anyOf'])) {
                foreach ($info['anyOf'] as $of) {
                    if (isset($of['type']) && $of['type'] === 'boolean') {
                        self::$testedSchemaProps[$name] = true;
                        $tests[$name . ' true'] = array($name, true);
                        $tests[$name . ' false'] = array($name, false);
                        $tests[$name . ' exception'] = array($name, 'foo', true);
                        continue;
                    }
                    if (isset($of['type']) && $of['type'] === 'string') {
                        if (isset($of['format']) && $of['format'] === 'uri-reference') {
                            self::$testedSchemaProps[$name] = true;
                            $tests[$name . ' url'] = array($name, 'http://example.com/');
                            $tests[$name . ' type exception'] = array($name, false, true);
                            $tests[$name . ' exception'] = array($name, '/cat.jpg', true);
                        }
                    }
                    if (isset($of['$ref'])) {
                        $ref = \str_replace('#/definitions/', '', $of['$ref']);
                        if (\strpos($ref, 'ImplementationsOf.') === 0) {
                            continue;
                        }
                        // \bdk\Test\Debug\Helper::stderr($name . ' anyof ref ' . $ref, self::$definitions[$ref]);
                        if (isset(self::$definitions[$ref]['anyOf']) === false) {
                            continue;
                        }
                        foreach (self::$definitions[$ref]['anyOf'] as $of) {
                            if (isset($of['enum'])) {
                                self::$testedSchemaProps[$name] = true;
                                foreach ($of['enum'] as $val) {
                                    $tests[$name . ' ' . $val] = array($name, $val);
                                }
                                $tests[$name . ' exception'] = array($name, 'foo', true);
                                continue;
                            }
                        }
                    }
                }
                continue;
            } // anyOf
            if (isset($info['$ref'])) {
                $ref = \str_replace('#/definitions/', '', $info['$ref']);
                if (\strpos($ref, 'ImplementationsOf.') === 0) {
                    continue;
                }
                if (isset(self::$definitions[$ref]['anyOf']) === false) {
                    continue;
                }
                // \bdk\Test\Debug\Helper::stderr($name, 'ref', $info['$ref'], self::$definitions[$ref]);
                foreach (self::$definitions[$ref]['anyOf'] as $of) {
                    if (isset($of['enum'])) {
                        self::$testedSchemaProps[$name] = true;
                        foreach ($of['enum'] as $val) {
                            $tests[$name . ' ' . $val] = array($name, $val);
                        }
                        $tests[$name . ' exception'] = array($name, 'foo', true);
                    }
                }
                continue;
            } // $ref
        }
        if (empty(self::$testedSchemaProps)) {
            // provide at least one test
            $tests[] = array(true, true);
        }
        return $tests;
    }

    /**
     * Build object on which test will be performed`
     *
     * @return object
     */
    abstract protected static function itemFactory();

    /**
     * Return test cases for provider.
     *
     * @return array
     */
    abstract protected static function withTestCases();

    protected static function assertAllWithMethodsTested()
    {
        $obj = static::itemFactory();
        $type = $obj->get('type');
        if (empty($type)) {
            return;
        }
        $expectMethods = \array_map(static function ($prop) {
            if ($prop === 'type') {
                return null;
            }
            if (\in_array($prop, static::$unsupportedAttributes, true)) {
                return null;
            }
            return isset(static::$withMethods[$prop])
                ? static::$withMethods[$prop]
                : 'with' . \ucfirst($prop);
        }, \array_keys(self::$definitions[$type]));
        // $expectMethods = \array_diff($expectMethods, ['withType']);
        $expectMethods = \array_filter($expectMethods);

        $reflectionClass = new ReflectionClass($obj);
        $className = $reflectionClass->getName();
        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
        $inheritedMethods = \array_filter($methods, static function (ReflectionMethod $refMethod) use ($className) {
            $name = $refMethod->getName();
            if (\strpos($name, 'with') !== 0) {
                return false;
            }
            $declaringClassName = $refMethod->getDeclaringClass()->getName();
            return $declaringClassName !== $className;
        });
        $inheritedMethods = \array_map(static function (ReflectionMethod $refMethod) {
            return $refMethod->getName();
        }, $inheritedMethods);

        $methodsNotTested = \array_values(\array_diff($expectMethods, $inheritedMethods, \array_keys(self::$testedWithMethods)));

        if ($methodsNotTested) {
            throw new Exception(\sprintf(
                '%s: %s method(s) not tested: %s',
                $className,
                \count($methodsNotTested),
                \json_encode($methodsNotTested)
            ));
        }
    }

    private static function setDefinitions()
    {
        if (self::$definitions) {
            // already set
            return;
        }
        self::$schema = \json_decode(\file_get_contents(__DIR__ . '/adaptive-card.json'), true);
        $props = array();
        foreach (self::$schema['definitions'] as $name => $def) {
            if (\strpos($name, 'ImplementationsOf.') === 0) {
                continue;
            }
            if (\strpos($name, 'Extendable.') === 0) {
                continue;
            }
            if (isset($def['properties'])) {
                $props[$name] = self::getPropsFromSchema($def, $name);
                continue;
            }
            if (isset($def['anyOf'])) {
                $other = array();
                foreach ($def['anyOf'] as $type) {
                    if (isset($type['type']) && $type['type'] === 'string') {
                        continue;
                    }
                    $other[] = $type;
                }
                if (\count($other) === 1 && $other[0]['type'] === 'object') {
                    $props[$name] = self::getPropsFromSchema($other[0], $name);
                    continue;
                }
            }
            // probably enum definition
            $props[$name] = $def;
        }
        \ksort($props);
        // \bdk\Test\Debug\Helper::stderr('Image', $props['Image']);
        // \bdk\Test\Debug\Helper::stderr('definitions', $props);
        self::$definitions = $props;
        self::$schema = null;
    }

    private static function getPropsFromSchema($def, $name)
    {
        // \bdk\Test\Debug\Helper::stderr($name, $def);
        $props = $def['properties'];
        if (isset($def['allOf'])) {
            $props = \array_filter($props, static function ($val) {
                return $val !== array();
            });
            foreach ($def['allOf'] as $allOf) {
                $name = \str_replace('#/definitions/', '', $allOf['$ref']);
                $defInherited = self::$schema['definitions'][$name];
                $propsInherited = self::getPropsFromSchema($defInherited, $name);
                $props = \array_merge($propsInherited, $props);
            }
        }
        foreach (\array_keys($props) as $name) {
            $isRequired = isset($def['required']) && \in_array($name, $def['required'], true);
            $props[$name]['isRequired'] = $isRequired;
            // \bdk\Test\Debug\Helper::stderr($name, 'isRequired', $props[$name]['isRequired']);
        }
        \ksort($props);
        return $props;
    }
}

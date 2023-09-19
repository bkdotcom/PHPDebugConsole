<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\Php;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\Test\Debug\Fixture\TestObj;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Utility class
 *
 * @covers \bdk\Debug\Utility\Php
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class PhpTest extends TestCase
{
    use AssertionTrait;

    /**
     * @param string $input
     * @param string $expect
     *
     * @dataProvider providerFriendlyClassName
     */
    public function testFriendlyClassName($input, $expect)
    {
        self::assertSame($expect, Php::friendlyClassName($input));
    }

    /**
     * @dataProvider providerGetDebugType
     */
    public function testGetDebugType($val, $expectedType)
    {
        $type = Php::getDebugType($val);
        self::assertSame($expectedType, $type);
    }

    public function testGetIncludedFiles()
    {
        $filesA = \get_included_files();
        $filesB = Php::getIncludedFiles();
        \sort($filesA);
        \sort($filesB);
        self::assertArraySubset($filesA, $filesB);
    }

    /**
     * @dataProvider providerIsCallable
     */
    public function testIsCallable($input, $flags, $isCallable)
    {
        self::assertSame(
            $isCallable,
            $flags !== null
                ? Php::isCallable($input, $flags)
                : Php::isCallable($input)
        );
    }

    public function testIsThrowable()
    {
        self::assertTrue(Php::isThrowable(new \Exception('thrown')));
        self::assertFalse(Php::isThrowable((object) array('stdObj')));
    }

    /**
     * Test
     *
     * @return void
     *
     * @todo better test
     */
    public function testMemoryLimit()
    {
        self::assertNotNull(Php::memoryLimit());
    }

    public function testUnserializeSafe()
    {
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $serialized = \serialize(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'obj' => new \bdk\Test\Debug\Fixture\TestTraversable(array('foo' => 'bar')),
            'after' => 'bar',
        ));

        // allow everything
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertEquals(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'obj' => new \bdk\Test\Debug\Fixture\TestTraversable(array('foo' => 'bar')),
            'after' => 'bar',
        ), Php::unserializeSafe($serialized, true));

        // disable all (stdClass still allowed)
        $serialized = 'a:5:{s:6:"before";s:3:"foo";s:8:"stdClass";O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}s:12:"serializable";C:35:"bdk\Test\Debug\Fixture\Serializable":13:{Brad was here}s:3:"obj";O:38:"bdk\Test\Debug\Fixture\TestTraversable":1:{s:4:"data";a:1:{s:3:"foo";s:3:"bar";}}s:5:"after";s:3:"bar";}';
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertEquals(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'serializable' => \unserialize('O:22:"__PHP_Incomplete_Class":2:{s:27:"__PHP_Incomplete_Class_Name";s:35:"bdk\Test\Debug\Fixture\Serializable";s:17:"__serialized_data";s:13:"Brad was here";}'),
            'obj' => \unserialize('O:22:"__PHP_Incomplete_Class":2:{s:27:"__PHP_Incomplete_Class_Name";s:38:"bdk\Test\Debug\Fixture\TestTraversable";s:4:"data";a:1:{s:3:"foo";s:3:"bar";}}'),
            'after' => 'bar',
        ), Php::unserializeSafe($serialized, false));

        // no Serializable (vanila unserialize will be used
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $serialized = \serialize(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'after' => 'bar',
        ));
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertEquals(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'after' => 'bar',
        ), Php::unserializeSafe($serialized, false));
    }

    public static function providerFriendlyClassName()
    {
        $fcnExpect = 'bdk\Test\Debug\Fixture\TestObj';
        $obj = new TestObj();
        $strClassname = 'bdk\Test\Debug\Fixture\TestObj';
        $tests = array(
            'obj' => array($obj, $fcnExpect),
            'reflectionClass' => array(new \ReflectionClass($strClassname), $fcnExpect),
            'reflectionObject' => array(new \ReflectionObject($obj), $fcnExpect),
            'strClassname' => array($strClassname, $fcnExpect),
            'strMethod' => array('\bdk\Test\Debug\Fixture\TestObj::methodPublic()', $fcnExpect),
            'strProperty' => array('\bdk\Test\Debug\Fixture\TestObj::$someArray', $fcnExpect),
        );
        if (PHP_VERSION_ID >= 70000) {
            $anonymous = require TEST_DIR . '/Debug/Fixture/Anonymous.php';
            $tests = \array_merge($tests, array(
                'anonymous' => array($anonymous['anonymous'], 'class@anonymous'),
                'anonymousExtends' => array($anonymous['stdClass'], 'stdClass@anonymous'),
                'anonymousImplements' => array($anonymous['implements'], 'IteratorAggregate@anonymous'),
            ));
        }
        if (PHP_VERSION_ID >= 70100) {
            $tests['strConstant'] = array('\bdk\Test\Debug\Fixture\TestObj::MY_CONSTANT', $fcnExpect);
        }
        if (PHP_VERSION_ID >= 80100) {
            $tests = \array_merge($tests, array(
                'enum' => array(\bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST, 'bdk\Test\Debug\Fixture\Enum\Meals'),
                'enum.backed' => array(\bdk\Test\Debug\Fixture\Enum\MealsBacked::BREAKFAST, 'bdk\Test\Debug\Fixture\Enum\MealsBacked'),
            ));
        }
        return $tests;
    }

    public static function providerGetDebugType()
    {
        $callbackFunc = \ini_set('unserialize_callback_func', null);
        $incompleteClass = \unserialize('O:8:"Foo\Buzz":0:{}');
        \ini_set('unserialize_callback_func', $callbackFunc);

        $fh = \fopen(__FILE__, 'r');
        \fclose($fh);

        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $tests = array(
            'object' => array(new \stdClass(), 'stdClass'),
            'object.closure' => array(function () {}, 'Closure'),
            'string' => array('foo', 'string'),
            'false' => array(false, 'bool'),
            'true' => array(true, 'bool'),
            'null' => array(null, 'null'),
            'array' => array(array(), 'array'),
            'array.callable' => array(array(new TestObj(), 'methodPublic'), 'callable'),
            'int' => array(42, 'int'),
            'float' => array(3.14, 'float'),
            'stream' => array(\fopen(__FILE__, 'r'), 'resource (stream)'),
            'closed resource' => array($fh, 'resource (closed)'),
            '__PHP_Incomplete_Class' => array($incompleteClass, '__PHP_Incomplete_Class'),
        );
        if (PHP_VERSION_ID >= 70000) {
            $tests = \array_merge($tests, array(
                'anon' => array(eval('return new class() {};'), 'class@anonymous'),
                'anonExtends' => array(eval('return new class() extends stdClass {};'), 'stdClass@anonymous'),
                'anonImplements' => array(eval('return new class() implements Reflector { function __toString() {} public static function export() {} };'), 'Reflector@anonymous'),
            ));
        }
        if (PHP_VERSION_ID >= 80100) {
            $tests = \array_merge($tests, array(
                'enum' => array(\bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST, 'bdk\Test\Debug\Fixture\Enum\Meals::BREAKFAST'),
                'enum.backed' => array(\bdk\Test\Debug\Fixture\Enum\MealsBacked::BREAKFAST, 'bdk\Test\Debug\Fixture\Enum\MealsBacked::BREAKFAST'),
            ));
        }
        return $tests;
    }

    public static function providerIsCallable()
    {
        $closure = static function ($foo) {
            echo $foo;
        };
        $invokable = new \bdk\Test\Container\Fixture\Invokable();
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $return = array(
            // closure
            'closure' => array($closure, null, true),

            // invokable
            'invokable' => array($invokable, null, true),

            // function
            'func' => array('header', null, true),
            'funcArrayOnly' => array('header', Php::IS_CALLABLE_ARRAY_ONLY, false),
            'funcNamespaceArrayOnly' => array('\bdk\Debug\Utility\header', Php::IS_CALLABLE_ARRAY_ONLY, false),
            'funcNamespace' => array('\bdk\Debug\header', null, true),
            'funcNamespaceNoSunchSyntaxOnly' => array('bogus\wompwomp', Php::IS_CALLABLE_SYNTAX_ONLY, true),
            'funcNoSuch' => array('wompwomp', null, false),
            'funcNoSunchSyntaxOnly' => array('wompwomp', Php::IS_CALLABLE_SYNTAX_ONLY, false),

            // string
            'static' => array('\bdk\Debug\Utility\Php::isCallable', null, true),
            'staticNoSuch' => array('\notAClass::method', null, false),
            'staticObjOnly' => array('\bdk\Debug\Php::isCallable', Php::IS_CALLABLE_OBJ_ONLY, false),
            'staticSyntaxOnly' => array('bogus::wompwomp', Php::IS_CALLABLE_SYNTAX_ONLY, true),

            // array - invalid
            'arrayEmpty' => array(array(), null, false),

            // array - classname
            'class' => array(array('\bdk\Debug\Utility\Php','isCallable'), null, true),
            'classObjOnly' => array(array('\bdk\Debug\Utility\Php','isCallable'), Php::IS_CALLABLE_OBJ_ONLY, false),
            'classInvalid' => array(array('some string', 'string'), Php::IS_CALLABLE_SYNTAX_ONLY, false),
            'classSyntaxOnly' => array(array('could\\be\\class', 'string'), Php::IS_CALLABLE_SYNTAX_ONLY, true),
            'classNoSuch' => array(array('could\\be\\class', 'string'), null, false),
            'classNoSuchMethod' => array(array('\bdk\Debug\Utility\Php', 'noSuchMethod'), null, false),
            'classNoSunchMethodHasCallStatic' => array(array('\bdk\Test\Container\Fixture\Invokable', 'noSuchMethod'), null, true),
            'classNoSuchMethodNoCall' => array(array('\bdk\Test\Container\Fixture\Invokable', 'noSuchMethod'), Php::IS_CALLABLE_NO_CALL, false),

            // array - object
            'object' => array(array(new Php(), 'isCallable'), null, true),
            'objectSyntaxOnly' => array(array($invokable, 'noSuchMethod'), Php::IS_CALLABLE_SYNTAX_ONLY, true),
            'objectNoSuchMethod' => array(array($invokable, 'noSuchMethod'), null, true),
            'objectNoSuchMethodNoCall' => array(array($invokable, 'noSuchMethod'), Php::IS_CALLABLE_NO_CALL, false),
            'objectInvalidMethodToken' => array(array($invokable, 'some string'), null, false),
        );
        if (PHP_VERSION_ID >= 80100) {
            $callables = require __DIR__ . '/firstClassCallable.php';
            foreach ($callables as $k => $callable) {
                // $this->assertTrue(Php::isCallable($callable), $k . ' first class callable syntax');
                $return['fcc_' . $k] = array($callable, null, true);
            }
        }
        return $return;
    }
}

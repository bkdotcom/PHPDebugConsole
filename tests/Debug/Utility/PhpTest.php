<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\Php;
use bdk\Test\Debug\Fixture\TestObj;
use bdk\Test\PolyFill\AssertionTrait;
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
        $serialized = \serialize(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'obj' => new \bdk\Test\Debug\Fixture\TestTraversable(array('foo' => 'bar')),
            'after' => 'bar',
        ));

        // allow everything
        self::assertEquals(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'obj' => new \bdk\Test\Debug\Fixture\TestTraversable(array('foo' => 'bar')),
            'after' => 'bar',
        ), Php::unserializeSafe($serialized, true));

        // disable all (stdClass still allowed)
        $serialized = 'a:5:{s:6:"before";s:3:"foo";s:8:"stdClass";O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}s:12:"serializable";C:35:"bdk\Test\Debug\Fixture\Serializable":13:{Brad was here}s:3:"obj";O:38:"bdk\Test\Debug\Fixture\TestTraversable":1:{s:4:"data";a:1:{s:3:"foo";s:3:"bar";}}s:5:"after";s:3:"bar";}';
        self::assertEquals(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'serializable' => \unserialize('O:22:"__PHP_Incomplete_Class":2:{s:27:"__PHP_Incomplete_Class_Name";s:35:"bdk\Test\Debug\Fixture\Serializable";s:17:"__serialized_data";s:13:"Brad was here";}'),
            'obj' => \unserialize('O:22:"__PHP_Incomplete_Class":2:{s:27:"__PHP_Incomplete_Class_Name";s:38:"bdk\Test\Debug\Fixture\TestTraversable";s:4:"data";a:1:{s:3:"foo";s:3:"bar";}}'),
            'after' => 'bar',
        ), Php::unserializeSafe($serialized, false));

        // no Serializable (vanila unserialize will be used
        $serialized = \serialize(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'after' => 'bar',
        ));
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
        $return = array(
            'obj' => array($obj, $fcnExpect),
            'strClassname' => array($strClassname, $fcnExpect),
            'strProperty' => array('\bdk\Test\Debug\Fixture\TestObj::$someArray', $fcnExpect),
            'strMethod' => array('\bdk\Test\Debug\Fixture\TestObj::methodPublic()', $fcnExpect),
            'reflectionClass' => array(new \ReflectionClass($strClassname), $fcnExpect),
            'reflectionObject' => array(new \ReflectionObject($obj), $fcnExpect),
        );
        if (PHP_VERSION_ID < 70000) {
            return $return;
        }
        $anonymous = require TEST_DIR . '/Debug/Fixture/Anonymous.php';
        $return = \array_merge($return, array(
            'anonymous' => array($anonymous['anonymous'], 'class@anonymous'),
            'anonymousExtends' => array($anonymous['stdClass'], 'stdClass@anonymous'),
            'anonymousImplements' => array($anonymous['implements'], 'IteratorAggregate@anonymous'),
        ));
        if (PHP_VERSION_ID < 70100) {
            return $return;
        }
        $return['strConstant'] = array('\bdk\Test\Debug\Fixture\TestObj::MY_CONSTANT', $fcnExpect);
        return $return;
    }

    public static function providerIsCallable()
    {
        $closure = static function ($foo) {
            echo $foo;
        };
        $invokable = new \bdk\Test\Container\Fixture\Invokable();
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

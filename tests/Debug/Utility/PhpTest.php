<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\Php;
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
     * [testFriendlyClassName description]
     *
     * @dataProvider providerFriendlyClassName
     */
    public function testFriendlyClassName($input, $expect)
    {
        $this->assertSame($expect, Php::friendlyClassName($input));
    }

    public function testGetIncludedFiles()
    {
        $filesA = \get_included_files();
        $filesB = Php::getIncludedFiles();
        \sort($filesA);
        \sort($filesB);
        $this->assertArraySubset($filesA, $filesB);
    }

    public function testGetReflector()
    {
        $this->assertNull(Php::getReflector(123));

        $this->assertNull(Php::getReflector('food()'));

        $strClassname = 'bdk\Test\Debug\Fixture\Test';
        $this->assertInstanceOf('ReflectionClass', Php::getReflector($strClassname));

        $str = '\bdk\Test\Debug\Fixture\Test::$someArray';
        $this->assertInstanceOf('ReflectionProperty', Php::getReflector($str));

        $str = '\bdk\Test\Debug\Fixture\Test::methodPublic()';
        $this->assertInstanceOf('ReflectionMethod', Php::getReflector($str));

        if (PHP_VERSION_ID < 70000) {
            return;
        }

        $str = '\bdk\Test\Debug\Fixture\Test::MY_CONSTANT';
        $this->assertInstanceOf('ReflectionClassConstant', Php::getReflector($str));
    }

    /**
     * [testIsCallable description]
     *
     * @dataProvider providerIsCallable
     */
    public function testIsCallable($input, $flags, $isCallable)
    {
        $this->assertSame(
            $isCallable,
            $flags !== null
                ? Php::isCallable($input, $flags)
                : Php::isCallable($input)
        );
    }

    public function testIsThrowable()
    {
        $this->assertTrue(Php::isThrowable(new \Exception('thrown')));
        $this->assertFalse(Php::isThrowable((object) array('stdObj')));
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
        $this->assertNotNull(Php::memoryLimit());
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
        $this->assertEquals(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'obj' => new \bdk\Test\Debug\Fixture\TestTraversable(array('foo' => 'bar')),
            'after' => 'bar',
        ), Php::unserializeSafe($serialized, true));

        // disable all (stdClass still allowed)
        $serialized = 'a:5:{s:6:"before";s:3:"foo";s:8:"stdClass";O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}s:12:"serializable";C:35:"bdk\Test\Debug\Fixture\Serializable":13:{Brad was here}s:3:"obj";O:38:"bdk\Test\Debug\Fixture\TestTraversable":1:{s:4:"data";a:1:{s:3:"foo";s:3:"bar";}}s:5:"after";s:3:"bar";}';
        $this->assertEquals(array(
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
        $this->assertEquals(array(
            'before' => 'foo',
            'stdClass' => (object) array('foo' => 'bar'),
            'after' => 'bar',
        ), Php::unserializeSafe($serialized, false));
    }

    public function providerFriendlyClassName()
    {
        $fcnExpect = 'bdk\Test\Debug\Fixture\Test';
        $obj = new \bdk\Test\Debug\Fixture\Test();
        $strClassname = 'bdk\Test\Debug\Fixture\Test';
        $return = array(
            'obj' => array($obj, $fcnExpect),
            'strClassname' => array($strClassname, $fcnExpect),
            'strProperty' => array('\bdk\Test\Debug\Fixture\Test::$someArray', $fcnExpect),
            'strMethod' => array('\bdk\Test\Debug\Fixture\Test::methodPublic()', $fcnExpect),
            'reflectionClass' => array(new \ReflectionClass($strClassname), $fcnExpect),
            'reflectionObject' => array(new \ReflectionObject($obj), $fcnExpect),
        );
        if (PHP_VERSION_ID < 70000) {
            return $return;
        }
        $anonymous = require TEST_DIR . '/Debug/Fixture/Anonymous.php';
        return \array_merge($return, array(
            'strConstant' => array('\bdk\Test\Debug\Fixture\Test::MY_CONSTANT', $fcnExpect),
            'anonymous' => array($anonymous['anonymous'], 'class@anonymous'),
            'anonymousExtends' => array($anonymous['stdClass'], 'stdClass@anonymous'),
            'anonymousImplements' => array($anonymous['implements'], 'IteratorAggregate@anonymous'),
        ));
        return $return;
    }

    public function providerIsCallable()
    {
        $closure = function ($foo) {
            echo $foo;
        };
        $invokable = new \bdk\Test\Container\Fixture\Invokable();
        $return = array(
            // These all fail because by IS_CALLABLE_ARRAY_ONLY is used by default
            'defaultFlagsFunc' => array('header', null, false),
            'defaultFlagsfuncNs' => array('\bdk\Debug\Utility\header', null, false),
            'defaultFlagsMethod' => array('\bdk\Debug\Php::isCallable', null, false),
            // IS_CALLABLE_OBJ_ONLY is used by default
            'defaultFlagsArrayClassname' => array(array('\bdk\Debug\Utility\Php','isCallable'), null, false),
            'defaultFlagsArrayObj' => array(array(new Php(), 'isCallable'), null, true),
            'defaultFlagsArrayEmpty' => array(array(), null, false),
            // Test that flags don't apply for Closure and invokable
            'defaultFlagsClosure' => array($closure, null, true),
            'defaultFlagsInvokable' => array($invokable, null, true),

            // disable IS_CALLABLE_OBJ_ONLY
            'arrayOnlyArrayClassname' => array(array('\bdk\Debug\Utility\Php','isCallable'), Php::IS_CALLABLE_ARRAY_ONLY, true),

            // Disable IS_CALLABLE_ARRAY_ONLY and they succeed
            'noOnlyFunc' => array('header', 0, true),
            'noOnlyFuncBogus' => array('wompwomp', 0, false),
            'noOnlyFuncNs' => array('\bdk\Debug\header', 0, true),
            'noOnlyMethod' => array('\bdk\Debug\Utility\Php::isCallable', 0, true),

            // Test that IS_CALLABLE_SYNTAX_ONLY doesn't work on non-namespaced string
            'syntaxOnlyFunc' => array('wompwomp', Php::IS_CALLABLE_SYNTAX_ONLY, false),
            // But syntax only does work here
            'syntaxOnlyFuncNs' => array('bogus\wompwomp', Php::IS_CALLABLE_SYNTAX_ONLY, true),
            'syntaxOnlyMethod' => array('bogus::wompwomp', Php::IS_CALLABLE_SYNTAX_ONLY, true),
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

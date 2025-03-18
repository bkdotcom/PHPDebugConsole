<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\Php;
use bdk\Debug\Utility\PhpType;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Test\Debug\DebugTestFramework;
use bdk\Test\Debug\Fixture\TestObj;

/**
 * PHPUnit tests for Utility class
 *
 * @covers \bdk\Debug\Utility\Php
 * @covers \bdk\Debug\Utility\PhpType
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class PhpTypeTest extends DebugTestFramework
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    /**
     * @param mixed       $value
     * @param string      $type
     * @param string|null $paramName
     * @param string|null $exceptionMessage
     *
     * @dataProvider providerAssertType
     */
    public function testAssertType($value, $type, $paramName = null, $exceptionMessage = null)
    {
        if ($exceptionMessage !== null) {
            $this->expectException('InvalidArgumentException');
            $this->expectExceptionMessage($exceptionMessage);
        }
        PhpType::assertType($value, $type, $paramName);
        self::assertTrue(true);
    }

    /**
     * @dataProvider providerGetDebugType
     */
    public function testGetDebugType($val, $expectedType)
    {
        $type = Php::getDebugType($val);
        self::assertSame($expectedType, $type);
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

    public function providerAssertType()
    {
        $method = __CLASS__ . '::testAssertType()';
        return [
            [array(), 'array'],
            ['call_user_func', 'callable'],
            [(object) array(), 'object'],
            [new \bdk\PubSub\Event(), 'bdk\PubSub\Event'],

            [array(), 'array|null'],
            ['call_user_func', 'callable'],
            [(object) array(), 'object|null'],
            [new \bdk\PubSub\Event(), 'bdk\PubSub\Event|null'],

            [null, 'array|null'],
            [null, 'callable|null'],
            [null, 'object|null'],
            [null, 'bdk\PubSub\Event|null'],

            [null, 'array', 'dingus', $method . ': $dingus expects array.  null provided'],
            [null, 'callable', null, $method . ' expects callable.  null provided'],
            [null, 'object', null, $method . ' expects object.  null provided'],
            [null, 'bdk\PubSub\Event', null, $method . ' expects bdk\PubSub\Event.  null provided'],

            [false, 'array|null', null, $method . ' expects array|null.  bool provided'],
            [false, 'callable|null', 'dingus', $method . ': $dingus expects callable|null.  bool provided'],
            [false, 'object|null', null, $method . ' expects object|null.  bool provided'],
            [false, 'bdk\PubSub\Event|null', null, $method . ' expects bdk\PubSub\Event|null.  bool provided'],

            [false, 'array', null, $method . ' expects array.  bool provided'],
            [false, 'callable', null, $method . ' expects callable.  bool provided'],
            [false, 'object', 'dingus', $method . ': $dingus expects object.  bool provided'],
            [false, 'bdk\PubSub\Event', null, $method . ' expects bdk\PubSub\Event.  bool provided'],
        ];
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
            'object.closure' => array(static function () {}, 'Closure'),
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

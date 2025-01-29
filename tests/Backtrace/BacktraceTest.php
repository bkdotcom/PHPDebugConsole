<?php

namespace bdk\Test\Backtrace;

use bdk\Backtrace;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\Test\Backtrace\Fixture\ChildObj;
use bdk\Test\Backtrace\Fixture\ParentObj;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Backtrace class
 *
 * @covers \bdk\Backtrace
 */
class BacktraceTest extends TestCase
{
    use AssertionTrait;

    protected static $line = 0;

    public function __call($method, $args)
    {
        if ($method === 'getCallerInfoHelper') {
            return $this->getCallerInfoHelper();
        }
    }

    public static function setUpBeforeClass(): void
    {
        $xdebugVer = \phpversion('xdebug');
        if (\version_compare($xdebugVer, '3.0.0', '<')) {
            \ini_set('xdebug.collect_params', '1');
        }
    }

    public function testAddInternalClass()
    {
        Backtrace::addInternalClass('hello');
        Backtrace::addInternalClass(array('world'));
        self::assertTrue(true); // simply testing that the above did not raise an error
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGet()
    {
        $line = __LINE__ + 1;
        $backtrace = Backtrace::get(null, 5);
        $haveArgs = false;
        foreach ($backtrace as $frame) {
            if (!empty($frame['args'])) {
                $haveArgs = true;
                break;
            }
        }
        self::assertCount(5, $backtrace);
        self::assertFalse($haveArgs);
        self::assertSame(__FILE__, $backtrace[0]['file']);
        self::assertSame($line, $backtrace[0]['line']);

        $line = __LINE__ + 1;
        $backtrace = Backtrace::get(Backtrace::INCL_ARGS, 5);
        $haveArgs = false;
        foreach ($backtrace as $frame) {
            if (!empty($frame['args'])) {
                $haveArgs = true;
                break;
            }
        }
        self::assertCount(5, $backtrace);
        self::assertTrue($haveArgs);
        self::assertSame(__FILE__, $backtrace[0]['file']);
        self::assertSame($line, $backtrace[0]['line']);
    }

    public function testGetFromException()
    {
        $line = __LINE__ + 1;
        $exception = new \Exception('this is a test');
        $backtrace = Backtrace::get(null, 3, $exception);
        self::assertCount(3, $backtrace);
        self::assertSame(__FILE__, $backtrace[0]['file']);
        self::assertSame($line, $backtrace[0]['line']);
    }

    public function testGetFromExceptionParseError()
    {
        if (\class_exists('ParseError') === false) {
            $this->markTestSkipped('ParseError class not available');
        }
        $exception = new \ParseError('parse error');
        $backtrace = Backtrace::get(null, 3, $exception);
        self::assertCount(0, $backtrace);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetCallerInfo()
    {
        $callerInfo = $this->getCallerInfoHelper();
        $line = __LINE__ - 1;
        $expect = array(
            'args' => array(),
            'class' => __CLASS__,
            'classCalled' => 'bdk\Test\Backtrace\BacktraceTest',
            'classContext' => 'bdk\Test\Backtrace\BacktraceTest',
            'evalLine' => null,
            'file' => __FILE__,
            'function' => __FUNCTION__,
            'line' => $line,
            'type' => '->',
        );
        self::assertSame($expect, $callerInfo);

        // @phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions
        $callerInfo = call_user_func(array($this, 'getCallerInfoHelper'));
        $line = __LINE__ - 1;
        self::assertSame(array(
            'args' => array(),
            'class' => __CLASS__,
            'classCalled' => 'bdk\Test\Backtrace\BacktraceTest',
            'classContext' => 'bdk\Test\Backtrace\BacktraceTest',
            'evalLine' => null,
            'file' => __FILE__,
            'function' => __FUNCTION__,
            'line' => $line,
            'type' => '->',
        ), $callerInfo);
    }

    public function testGetCallerInfoEval()
    {
        $callerInfo = $this->getCallerInfoEval();
        self::assertSame(array(
            'args' => array(),
            'class' => null,
            'classCalled' => null,
            'classContext' => null,
            'evalLine' => 1,
            'file' => __FILE__,
            'function' => 'eval',
            'line' => self::$line,
            'type' => null,
        ), $callerInfo);
    }

    public function testGetCallerInfoClassContext()
    {
        /*
        \bdk\Test\Backtrace\Fixture\ChildObj::methodStatic();
        $callerInfo = \bdk\Test\Backtrace\Fixture\ChildObj::$callerInfo;
        echo \print_r($callerInfo, true) . "\n";
        */

        $child = new ChildObj();
        $parent = new ParentObj();
        $childRef = new \ReflectionObject($child);
        $parentRef = new \ReflectionObject($parent);

        ChildObj::$callerInfoStack = array();
        $child->extendMe();
        $line = __LINE__ - 1;
        $callerInfoStack = ChildObj::$callerInfoStack;
        unset($callerInfoStack[1]['line'], $callerInfoStack[2]['line']);
        // echo 'callerInfoStack = ' . \print_r($callerInfoStack, true) . "\n";
        self::assertSame(array(
            array(
                'args' => array(),
                'class' => __CLASS__,
                'classCalled' => __CLASS__,
                'classContext' => __CLASS__,
                'evalLine' => null,
                'file' => __FILE__,
                'function' => __FUNCTION__,
                'line' => $line,
                'type' => '->',
            ),
            array(
                'args' => array(),
                'class' => \get_class($child),
                'classCalled' => \get_class($child),
                'classContext' => \get_class($child),
                'evalLine' => null,
                'file' => $childRef->getFileName(),
                'function' => 'extendMe',
                // 'line' => 10,
                'type' => '->',
            ),
            array(
                'args' => array(),
                'class' => \get_class($parent),
                'classCalled' => \get_class($parent),
                'classContext' => \get_class($child),
                'evalLine' => null,
                'file' => $parentRef->getFileName(),
                'function' => 'extendMe',
                // 'line' => 12
                'type' => '->',
            ),
        ), $callerInfoStack);

        /*
        \bdk\Test\Backtrace\Fixture\ChildObj::method2Static();
        $callerInfo = \bdk\Test\Backtrace\Fixture\ChildObj::$callerInfo;
        echo \print_r($callerInfo, true) . "\n";
        */

        ChildObj::$callerInfoStack = array();
        $child->inherited();
        $line = __LINE__ - 1;
        $callerInfoStack = ChildObj::$callerInfoStack;
        unset($callerInfoStack[1]['line']);
        // echo 'callerInfoStack = ' . \print_r($callerInfoStack, true) . "\n";
        self::assertSame(array(
            array(
                'args' => array(),
                'class' => __CLASS__,
                'classCalled' => __CLASS__,
                'classContext' => __CLASS__,
                'evalLine' => null,
                'file' => __FILE__,
                'function' => __FUNCTION__,
                'line' => $line,
                'type' => '->',
            ),
            array(
                'args' => array(),
                'class' => \get_class($parent),
                'classCalled' => \get_class($child),
                'classContext' => \get_class($child),
                'evalLine' => null,
                'file' => $parentRef->getFileName(),
                'function' => 'inherited',
                // 'line' => 10,
                'type' => '->',
            ),
        ), $callerInfoStack);
    }

    public function testGetRenameFunctions()
    {
        $magic = new Fixture\Magic();
        $magic->test();

        $trace = $magic->trace;

        self::assertSame('bdk\Test\Backtrace\Fixture\Magic->__call(\'test\')', $trace[1]['function']);
    }

    private function getCallerInfoEval()
    {
        $php = 'return \bdk\Test\Backtrace\BacktraceTest::getCallerInfoHelper();';
        self::$line = __LINE__ + 1;
        return eval($php);
    }

    public static function getCallerInfoHelper()
    {
        return Backtrace::getCallerInfo();
    }
}

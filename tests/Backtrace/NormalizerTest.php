<?php

namespace bdk\Test\Backtrace;

use bdk\Backtrace\Normalizer;
use bdk\Backtrace\Xdebug;
use bdk\PhpUnitPolyfill\AssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Backtrace\Normalizer
 */
class NormalizerTest extends TestCase
{
    use AssertionTrait;

    public function testNormalize()
    {
        func1();

        $trace = $GLOBALS['debug_backtrace'];
        $trace = Normalizer::normalize($trace);

        self::assertSame(__FILE__, $trace[0]['file']);
        self::assertSame('{closure}', $trace[0]['function']);

        self::assertSame(__FILE__, $trace[1]['file']);
        self::assertSame('bdk\Test\Backtrace\func2', $trace[1]['function']);
        self::assertSame(array(
            "they're",
            '"quotes"',
            42,
            null,
            true,
        ), $trace[1]['args']);

        self::assertSame(__FILE__, $trace[2]['file']);
        self::assertSame('bdk\Test\Backtrace\func1', $trace[2]['function']);

        self::assertSame(__CLASS__ . '->' . __FUNCTION__, $trace[3]['function']);

        $trace = \array_reverse($GLOBALS['xdebug_trace']);
        $trace = Normalizer::normalize($trace);

        self::assertSame(__FILE__, $trace[0]['file']);
        self::assertSame('bdk\\Backtrace\\Xdebug::getFunctionStack', $trace[0]['function']);

        self::assertSame(__FILE__, $trace[1]['file']);
        self::assertSame('{closure}', $trace[1]['function']);

        self::assertSame(__FILE__, $trace[2]['file']);
        self::assertSame('bdk\Test\Backtrace\func2', $trace[2]['function']);
        self::assertSame(array(
            "they're",
            '"quotes"',
            42,
            null,
            true,
        ), $trace[2]['args']);

        self::assertSame(__FILE__, $trace[3]['file']);
        self::assertSame('bdk\Test\Backtrace\func1', $trace[3]['function']);

        self::assertSame(__CLASS__ . '->' . __FUNCTION__, $trace[4]['function']);
    }

    public function testNormalizeInclude()
    {
        $filepath = __DIR__ . '/Fixture/include.php';

        require $filepath;

        $this->assertIncludeDebugBacktrace($filepath);
        $this->assertIncludeXdebug($filepath);
    }

    protected function assertIncludeDebugBacktrace($filepath)
    {
        $trace = $GLOBALS['debug_backtrace'];
        $trace = Normalizer::normalize($trace);

        self::assertSame($filepath, $trace[0]['file']);
        self::assertSame('{closure}', $trace[0]['function']);
        self::assertIsInt($trace[0]['evalLine']);

        self::assertSame($filepath, $trace[1]['file']);
        self::assertSame('func4', $trace[1]['function']);
        self::assertSame(array(
            "they're",
            '"quotes"',
            42,
            null,
            true,
        ), $trace[1]['args']);
        self::assertIsInt($trace[1]['evalLine']);

        self::assertSame($filepath, $trace[2]['file']);
        self::assertSame('func3', $trace[2]['function']);
        self::assertIsInt($trace[2]['evalLine']);

        self::assertSame($filepath, $trace[3]['file']);
        self::assertSame('eval', $trace[3]['function']);
        self::assertSame(array(), $trace[3]['args']); // debug_backtrace doesn't provide eval'd code

        self::assertSame(__FILE__, $trace[4]['file']);
        self::assertSame('require', $trace[4]['function']);
        self::assertSame($filepath, $trace[4]['args'][0]);
    }

    protected function assertIncludeXdebug($filepath)
    {
        $trace = \array_reverse($GLOBALS['xdebug_trace']);
        $trace = Normalizer::normalize($trace);

        var_dump($trace);

        self::assertSame($filepath, $trace[0]['file']);
        self::assertSame('bdk\\Backtrace\\Xdebug::getFunctionStack', $trace[0]['function']);

        self::assertSame($filepath, $trace[1]['file']);
        self::assertSame('{closure}', $trace[1]['function']);
        self::assertIsInt($trace[1]['evalLine']);

        self::assertSame($filepath, $trace[2]['file']);
        self::assertSame('func4', $trace[2]['function']);
        self::assertSame(array(
            "they're",
            '"quotes"',
            42,
            null,
            true,
        ), $trace[2]['args']);
        self::assertIsInt($trace[2]['evalLine']);

        self::assertSame($filepath, $trace[3]['file']);
        self::assertSame('func3', $trace[3]['function']);
        self::assertIsInt($trace[3]['evalLine']);

        self::assertSame($filepath, $trace[4]['file']);
        self::assertSame('eval', $trace[4]['function']);
        self::assertTrue(\strpos($trace[4]['args'][0], 'func3();') === 0);

        self::assertSame(__FILE__, $trace[5]['file']);
        self::assertSame('include or require', $trace[5]['function']);
        self::assertSame($filepath, $trace[5]['args'][0]);
    }
}

function func1()
{
    call_user_func_array('bdk\Test\Backtrace\func2', array("they're", '"quotes"', 42, null, true));
}

function func2()
{
    $closure = function () {
        $GLOBALS['xdebug_trace'] = Xdebug::getFunctionStack();
        $GLOBALS['debug_backtrace'] = \debug_backtrace();
    };
    $closure();
}

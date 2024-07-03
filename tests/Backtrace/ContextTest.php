<?php

namespace bdk\Test\Backtrace;

use bdk\Backtrace;
use bdk\Backtrace\Normalizer;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Backtrace class
 *
 * @covers \bdk\Backtrace
 * @covers \bdk\Backtrace\Context
 */
class ContextTest extends TestCase
{
    public function testAddContext()
    {
        $line1 = __LINE__ + 2;
        $closure = static function ($php) {
            eval($php);
        };
        $php = '
            $thing = new bdk\Test\Backtrace\Fixture\Thing2();
            $thing->a();
        ';
        $line2 = __LINE__ + 1;
        $closure($php);

        $trace = $GLOBALS['debug_backtrace'];
        $trace = Normalizer::normalize($trace);
        $trace = \array_slice($trace, 0, 6);
        $trace = Backtrace::addContext($trace, 0);

        self::assertSame(__FILE__, $trace[2]['file']);
        self::assertSame($line1, $trace[2]['line']);
        self::assertSame('bdk\Test\Backtrace\Fixture\Thing2->a', $trace[2]['function']);
        self::assertFalse($trace[2]['context']);

        self::assertSame(__FILE__, $trace[3]['file']);
        self::assertSame($line1, $trace[3]['line']);
        self::assertSame('eval', $trace[3]['function']);
        self::assertStringMatchesFormat('%weval($php);%w', $trace[3]['context'][$line1]);

        self::assertSame(__FILE__, $trace[4]['file']);
        self::assertSame($line2, $trace[4]['line']);
        self::assertSame('{closure}', $trace[4]['function']);
        self::assertStringMatchesFormat('%w$closure($php);%w', $trace[4]['context'][$line2]);
    }

    public function testAddContextXdebug()
    {
        $line1 = __LINE__ + 2;
        $closure = static function ($php) {
            eval($php);
        };
        $php = '
            $thing = new bdk\Test\Backtrace\Fixture\Thing2();
            $thing->a();
        ';
        $line2 = __LINE__ + 1;
        $closure($php);

        $trace = \array_reverse($GLOBALS['xdebug_trace']);
        $trace = Normalizer::normalize($trace);
        $trace = \array_slice($trace, 0, 6);
        $trace = Backtrace::addContext($trace);

        self::assertSame('bdk\\Backtrace\\Xdebug::getFunctionStack', $trace[0]['function']);
        self::assertSame(__DIR__ . '/Fixture/Thing2.php', $trace[0]['file']);

        self::assertSame('bdk\Test\Backtrace\Fixture\Thing2->c', $trace[1]['function']);

        self::assertSame('bdk\Test\Backtrace\Fixture\Thing2->b', $trace[2]['function']);

        self::assertSame(__FILE__, $trace[3]['file']);
        self::assertSame($line1, $trace[3]['line']);
        self::assertSame('bdk\\Test\\Backtrace\\Fixture\\Thing2->a', $trace[3]['function']);
        self::assertSame(1, \key($trace[3]['context']));
        self::assertSame($php, \implode('', $trace[3]['context']));

        self::assertSame(__FILE__, $trace[4]['file']);
        self::assertSame($line1, $trace[4]['line']);
        self::assertSame('eval', $trace[4]['function']);
        self::assertStringMatchesFormat('%weval($php);%w', $trace[4]['context'][$line1]);

        self::assertSame(__FILE__, $trace[5]['file']);
        self::assertSame($line2, $trace[5]['line']);
        self::assertSame('{closure}', $trace[5]['function']);
        self::assertStringMatchesFormat('%w$closure($php);%w', $trace[5]['context'][$line2]);
    }

    public function testGetFileLines()
    {
        self::assertFalse(Backtrace::getFileLines('/no/such/file.php'));
        self::assertSame(array(
            1 => "<?php\n",
        ), Backtrace::getFileLines(__FILE__, 0, 1));
    }
}

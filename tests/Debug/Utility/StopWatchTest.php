<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\StopWatch;
use bdk\PhpUnitPolyfill\AssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Utility\StopWatch
 */
class StopWatchTest extends TestCase
{
    use AssertionTrait;

    public function testConstruct()
    {
        $time = \microtime(true) - 11; // set request time to 11 sec ago
        $stopWatch = new StopWatch(array(
            'requestTime' => $time,
        ));
        $this->assertIsFloat($stopWatch->get());
        $this->assertGreaterThan(10, $stopWatch->get());
    }

    public function testLabel()
    {
        $stopWatch = new StopWatch();
        $stopWatch->start('test');
        $stopWatch->stop('test'); // pause
        $stopWatch->start('test'); // resume
        $elapsed = $stopWatch->get('test');
        $this->assertLessThan(0.005, $elapsed);

        $this->assertFalse($stopWatch->stop('noSuchTimer'));
    }

    public function testStack()
    {
        $time = \microtime(true) - 11; // set request time to 11 sec ago
        $stopWatch = new StopWatch(array(
            'requestTime' => $time,
        ));
        $stopWatch->start();
        $elapsed = $stopWatch->get();
        $this->assertIsFloat($elapsed);
        $this->assertLessThan(1, $elapsed);

        $this->assertIsFloat($stopWatch->stop());
    }

    public function testReset()
    {
        $stopWatch = new StopWatch();
        $stopWatch->start('test');
        $stopWatch->reset();
        $this->assertFalse($stopWatch->get('test'));
    }
}

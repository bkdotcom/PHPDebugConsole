<?php

namespace bdk\Test\Debug;

/**
 * @covers \bdk\Debug\Component
 */
class ComponentTest extends DebugTestFramework
{
    public function testGet()
    {
        $callInfo = new \bdk\Debug\Collector\SimpleCache\CallInfo('foo');
        $this->assertTrue($callInfo->isSuccess);

        $this->assertSame('foo', $callInfo->method);

        $this->assertNull($callInfo->noSuchProperty);
    }

    public function testGetCfg()
    {
        $this->assertIsArray($this->debug->getCfg());
    }

    public function testSetCfg()
    {
        $prev = $this->debug->getDump('html')->setCfg('bogus', 'ignoreMe');
        $this->assertNull($prev);
    }
}

<?php

namespace bdk\Test\Debug;

/**
 * @covers \bdk\Debug\AbstractComponent
 */
class ComponentTest extends DebugTestFramework
{
    public function testGet()
    {
        $callInfo = new \bdk\Debug\Collector\SimpleCache\CallInfo('foo');
        $callInfo->end(true);

        $this->assertTrue($callInfo->success);
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

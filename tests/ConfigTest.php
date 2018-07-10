<?php
/**
 * Run with --process-isolation option
 */

/**
 * PHPUnit tests for Debug class
 */
class ConfigTest extends DebugTestFramework
{

    /**
     * Test
     *
     * @return void
     */
    public function testGetCfg()
    {
        $abstracterKeys = array('collectConstants', 'collectMethods', 'objectsExclude', 'objectSort', 'useDebugInfo');
        $debugKeys = array('collect', 'file', 'key', 'output', 'channel', 'errorMask', 'emailFunc', 'emailLog', 'emailTo', 'logEnvInfo', 'logServerKeys', 'onLog', 'parent', 'services');

        $this->assertSame(true, $this->debug->getCfg('collect'));
        $this->assertSame(true, $this->debug->getCfg('debug.collect'));

        $this->assertSame('visibility', $this->debug->getCfg('objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter.objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter/objectSort'));

        $this->assertSame($abstracterKeys, array_keys($this->debug->getCfg('abstracter')));
        $this->assertSame($abstracterKeys, array_keys($this->debug->getCfg('abstracter/*')));
        $this->assertSame($debugKeys, array_keys($this->debug->getCfg()));
        $this->assertSame($debugKeys, array_keys($this->debug->getCfg('debug')));
        $this->assertSame($debugKeys, array_keys($this->debug->getCfg('debug/*')));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testSetCfg()
    {
        $this->assertSame(null, $this->debug->setCfg('foo', 'bar'));
        $this->assertSame('bar', $this->debug->setCfg('foo', 'baz'));
        $this->assertSame('baz', $this->debug->getCfg('foo'));
    }
}

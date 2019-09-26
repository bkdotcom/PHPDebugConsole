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
        $configKeys = array('debug', 'abstracter', 'errorEmailer', 'errorHandler', 'output');
        $abstracterKeys = array('cacheMethods', 'collectConstants', 'collectMethods', 'objectsExclude', 'objectSort', 'useDebugInfo');
        $debugKeys = array('collect', 'file', 'key', 'output', 'channelName', 'enableProfiling', 'errorMask', 'emailFrom', 'emailFunc', 'emailLog', 'emailTo', 'logEnvInfo', 'logRuntime', 'logServerKeys', 'onLog', 'factories', 'services');

        $this->assertSame(true, $this->debug->getCfg('collect'));
        $this->assertSame(true, $this->debug->getCfg('debug.collect'));

        $this->assertSame('visibility', $this->debug->getCfg('objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter.objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter/objectSort'));

        $this->assertSame(null, $this->debug->getCfg('debug'));
        $this->assertSame(null, $this->debug->getCfg('abstracter'));
        $this->assertSame($abstracterKeys, array_keys($this->debug->getCfg('abstracter/*')));
        $this->assertInternalType('boolean', $this->debug->getCfg('output'));       // debug/output
        $this->assertInternalType('array', $this->debug->getCfg('output/*'));
        $this->assertSame($configKeys, array_keys($this->debug->getCfg()));
        $this->assertSame($configKeys, array_keys($this->debug->getCfg('*')));
        $this->assertSame($debugKeys, array_keys($this->debug->getCfg('debug/*')));
        $this->assertSame(false, $this->debug->getCfg('logEnvInfo/cookies'));       // debug/logEnvInfo/cookies
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

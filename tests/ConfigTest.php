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
        $configKeys = array(
            'debug',
            'abstracter',
            'errorEmailer',
            'errorHandler',
            'routeHtml',
            'routeStream',
        );
        $abstracterKeys = array(
            'cacheMethods',
            'collectConstants',
            'collectMethods',
            'objectsExclude',
            'objectSort',
            'outputConstants',
            'outputMethodDesc',
            'outputMethods',
            'useDebugInfo',
            'fullyQualifyPhpDocType',
        );
        $debugKeys = array(
            'collect',
            'key',
            'output',
            'arrayShowListKeys',
            'channelIcon',
            'channelName',
            'channelShow',
            'enableProfiling',
            'errorMask',
            'emailFrom',
            'emailFunc',
            'emailLog',
            'emailTo',
            'factories',
            'headerMaxAll',
            'headerMaxPer',
            'logEnvInfo',
            'logResponse',
            'logRuntime',
            'logServerKeys',
            'onBootstrap',
            'onLog',
            'onOutput',
            'outputHeaders',
            'redactKeys',
            'redactReplace',
            'route',
            'routeNonHtml',
            'services',
        );

        $this->assertSame(true, $this->debug->getCfg('collect'));
        $this->assertSame(true, $this->debug->getCfg('debug.collect'));

        $this->assertSame('visibility', $this->debug->getCfg('objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter.objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter/objectSort'));

        $this->assertSame(null, $this->debug->getCfg('debug'));
        $this->assertSame(null, $this->debug->getCfg('abstracter'));
        $this->assertSame($abstracterKeys, array_keys($this->debug->getCfg('abstracter/*')));
        $this->assertInternalType('boolean', $this->debug->getCfg('output'));       // debug/output

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

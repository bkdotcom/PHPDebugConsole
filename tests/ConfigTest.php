<?php

use bdk\Debug;
use bdk\Debug\Psr7lite\ServerRequest;

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
            'abstracter',
            'debug',
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
            'channels',
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
            'logRequestInfo',
            'logResponse',
            'logResponseMaxLen',
            'logRuntime',
            'logServerKeys',
            'maxLenString',
            'onBootstrap',
            'onLog',
            'onOutput',
            'outputHeaders',
            'redactKeys',
            'redactReplace',
            'route',
            'routeNonHtml',
            'services',
            'sessionName',
        );

        $this->assertSame(true, $this->debug->getCfg('collect'));
        $this->assertSame(true, $this->debug->getCfg('debug.collect'));

        $this->assertSame('visibility', $this->debug->getCfg('objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter.objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter/objectSort'));

        $this->assertSame($debugKeys, array_keys($this->debug->getCfg('debug')));
        $this->assertSame($abstracterKeys, array_keys($this->debug->getCfg('abstracter')));
        $this->assertSame($abstracterKeys, array_keys($this->debug->getCfg('abstracter/*')));
        $this->assertInternalType('boolean', $this->debug->getCfg('output'));       // debug/output

        $this->assertSame($configKeys, array_keys($this->debug->getCfg()));
        $this->assertSame($configKeys, array_keys($this->debug->getCfg('*')));
        $this->assertSame($debugKeys, array_keys($this->debug->getCfg('debug/*')));
        $this->assertSame(false, $this->debug->getCfg('logRequestInfo/cookies'));
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

    public function testInitKey()
    {
        \bdk\Debug::getInstance()->setCfg('services', array(
            'request' => (new ServerRequest(array(), array('REQUEST_METHOD' => 'GET')))
                ->withQueryParams(array(
                    'debug' => 'swordfish',
                )),
        ));

        // Utility caches serverParams (statically)...  use serverParamsRef to clear it
        $utilityRef = new \ReflectionClass('bdk\\Debug\\Utility');
        $serverParamsRef = $utilityRef->getProperty('serverParams');
        $serverParamsRef->setAccessible(true);
        $serverParamsRef->setValue(array());

        $debug = new Debug(array(
            'key' => 'swordfish',
            'logResponse' => false,
        ));
        $this->assertTrue($debug->getCfg('collect'));
        $this->assertTrue($debug->getCfg('output'));
        $debug->setCfg('output', false);
        $serverParamsRef->setValue(array());
    }

    /**
     * Test config stores config values until class is instantiated
     *
     * @return void
     */
    public function testPending()
    {
        $debug = new Debug();

        $this->assertTrue($debug->getCfg('collectMethods'));
        $debug->setCfg('collectMethods', false);
        $this->assertFalse($debug->getCfg('collectMethods'));
        $debug->abstracter;
        $this->assertFalse($debug->abstracter->getCfg('collectMethods'));

        // routeHtml should not yet be loaded
        $this->assertFalse($debug->getRoute('html', true));
        // getting filepathScript should load routeHtml
        $filepathScript = $debug->getCfg('filepathScript');
        $this->assertInternalType('string', $filepathScript);
        $this->assertTrue($debug->getRoute('html', true));
    }
}

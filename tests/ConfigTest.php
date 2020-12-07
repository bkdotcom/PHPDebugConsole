<?php

namespace bdk\DebugTests;

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
            'collectAttributesConst',
            'collectAttributesObj',
            'collectConstants',
            'collectMethods',
            'objectsExclude',
            'objectsWhitelist',
            'objectSort',
            'outputAttributesConst',
            'outputAttributesObj',
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
            // 'arrayShowListKeys',
            'channels',
            'channelIcon',
            'channelName',
            'channelShow',
            'channelSort',
            'enableProfiling',
            'errorMask',
            'emailFrom',
            'emailFunc',
            'emailLog',
            'emailTo',
            'exitCheck',
            'factories',
            'headerMaxAll',
            'headerMaxPer',
            'logEnvInfo',
            'logRequestInfo',
            'logResponse',
            'logResponseMaxLen',
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
            'sessionName',
            'stringMaxLen',
        );

        $this->assertSame(true, $this->debug->getCfg('collect'));
        $this->assertSame(true, $this->debug->getCfg('debug.collect'));

        $this->assertSame('visibility', $this->debug->getCfg('objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter.objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter/objectSort'));

        $this->assertSame($debugKeys, \array_keys($this->debug->getCfg('debug')));
        $this->assertSame($abstracterKeys, \array_keys($this->debug->getCfg('abstracter')));
        $this->assertSame($abstracterKeys, \array_keys($this->debug->getCfg('abstracter/*')));
        $this->assertIsBool($this->debug->getCfg('output'));       // debug/output

        $this->assertSame($configKeys, \array_keys($this->debug->getCfg()));
        $this->assertSame($configKeys, \array_keys($this->debug->getCfg('*')));
        $this->assertSame($debugKeys, \array_keys($this->debug->getCfg('debug/*')));
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
        $debug = new Debug(array(
            'key' => 'swordfish',
            'logResponse' => false,
            'services' => array(
                'request' => (new ServerRequest(
                    'GET',
                    null,
                    array(
                        'REQUEST_METHOD' => 'GET', // presence of REQUEST_METHOD = not cli
                    )
                ))
                    ->withQueryParams(array(
                        'debug' => 'swordfish',
                    )),
            ),
        ));
        $this->assertTrue($debug->getCfg('collect'));
        $this->assertTrue($debug->getCfg('output'));
        $debug->setCfg('output', false);
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
        $this->assertIsString($filepathScript);
        $this->assertTrue($debug->getRoute('html', true));
    }
}

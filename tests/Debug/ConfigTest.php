<?php

namespace bdk\Test\Debug;

use bdk\Debug;
use bdk\HttpMessage\ServerRequest;

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
            'collectAttributesMethod',
            'collectAttributesObj',
            'collectAttributesParam',
            'collectAttributesProp',
            'collectConstants',
            'collectMethods',
            'collectPhpDoc',
            'fullyQualifyPhpDocType',
            'objectsExclude',
            'objectSort',
            'objectsWhitelist',
            'outputAttributesConst',
            'outputAttributesMethod',
            'outputAttributesObj',
            'outputAttributesParam',
            'outputAttributesProp',
            'outputConstants',
            'outputMethodDesc',
            'outputMethods',
            'outputPhpDoc',
            'stringMaxLen',
            'stringMinLen',
            'useDebugInfo',
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
            'serviceProvider',
            'sessionName',
            'wampPublisher',
            'container',
        );
        \sort($debugKeys);

        $this->assertSame(true, $this->debug->getCfg('collect'));
        $this->assertSame(true, $this->debug->getCfg('debug.collect'));

        $this->assertSame('visibility', $this->debug->getCfg('objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter.objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter/objectSort'));

        $keysActual = \array_keys($this->debug->getCfg('debug'));
        \sort($keysActual);
        $this->assertSame($debugKeys, $keysActual);
        $this->assertSame($abstracterKeys, \array_keys($this->debug->getCfg('abstracter')));
        $this->assertSame($abstracterKeys, \array_keys($this->debug->getCfg('abstracter/*')));
        $this->assertIsBool($this->debug->getCfg('output'));       // debug/output

        $this->assertSame($configKeys, \array_keys($this->debug->getCfg()));
        $this->assertSame($configKeys, \array_keys($this->debug->getCfg('*')));
        $keysActual = \array_keys($this->debug->getCfg('debug/*'));
        \sort($keysActual);
        $this->assertSame($debugKeys, $keysActual);
        $this->assertSame(false, $this->debug->getCfg('logRequestInfo/cookies'));
    }

    public function testEmailTo()
    {
        /*
            'emailTo' is currently set to null for tests..
        */
        $this->assertNull($this->debug->getCfg('emailTo'));

        /*
            test setting to 'default'
        */
        $this->debug->setCfg('emailTo', 'default');
        $this->assertSame('ttesterman@test.com', $this->debug->getCfg('emailTo'));
        $this->assertSame('ttesterman@test.com', $this->debug->getCfg('errorEmailer.emailTo'));
        /*
            updating the request obj will not update the default email!!
            this is intentional
        */
        $this->debug->setCfg('serviceProvider', array(
            'request' => new ServerRequest(
                'GET',
                null,
                array(
                    'SERVER_ADMIN' => 'bkfake-github@yahoo.com',
                )
            ),
        ));
        $this->assertSame('ttesterman@test.com', $this->debug->getCfg('emailTo'));
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
            'serviceProvider' => array(
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

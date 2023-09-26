<?php

namespace bdk\Test\Debug;

use bdk\Debug;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Mysqli debug collector
 *
 * @covers \bdk\Debug\Abstraction\Abstracter
 * @covers \bdk\Debug\Abstraction\AbstractArray
 * @covers \bdk\Debug\Abstraction\AbstractObject
 * @covers \bdk\Debug\Abstraction\AbstractString
 * @covers \bdk\Debug\Abstraction\Object\Constants
 * @covers \bdk\Debug\Abstraction\Object\Definition
 * @covers \bdk\Debug\Abstraction\Object\Helper
 * @covers \bdk\Debug\Abstraction\Object\MethodParams
 * @covers \bdk\Debug\Abstraction\Object\Methods
 * @covers \bdk\Debug\Abstraction\Object\Properties
 * @covers \bdk\Debug\Abstraction\Object\PropertiesDom
 * @covers \bdk\Debug\Abstraction\Object\PropertiesPhpDoc
 * @covers \bdk\Debug\Abstraction\Object\Subscriber
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\Helper
 * @covers \bdk\Debug\Dump\Html\HtmlObject
 * @covers \bdk\Debug\Dump\Html\HtmlString
 * @covers \bdk\Debug\Dump\Html\AbstractObjectSection
 * @covers \bdk\Debug\Dump\Html\Table
 * @covers \bdk\Debug\Dump\Html\Value
 * @covers \bdk\Debug\Route\ChromeLogger
 * @covers \bdk\Debug\Route\Script
 * @covers \bdk\Debug\Route\Text
 * @covers \bdk\Debug\ServiceProvider
 */
class ServiceProviderTest extends DebugTestFramework
{
    public function testDebugConstruct()
    {
        \bdk\Debug\Utility\Reflection::propSet('bdk\ErrorHandler', 'instance', null);

        $debug = new Debug(array(
            'logResponse' => false,
        ));

        self::assertInstanceOf('\bdk\Backtrace', $debug->backtrace);
        self::assertInstanceOf('\bdk\ErrorHandler', $debug->errorHandler);
        self::assertInstanceOf('\bdk\Debug\Utility\Html', $debug->html);
        if (PHP_VERSION_ID >= 70000) {
            self::assertInstanceOf('\bdk\Debug\Psr15\Middleware', $debug->middleware);
        }
        self::assertInstanceOf('\bdk\Debug\Plugin\Highlight', $debug->pluginHighlight);
        self::assertInstanceOf('\bdk\Debug\Route\Wamp', $debug->routeWamp);
        self::assertInstanceOf('\bdk\Debug\Utility\StopWatch', $debug->stopWatch);

        $debug = new Debug(array(
            'logResponse' => false,
        ));
        self::assertInstanceOf('\bdk\ErrorHandler', $debug->errorHandler);

        self::assertInstanceOf('\bdk\Debug\Abstraction\Abstracter', $debug->abstracter);
        self::assertInstanceOf('\bdk\Debug\Route\ChromeLogger', $debug->getRoute('chromeLogger'));
        self::assertInstanceOf('\bdk\Debug\Route\Script', $debug->getRoute('script'));
        self::assertInstanceOf('\bdk\Debug\Route\Text', $debug->getRoute('text'));
        self::assertInstanceOf('\bdk\Debug\Dump\Html\Helper', $debug->getDump('html')->helper);
        self::assertInstanceOf('\bdk\Debug\Dump\Html\HtmlObject', $debug->getDump('html')->valDumper->object);
        self::assertInstanceOf('\bdk\Debug\Dump\Html\Table', $debug->getDump('html')->table);
        self::assertInstanceOf('\bdk\Debug\Dump\Html\Value', $debug->getDump('html')->valDumper);
    }
}

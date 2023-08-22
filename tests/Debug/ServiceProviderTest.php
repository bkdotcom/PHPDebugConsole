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
 * @covers \bdk\Debug\Abstraction\AbstractObjectClass
 * @covers \bdk\Debug\Abstraction\AbstractObjectConstants
 * @covers \bdk\Debug\Abstraction\AbstractObjectHelper
 * @covers \bdk\Debug\Abstraction\AbstractObjectMethodParams
 * @covers \bdk\Debug\Abstraction\AbstractObjectMethods
 * @covers \bdk\Debug\Abstraction\AbstractObjectProperties
 * @covers \bdk\Debug\Abstraction\AbstractObjectSubscriber
 * @covers \bdk\Debug\Abstraction\AbstractString
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\Helper
 * @covers \bdk\Debug\Dump\Html\HtmlObject
 * @covers \bdk\Debug\Dump\Html\HtmlString
 * @covers \bdk\Debug\Dump\Html\ObjectConstants
 * @covers \bdk\Debug\Dump\Html\ObjectMethods
 * @covers \bdk\Debug\Dump\Html\ObjectProperties
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
        \bdk\Debug\Utility\Reflection::propSet('\bdk\ErrorHandler', 'instance', null);

        $debug = new Debug(array(
            'logResponse' => false,
        ));

        self::assertInstanceOf('\bdk\Backtrace', $debug->backtrace);
        self::assertInstanceOf('\bdk\ErrorHandler', $debug->errorHandler);
        self::assertInstanceOf('\bdk\Debug\Utility\Html', $debug->html);
        if (PHP_VERSION_ID >= 70000) {
            $this->assertInstanceOf('\bdk\Debug\Psr15\Middleware', $debug->middleware);
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

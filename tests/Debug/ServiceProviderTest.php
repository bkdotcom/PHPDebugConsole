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
 * @covers \bdk\Debug\Abstraction\AbstractObjectConstants
 * @covers \bdk\Debug\Abstraction\AbstractObjectHelper
 * @covers \bdk\Debug\Abstraction\AbstractObjectMethodParams
 * @covers \bdk\Debug\Abstraction\AbstractObjectMethods
 * @covers \bdk\Debug\Abstraction\AbstractObjectProperties
 * @covers \bdk\Debug\Abstraction\AbstractString
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\Helper
 * @covers \bdk\Debug\Dump\Html\HtmlObject
 * @covers \bdk\Debug\Dump\Html\HtmlString
 * @covers \bdk\Debug\Dump\Html\Table
 * @covers \bdk\Debug\Dump\Html\Value
 * @covers \bdk\Debug\Route\ChromeLogger
 * @covers \bdk\Debug\Route\Text
 * @covers \bdk\Debug\Route\Script
 * @covers \bdk\Debug\ServiceProvider
 */
class ServiceProviderTest extends DebugTestFramework
{
    public function testDebugConstruct()
    {
        // $reflectionProperty = new \ReflectionProperty('\bdk\ErrorHandler', 'instance');
        // $reflectionProperty->setAccessible(true);
        // $reflectionProperty->setValue(null);
        $this->helper->setPrivateProp('\bdk\ErrorHandler', 'instance', null);

        $debug = new Debug(array(
            'logResponse' => false,
        ));

        $this->assertInstanceOf('\bdk\Backtrace', $debug->backtrace);
        // $this->assertInstanceOf('\bdk\ErrorHandler\ErrorEmailer', $debug->errorEmailer);
        $this->assertInstanceOf('\bdk\ErrorHandler', $debug->errorHandler);
        $this->assertInstanceOf('\bdk\Debug\Utility\Html', $debug->html);
        $this->assertInstanceOf('\bdk\Debug\Method\Clear', $debug->methodClear);
        $this->assertInstanceOf('\bdk\Debug\Method\Time', $debug->methodTime);
        if (PHP_VERSION_ID >= 70000) {
            $this->assertInstanceOf('\bdk\Debug\Psr15\Middleware', $debug->middleware);
        }
        $this->assertInstanceOf('\bdk\Debug\Plugin\Highlight', $debug->pluginHighlight);
        $this->assertInstanceOf('\bdk\Debug\Route\Wamp', $debug->routeWamp);
        $this->assertInstanceOf('\bdk\Debug\Utility\StopWatch', $debug->stopWatch);

        $debug = new Debug(array(
            'logResponse' => false,
        ));
        $this->assertInstanceOf('\bdk\ErrorHandler', $debug->errorHandler);

        $this->assertInstanceOf('\bdk\Debug\Abstraction\Abstracter', $debug->abstracter);
        $this->assertInstanceOf('\bdk\Debug\Route\ChromeLogger', $debug->getRoute('chromeLogger'));
        $this->assertInstanceOf('\bdk\Debug\Route\Script', $debug->getRoute('script'));
        $this->assertInstanceOf('\bdk\Debug\Route\Text', $debug->getRoute('text'));
        $this->assertInstanceOf('\bdk\Debug\Dump\Html\Helper', $debug->getDump('html')->helper);
        $this->assertInstanceOf('\bdk\Debug\Dump\Html\HtmlObject', $debug->getDump('html')->valDumper->object);
        $this->assertInstanceOf('\bdk\Debug\Dump\Html\Table', $debug->getDump('html')->table);
        $this->assertInstanceOf('\bdk\Debug\Dump\Html\Value', $debug->getDump('html')->valDumper);
    }
}

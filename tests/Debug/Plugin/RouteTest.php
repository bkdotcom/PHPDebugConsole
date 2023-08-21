<?php

namespace bdk\Test\Debug\Plugin;

use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Plugin\Route
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class RouteTest extends DebugTestFramework
{
    public function testGetDefaultRoute()
    {
        $GLOBALS['collectedHeaders'] = array(
            array('Content-Type: text/html', false),
        );
        $route = $this->debug->getDefaultRoute();
        self::assertSame('html', $route);

        $GLOBALS['collectedHeaders'] = array(
            array('Content-Type: image/jpeg', false),
        );
        $route = $this->debug->getDefaultRoute();
        self::assertSame('serverLog', $route);

        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => new \bdk\HttpMessage\ServerRequest('GET', null, array(
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            )),
        ));
        $route = $this->debug->getDefaultRoute();
        self::assertSame('serverLog', $route);

        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => new \bdk\HttpMessage\ServerRequest('GET', null, array(
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            )),
        ));
        $this->debug->setCfg('route', 'html');
        $event = new Event($this->debug);
        $this->debug->getPlugin('route')->onOutput($event);
        $route = $this->debug->getCfg('route');
        self::assertInstanceOf('bdk\\Debug\\Route\\Html', $route);
    }

    public function testOnCfgRoute()
    {
        $container = $this->helper->getProp($this->debug, 'container');
        unset($container['routeFirephp']);

        $this->debug->setCfg('route', new \bdk\Debug\Route\Firephp($this->debug));
        self::assertInstanceOf('bdk\\Debug\\Route\\Firephp', $container['routeFirephp']);
        $this->debug->obEnd();
    }
}

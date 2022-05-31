<?php

namespace bdk\Test\Debug\Route;

use bdk\Test\Debug\DebugTestFramework;
use bdk\Debug\Route\Text as routeText;

/**
 * Test Text
 *
 * @covers \bdk\Debug\Route\AbstractRoute
 * @covers \bdk\Debug\Route\Text
 */
class TextTest extends DebugTestFramework
{
    public function testConstruct()
    {
        $route = new routeText($this->debug);
        $this->assertInstanceOf('bdk\\Debug\\Route\\Text', $route);
    }

    public function testProcessLogEntries()
    {
        $route = $this->debug->getRoute('text');
        \bdk\Test\Debug\Helper::setPrivateProp($route, 'shouldIncludeCache', array());
        $this->debug->alert('This will self destruct');
        $this->outputTest(array(
            'text' => 'This will self destruct',
        ));
    }

    public function testProcessLogEntriesExplicitChannels()
    {
        $route = $this->debug->getRoute('text');
        \bdk\Test\Debug\Helper::setPrivateProp($route, 'shouldIncludeCache', array());
        $route->setCfg('channels', array('general'));
        $this->debug->alert('Alert 2');
        $event = new \bdk\PubSub\Event();
        $route->processLogEntries($event);
        $this->assertSame('》[Alert ⦻ error] Alert 2《' . "\n", $event['return']);
    }

    public function testProcessLogEntriesWildCardChannels()
    {
        $route = $this->debug->getRoute('text');
        \bdk\Test\Debug\Helper::setPrivateProp($route, 'shouldIncludeCache', array());
        $route->setCfg('channels', array('general*'));
        $this->debug->alert('Alert 3');
        $event = new \bdk\PubSub\Event();
        $route->processLogEntries($event);
        $this->assertSame('》[Alert ⦻ error] Alert 3《' . "\n", $event['return']);
    }

    public function testGetDumper()
    {
        $dumper = $this->debug->getRoute('text')->dumper;
        $this->assertInstanceOf('bdk\\Debug\\Dump\\Text', $dumper);
    }
}

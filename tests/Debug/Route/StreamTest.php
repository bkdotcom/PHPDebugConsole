<?php

namespace bdk\Test\Debug\Route;

use bdk\Debug\Dump\TextAnsi;
use bdk\Debug\Route\Stream;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test TextAnsi route
 *
 * @covers \bdk\Debug\Dump\TextAnsi
 * @covers \bdk\Debug\Route\Stream
 */
class StreamTest extends DebugTestFramework
{
    public function testConstruct()
    {
        $route = new Stream($this->debug);
        $this->assertInstanceOf('bdk\\Debug\\Route\\Stream', $route);
    }

    public function testGetValDumper()
    {
        $valDumper = $this->debug->getDump('textAnsi')->valDumper;
        $this->assertInstanceOf('bdk\\Debug\\Dump\\TextAnsiValue', $valDumper);

        $dumper = new TextAnsi($this->debug);
        $valDumper = $dumper->valDumper;
        $this->assertInstanceOf('bdk\\Debug\\Dump\\TextAnsiValue', $valDumper);
    }

    public function testOnLog()
    {
        $route = $this->debug->getRoute('stream');
        $route->setCfg('stream', null);
        $route->setCfg('stream', 'php://temp');
        $route->setCfg('output', true);

        $route->onLog(new \bdk\Debug\LogEntry($this->debug, 'log', array('foo')));
        $fileHandle = $this->helper->getProp($route, 'fileHandle');
        \fseek($fileHandle, 0);
        $string = \fgets($fileHandle);
        $this->assertSame("foo\n", $string);

        $route->onLog(new \bdk\Debug\LogEntry($this->debug, 'groupUncollapse'));
        \fseek($fileHandle, 0);
        $string = \fgets($fileHandle);
        $this->assertSame("foo\n", $string);

        $route->setCfg('output', false);
        $route->onLog(new \bdk\Debug\LogEntry($this->debug, 'log', array('bar')));
        $fileHandle = $this->helper->getProp($route, 'fileHandle');
        \fseek($fileHandle, 0);
        $string = \fgets($fileHandle);
        $this->assertSame("foo\n", $string);

        $route->setCfg('stream', null);
        $route->onLog(new \bdk\Debug\LogEntry($this->debug, 'log', array('bar')));
        $fileHandle = $this->helper->getProp($route, 'fileHandle');
        $this->assertNull($fileHandle);
    }
}

<?php

namespace bdk\Test\Debug;

use bdk\Debug\LogEntry;

/**
 * PHPUnit tests for LogEntry class
 *
 * @covers \bdk\Debug\LogEntry
 */
class LogEntryTest extends DebugTestFramework
{
    public function testAppendGroup()
    {
        $logEntry = new LogEntry(
            $this->debug->getChannel('php', array('nested' => false)),
            'log',
            array('string', true, false, null, 42),
            array(
                'appendGroup' => 'foo bar',
                'id' => 'ding dong',
                'channel' => null,
            )
        );
        $this->assertSame(array(
            'appendGroup' => 'foo_bar',
            'attribs' => array(
                'id' => 'ding_dong',
                'class' => array(),
            ),
        ), $logEntry->getMeta());
        $this->assertSame($this->debug, $logEntry->getSubject());
    }

    public function testJsonEncode()
    {
        $this->debug->getChannel('Request / Response', array('nested' => false));
        $logEntry = new LogEntry(
            $this->debug->getChannel('php', array('nested' => false)),
            'log',
            array('string', true, false, null, 42),
            array(
                'attribs' => array(
                    'class' => 'ding dong',
                ),
                'channel' => 'Request / Response'
            )
        );
        $json = \json_encode($logEntry);
        $this->assertSame('{"method":"log","args":["string",true,false,null,42],"meta":{"attribs":{"class":["ding","dong"]},"channel":"Request \/ Response"}}', $json);
    }

    public function testSpecifyChannel()
    {
        $logEntry = new LogEntry(
            $this->debug,
            'log',
            array('string', true, false, null, 42),
            array(
                'channel' => 'php'
            )
        );
        $json = \json_encode($logEntry);
        $this->assertSame('{"method":"log","args":["string",true,false,null,42],"meta":{"channel":"php"}}', $json);
    }
}

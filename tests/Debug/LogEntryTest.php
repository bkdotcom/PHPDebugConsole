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
        self::assertSame(array(
            'appendGroup' => 'foo_bar',
            'attribs' => array(
                'id' => 'ding_dong',
                'class' => array(),
            ),
        ), $logEntry->getMeta());
        self::assertSame($this->debug, $logEntry->getSubject());
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
                'channel' => 'request-response',
            )
        );
        $json = \json_encode($logEntry);
        self::assertSame('{"method":"log","args":["string",true,false,null,42],"meta":{"attribs":{"class":["ding","dong"]},"channel":"request-response"}}', $json);
    }

    public function testSpecifyChannel()
    {
        // channel does not exist..  nested channel will get created
        $this->debug->getChannel('php', array('nested' => false));
        $logEntry = new LogEntry(
            $this->debug,
            'log',
            array('string', true, false, null, 42),
            array(
                'channel' => 'php',
            )
        );
        $json = \json_encode($logEntry);
        self::assertSame('{"method":"log","args":["string",true,false,null,42],"meta":{"channel":"php"}}', $json);
    }

    public function testSpecifyChannelNotYetCreated()
    {
        // channel does not exist..  nested channel will get created
        $logEntry = new LogEntry(
            $this->debug,
            'log',
            array('string', true, false, null, 42),
            array(
                'channel' => 'php',
            )
        );
        $json = \json_encode($logEntry);
        self::assertSame('{"method":"log","args":["string",true,false,null,42],"meta":{"channel":"general.php"}}', $json);
    }
}

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
        // this test originally tested that 'id' 'appendGroup' value and get sanitized to a valid id
        //   we now only sanitize id when output as html attrib
        $logEntry = new LogEntry(
            $this->debug->getChannel('php', array('nested' => false)),
            'log',
            array('string', true, false, null, 42),
            array(
                'appendGroup' => 'foo bar',
                'channel' => null,
                'id' => 'ding dong',
            )
        );
        self::assertSame(array(
            'appendGroup' => 'foo bar',
            'attribs' => array(
                'id' => 'ding dong',
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

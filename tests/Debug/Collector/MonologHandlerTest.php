<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug\Collector\MonologHandler;
use bdk\Test\Debug\DebugTestFramework;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use Monolog\Logger;
use Psr\Log\LogLevel;

/**
 * PHPUnit tests for MonologHandler
 *
 * @covers \bdk\Debug\Collector\MonologHandler
 */
class MonologHandlerTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    /**
     * @doesNotPerformAssertions
     */
    public function testConstructPassedDebug()
    {
        new MonologHandler($this->debug);
    }

    public function testConstructPassedLogger()
    {
        $handler = new MonologHandler($this->debug->logger);
        $this->assertInstanceOf('bdk\\Debug\\Collector\\MonologHandler', $handler);
    }

    public function testConstructThrowsException()
    {
        $this->expectException('InvalidArgumentException');
        $handler = new MonologHandler('foo');
    }

    /**
     * @dataProvider methodProvider
     */
    public function testMonolog($psr3method, $method, $args)
    {
        $monolog = new Logger('PHPDebugConsole');
        $handler = new MonologHandler();
        $monolog->pushHandler($handler);

        \call_user_func_array(array($monolog, $psr3method), $args);
        $path = $method === 'alert'
            ? 'alerts/__end__'
            : 'log/__end__';
        $logEntry = $this->debug->data->get($path);
        $array = $this->helper->logEntryToArray($logEntry);
        // $this->helper->stderr($array);
        $this->assertSame($method, $array['method']);
        $this->assertSame($args, $array['args']);
        $this->assertSame($psr3method, $array['meta']['psr3level']);
    }

    /**
     * @dataProvider methodProvider
     */
    public function testMonologLevel($psr3method, $method, $args)
    {
        $monolog = new Logger('PHPDebugConsole');
        $handler = new MonologHandler($this->debug, LogLevel::WARNING);
        $monolog->pushHandler($handler);

        \call_user_func_array(array($monolog, $psr3method), $args);
        $path = $method === 'alert'
            ? 'alerts/__end__'
            : 'log/__end__';
        $logEntry = $this->debug->data->get($path);
        $levelsHandled = array(
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
        );
        if (\in_array($psr3method, $levelsHandled, true)) {
            $array = $this->helper->logEntryToArray($logEntry);
            $this->assertSame($method, $array['method']);
            $this->assertSame($args, $array['args']);
            $this->assertSame($psr3method, $array['meta']['psr3level']);
        } else {
            // $this->helper->stderr($psr3method, $logEntry);
            $this->assertNull($logEntry);
        }
    }

    /*
    public function testFormatter()
    {
        $monolog = new Logger('PHPDebugConsole');
        $handler = new MonologHandler($this->debug);
        $formatter = new \Monolog\Formatter\NormalizerFormatter();
        $handler->setFormatter($formatter);
        $monolog->pushHandler($handler);
        $monolog->debug('test', array(
            'datetime' => new \DateTime('2023-01-21 21:00:00'),
        ));
        $logEntry = $this->debug->data->get('log/__end__');
        $this->helper->stderr('logEntry', $logEntry);
        $array = $this->helper->logEntryToArray($logEntry);
        $this->assertSame('log', $array['method']);
        $this->assertSame(array(''), $array['args']);
    }
    */

    public function testPlaceholders()
    {
        $monolog = new Logger('PHPDebugConsole');
        $handler = new MonologHandler($this->debug);
        $monolog->pushHandler($handler);

        $monolog->debug('{adj} debugging', array(
            'adj' => 'Awesome',
            'foo' => 'bar',
        ));
        $this->assertSame(array(
            'method' => 'log',
            'args' => array(
                'Awesome debugging',
                array('foo' => 'bar'),
            ),
            'meta' => array(
                'channel' => 'general.PHPDebugConsole',
                'glue' => ', ',
                'psr3level' => 'debug',
            ),
        ), $this->helper->logEntryToArray($this->debug->data->get('log/__end__')));
    }

    public function testDebugWithTable()
    {
        $monolog = new Logger('PHPDebugConsole');
        $handler = new MonologHandler($this->debug);
        $monolog->pushHandler($handler);

        $tableData = array(
            array('name' => 'Bob', 'age' => '12', 'sex' => 'M', 'Naughty' => false),
            array('Naughty' => true, 'name' => 'Sally', 'extracol' => 'yes', 'sex' => 'F', 'age' => '10'),
        );
        $tableDataLogged = array(
            array('name' => 'Bob', 'age' => '12',),
            array('name' => 'Sally', 'age' => '10', ),
        );

        $monolog->debug('table caption', array(
            'table' => $tableData,
            'columns' => array('name', 'age'),
        ));
        $this->assertSame(array(
            'method' => 'table',
            'args' => array(
                $tableDataLogged,
            ),
            'meta' => array(
                'caption' => 'table caption',
                'channel' => 'general.PHPDebugConsole',
                'psr3level' => 'debug',
                'sortable' => true,
                'tableInfo' => array(
                    'class' => null,
                    'columns' => array(
                        array(
                            'key' => 'name',
                        ),
                        array(
                            'key' => 'age',
                        ),
                    ),
                    'haveObjRow' => false,
                    'indexLabel' => null,
                    'rows' => array(),
                    'summary' => null,
                ),
            ),
        ), $this->helper->logEntryToArray($this->debug->data->get('log/__end__')));
    }

    public static function methodProvider()
    {
        return array(
            'emergency' => array('emergency', 'error', array('monolog emergency')),
            'alert' => array('alert', 'alert', array('monolog alert')),
            'critical' => array('critical', 'error', array('monolog critical')),
            'error' => array('error', 'error', array('monolog error')),
            'warning' => array('warning', 'warn', array('monolog warning')),
            'notice' => array('notice', 'warn', array('monolog notice')),
            'info' => array('info', 'info', array('monolog info')),
            'debug' => array('debug', 'log', array('monolog debug')),
        );
    }
}

<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Collector\MonologHandler;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Test\Debug\DebugTestFramework;
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
        self::assertInstanceOf('bdk\\Debug\\Collector\\MonologHandler', $handler);
    }

    public function testConstructThrowsException()
    {
        $this->expectException('InvalidArgumentException');
        new MonologHandler('foo');
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
        self::assertSame($method, $array['method']);
        self::assertSame($args, $array['args']);
        self::assertSame($psr3method, $array['meta']['psr3level']);
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
            self::assertSame($method, $array['method']);
            self::assertSame($args, $array['args']);
            self::assertSame($psr3method, $array['meta']['psr3level']);
            return;
        }
        self::assertNull($logEntry);
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
        self::assertSame(array(
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

        $tableData = [
            array('name' => 'Bob', 'age' => '12', 'sex' => 'M', 'Naughty' => false),
            array('Naughty' => true, 'name' => 'Sally', 'extracol' => 'yes', 'sex' => 'F', 'age' => '10'),
        ];
        $tableDataLogged = [
            'caption' => 'table caption',
            'debug' => Abstracter::ABSTRACTION,
            'header' => ['','name','age'],
            'meta' => [
                'class' => null,
                'columns' => [
                    [
                        'attribs' => array(
                            'class' => ['t_key'],
                            'scope' => 'row',
                        ),
                        'key' => \bdk\Table\Factory::KEY_INDEX,
                        'tagName' => 'th',
                    ],
                    ['key' => 'name'],
                    ['key' => 'age'],
                ],
                'haveObjectRow' => false,
                'sortable' => true,
            ],
            'rows' => [
                [
                    0,
                    'Bob',
                    '12',
                ],
                [
                    1,
                    'Sally',
                    '10',
                ],
            ],
            'type' => 'table',
            'value' => null,
        ];

        $monolog->debug('table caption', array(
            'table' => $tableData,
            'columns' => array('name', 'age'),
        ));
        self::assertSame(array(
            'method' => 'table',
            'args' => array(
                $tableDataLogged,
            ),
            'meta' => array(
                'channel' => 'general.PHPDebugConsole',
                'psr3level' => 'debug',
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

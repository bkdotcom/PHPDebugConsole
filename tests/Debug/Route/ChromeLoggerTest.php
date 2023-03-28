<?php

namespace bdk\Test\Debug\Route;

use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Html route
 *
 * @covers \bdk\Debug\Route\ChromeLogger
 */
class ChromeLoggerTest extends DebugTestFramework
{
    public function setUp(): void
    {
        parent::setup();
        $debug = \bdk\Debug::getInstance();
        $channels = $debug->getChannelsTop();
        foreach ($channels as $channel) {
            if ($channel === $debug) {
                continue;
            }
            $channel->setCfg('output', false);
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->debug->setCfg(array(
            'headerMaxPer' => null,
            'route' => 'html',
        ));
        $this->debug->routeChromeLogger->setCfg('group', true);
    }

    public function testUnableToFitToMax()
    {
        $this->debug->setCfg(array(
            'headerMaxPer' => 128,
            'outputHeaders' => false,
            'route' => 'chromeLogger',
        ));
        $this->debug->output();
        $header = \base64_decode($this->debug->getHeaders()[0][1], true);
        $rows = \json_decode($header, true)['rows'];
        self::assertSame(array(
            array(
                array(
                    'chromeLogger: unable to abridge log to 128 B',
                ),
                null,
                'warn',
            ),
        ), $rows);
    }

    public function testAssertEncodedLength()
    {
        $this->debug->setCfg(array(
            'headerMaxPer' => 823,
            'outputHeaders' => false,
            'route' => 'chromeLogger',
        ));
        $this->debug->routeChromeLogger->setCfg('group', false);
        $this->debug->log(1, (object) array(
            'This is a test',
            'Brad was here',
        ));
        $this->debug->alert('hello alert');
        $this->debug->table(2, array(
            array('city' => 'Atlanta', 'state' => 'GA', 'population' => 472522,),
            array('city' => 'Buffalo', 'state' => 'NY', 'population' => 256902,),
            array('city' => 'Chicago', 'state' => 'IL', 'population' => 2704958,),
            array('city' => 'Denver', 'state' => 'CO', 'population' => 693060,),
            array('city' => 'Seattle', 'state' => 'WA', 'population' => 704352,),
            array('city' => 'Tulsa', 'state' => 'OK', 'population' => 403090,),
        ), 'Populations');

        $this->debug->output();
        $header = \base64_decode($this->debug->getHeaders()[0][1], true);
        $rows = \json_decode($header, true)['rows'];
        $this->assertSame(array(
            array(
                array(
                    'PHP',
                    'GET ' . (string) $this->debug->serverRequest->getUri(),
                ),
                null,
                'info',
            ),
            array(
                array(
                    '%cLog abridged due to header size constraint',
                    'padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #d9edf7; border: 1px solid #bce8f1; color: #31708f;',
                ),
                null,
                'info',
            ),
            array(
                array(
                    '%chello alert',
                    'padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #ffbaba; border: 1px solid #d8000c; color: #d8000c;',
                ),
                null,
                '',
            ),
        ), $rows);
    }

    public function testReduceDataFill()
    {
        $this->debug->setCfg(array(
            'headerMaxPer' => 1024,
            'outputHeaders' => false,
            'route' => 'chromeLogger',
        ));
        $this->debug->routeChromeLogger->setCfg('group', false);
        $this->debug->group('group');
        $this->debug->log(1, (object) array(
            'This is a test',
            'Brad was here',
        ));
        $this->debug->alert('hello alert');
        $this->debug->table(2, array(
            array('city' => 'Atlanta', 'state' => 'GA', 'population' => 472522,),
            array('city' => 'Buffalo', 'state' => 'NY', 'population' => 256902,),
            array('city' => 'Chicago', 'state' => 'IL', 'population' => 2704958,),
            array('city' => 'Denver', 'state' => 'CO', 'population' => 693060,),
            array('city' => 'Seattle', 'state' => 'WA', 'population' => 704352,),
            array('city' => 'Tulsa', 'state' => 'OK', 'population' => 403090,),
        ), 'Populations');
        $this->debug->log('test');
        $this->debug->groupEnd();

        $this->debug->output();
        $header = \base64_decode($this->debug->getHeaders()[0][1], true);
        $rows = \json_decode($header, true)['rows'];
        $this->assertSame(array(
            array(
                array(
                    'PHP',
                    'GET ' . (string) $this->debug->serverRequest->getUri(),
                ),
                null,
                'info',
            ),
            array(
                array(
                    '%cLog abridged due to header size constraint',
                    'padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #d9edf7; border: 1px solid #bce8f1; color: #31708f;',
                ),
                null,
                'info',
            ),
            array(
                array(
                    '%chello alert',
                    'padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #ffbaba; border: 1px solid #d8000c; color: #d8000c;',
                ),
                null,
                '',
            ),
            array(
                array('group'),
                null,
                'group',
            ),
            array(
                array('test'),
                null,
                '',
            ),
            array(
                array(),
                null,
                'groupEnd',
            ),
        ), $rows);
    }

    public function testViaCli()
    {
        $this->debug->setCfg(array(
            'route' => 'chromeLogger',
            'serviceProvider' => array(
                'serverRequest' => new \bdk\HttpMessage\ServerRequest('GET', '', array(
                    'argv' => array('foo','bar'),
                )),
            ),
        ));
        $this->debug->output();
        $header = \base64_decode($this->debug->getHeaders()[0][1], true);
        $rows = \json_decode($header, true)['rows'];
        \array_splice($rows, 1, 2, array());
        self::assertSame(array(
            array(
                array(
                    'PHP',
                    '$: foo bar',
                ),
                null,
                'groupCollapsed',
            ),
            // extracted entries
            array(
                array(),
                null,
                'groupEnd',
            ),
        ), $rows);
    }
}

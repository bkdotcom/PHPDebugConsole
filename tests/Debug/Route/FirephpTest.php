<?php

namespace bdk\Test\Debug\Route;

use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Firephp
 *
 * @covers \bdk\Debug\Route\Firephp
 */
class FirephpTest extends DebugTestFramework
{
    public function setUp(): void
    {
        parent::setup();

        $channels = $this->debug->getChannelsTop();
        foreach ($channels as $channel) {
            if ($channel === $this->debug) {
                continue;
            }
            $channel->setCfg('output', false);
        }

        $routeFirephp = $this->debug->getRoute('firephp');
        $messageIndex = new \ReflectionProperty($routeFirephp, 'messageIndex');
        $messageIndex->setAccessible(true);
        $messageIndex->setValue($routeFirephp, 0);

        $timersRef = new \ReflectionProperty($this->debug->stopWatch, 'timers');
        $timersRef->setAccessible(true);
        $timers = $timersRef->getValue($this->debug->stopWatch);
        $timers['labels']['requestTime'] = array(0, \microtime(true));
        $timersRef->setValue($this->debug->stopWatch, $timers);
    }

    /*
    public function tearDown(): void
    {
        parent::tearDown();
        $this->debug->setCfg(array(
            // 'headerMaxPer' => null,
            'route' => 'html',
        ));
    }
    */

    public function testMessageLimit()
    {
        $this->debug->setCfg(array(
            'route' => 'firephp',
            'serviceProvider' => array(
                'serverRequest' => new \bdk\HttpMessage\ServerRequest('GET', '', array(
                    'argv' => array('foo','bar'),
                )),
            ),
        ));
        $limitWas = $this->debug->getRoute('firephp')->getCfg('messageLimit');
        $this->debug->getRoute('firephp')->setCfg('messageLimit', 50);
        $logEntry = new LogEntry($this->debug, 'log', array('hi'));
        $this->debug->data->set('log', \array_fill(0, 55, $logEntry));
        $this->debug->output();
        $headers = $this->debug->getHeaders();
        $last3 = \array_slice($headers, -3);
        // \bdk\Test\Debug\Helper::stderr('count', \count($headers), $last3);
        self::assertSame('21|[{"Type":"LOG"},"hi"]|', $last3[0][1]);
        self::assertStringMatchesFormat('%d|[{"Type":"WARN"},"FirePhp\'s limit of ' . \number_format(50) . ' messages reached!"]|', $last3[1][1]);
        self::assertSame('X-Wf-1-Index', $last3[2][0]);
        self::assertSame(51, $last3[2][1]);
        $this->debug->getRoute('firephp')->setCfg('messageLimit', $limitWas);
    }

    public function testViaCli()
    {
        $this->debug->setCfg(array(
            'route' => 'firephp',
            'serviceProvider' => array(
                'serverRequest' => new \bdk\HttpMessage\ServerRequest('GET', '', array(
                    'argv' => array('foo','bar'),
                    'REQUEST_TIME_FLOAT' => \microtime(true),
                )),
            ),
        ));
        $this->debug->output();
        $jsonExpect = <<<'EOD'
[
    [
        "X-Wf-Protocol-1",
        "http:\/\/meta.wildfirehq.org\/Protocol\/JsonStream\/0.2"
    ],
    [
        "X-Wf-1-Plugin-1",
        "http:\/\/meta.firephp.org\/Wildfire\/Plugin\/FirePHP\/Library-FirePHPCore\/0.3"
    ],
    [
        "X-Wf-1-Structure-1",
        "http:\/\/meta.firephp.org\/Wildfire\/Structure\/FirePHP\/FirebugConsole\/0.1"
    ],
    [
        "X-Wf-1-1-1-1",
        "74|[{\"Collapsed\":\"true\",\"Label\":\"PHP: $: foo bar\",\"Type\":\"GROUP_START\"},null]|"
    ],
    [
        "X-Wf-1-1-1-2",
        "%d|[{\"Type\":\"INFO\"},\"Built In %f ms\"]|"
    ],
    [
        "X-Wf-1-1-1-3",
        "%d|[{\"Type\":\"INFO\"},\"Peak Memory Usage: %s\"]|"
    ],
    [
        "X-Wf-1-1-1-4",
        "27|[{\"Type\":\"GROUP_END\"},null]|"
    ],
    [
        "X-Wf-1-Index",
        4
    ]
]
EOD;
        $headers = $this->debug->getHeaders();
        self::assertStringMatchesFormat($jsonExpect, \json_encode($headers, JSON_PRETTY_PRINT));
    }
}

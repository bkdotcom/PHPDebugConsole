<?php

namespace bdk\Test\Debug\Plugin;

use bdk\PubSub\Manager as EventManager;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Plugin\Runtime
 */
class RuntimeTest extends DebugTestFramework
{
    /**
     * @doesNotPerformAssertions
     */
    public function testBootstrap()
    {
        $this->debug->removePlugin($this->debug->getPlugin('runtime'));
        $this->debug->addPlugin(new \bdk\Debug\Plugin\Runtime(), 'runtime');
    }

    public function testOnShutdown()
    {
        $this->debug->setCfg(array(
            'output' => true,
        ));

        \ob_start();
        $this->debug->eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
        \ob_get_clean();

        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('logSummary')[1]);

        self::assertStringMatchesFormat('Built in %f %s', $logEntries[0]['args'][0]);
        self::assertStringMatchesFormat('Peak memory usage <i class="fa fa-question-circle-o" title="Includes debug overhead"></i>: %f %s / %f %s', $logEntries[1]['args'][0]);
    }
}

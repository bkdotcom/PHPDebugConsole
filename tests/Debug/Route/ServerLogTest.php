<?php

namespace bdk\Test\Debug\Route;

use bdk\Debug\Route\ServerLog;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Html route
 *
 * @covers \bdk\Debug\Route\ServerLog
 */
class ServerLogTest extends DebugTestFramework
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        try {
            $logDir = TEST_DIR . '/../tmp/log';
            $files = \glob($logDir . '/*.json');
            foreach ($files as $filePath) {
                \unlink($filePath);
            }
            \rmdir($logDir);
        } catch (\Exception $e) {
            // ignore
        }
    }

    public function testConstruct()
    {
        $serverLog = new ServerLog($this->debug);
        $this->assertSame($this->debug->getServerParam('DOCUMENT_ROOT') . '/log', $this->debug->getRoute('serverLog')->getCfg('logDir'));

        $debug = new \bdk\Debug();
        $serverLog = new ServerLog($debug);
        $this->assertSame(\sys_get_temp_dir() . '/log', $serverLog->getCfg('logDir'));
        $this->assertStringMatchesFormat('serverLog_%s_%s.json', $serverLog->filename);
    }

    public function testCollectGarbage()
    {
        $serverLog = $this->debug->getRoute('serverLog');
        $event = new \bdk\PubSub\Event($this->debug, array(
            'headers' => array(),
        ));

        $serverLog->setCfg('gcProb', 1);
        $serverLog->setCfg('lifetime', 0);

        // 1st call to create a log file
        $serverLog->processLogEntries($event);
        $serverLog->processLogEntries($event);

        $logDir = $serverLog->getCfg('logDir');
        $files = \glob($logDir . '/*');

        $this->assertCount(1, $files);
        $serverLog->setCfg('gcProb', .1);
        $serverLog->setCfg('lifetime', 60);
    }
}

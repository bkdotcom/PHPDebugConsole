<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\SwiftMailerLogger;
use bdk\PubSub\Manager as EventManager;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Collector\SwiftMailerLogger
 */
class SwiftMailerLoggerTest extends DebugTestFramework
{
    public static function tearDownAfterClass(): void
    {
        $debug = \bdk\Debug::getInstance();
        $shutDownSubscribers = $debug->eventManager->getSubscribers(EventManager::EVENT_PHP_SHUTDOWN);
        foreach ($shutDownSubscribers as $subscriber) {
            $callable = $subscriber['callable'];
            if (\is_array($callable) && $callable[0] instanceof SwiftMailerLogger) {
                $debug->eventManager->unsubscribe(EventManager::EVENT_PHP_SHUTDOWN, $callable);
                break;
            }
        }
    }

    public function testConstruct()
    {
        $sendmailPath = \ini_get('sendmail_path') ?: '/usr/sbin/sendmail -bs';
        \set_error_handler(static function () {});
        $transport = new \Swift_SendmailTransport($sendmailPath);
        \restore_error_handler();

        /*
        $transport = new Swift_SmtpTransport('smtp.mandrillapp.com');
        $transport->setPort(587)
            ->setEncryption('tls')
            ->setUsername('support@brickwire.com')
            ->setPassword('xNjvL74n1DJbFFDBIP7X8w');
        */

        $mailer = new \Swift_Mailer($transport);
        $logger = new SwiftMailerLogger($this->debug);
        $mailer->registerPlugin($logger);

        self::assertTrue(true);
    }
}

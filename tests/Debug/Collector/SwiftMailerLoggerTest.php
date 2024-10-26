<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug\Collector\SwiftMailerLogger;
use bdk\PubSub\Manager as EventManager;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Test\Debug\DebugTestFramework;
use Swift_Events_CommandEvent;
use Swift_Events_ResponseEvent;
use Swift_Events_SendEvent;
use Swift_Events_TransportChangeEvent;
use Swift_Events_TransportExceptionEvent;
use Swift_Mailer;
use Swift_Message;
use Swift_SendmailTransport;
use Swift_TransportException;

/**
 * @covers \bdk\Debug\Collector\SwiftMailerLogger
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class SwiftMailerLoggerTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    protected $logger;
    protected $transport;

    public static function setUpBeforeClass(): void
    {
        $debug = \bdk\Debug::getInstance();
        $debug->errorHandler->eventManager->subscribe(\bdk\ErrorHandler::EVENT_ERROR, static function (\bdk\ErrorHandler\Error $error) {
            if (\strpos($error['file'], 'vendor/swiftmailer') !== false) {
                // \bdk\Debug::varDump('Error', $error->getValues());
                // $error->stopPropagation();
                $error['continueToPrevHandler'] = false;
                $error['continueToNormal'] = false;
                $error['isSuppressed'] = true;
            }
        }, 100);
    }

    public static function tearDownAfterClass(): void
    {
        $debug = \bdk\Debug::getInstance();
        $shutDownSubscribers = $debug->eventManager->getSubscribers(EventManager::EVENT_PHP_SHUTDOWN);
        foreach ($shutDownSubscribers as $subscriber) {
            $callable = $subscriber['callable'];
            if (\is_array($callable) && $callable[0] instanceof SwiftMailerLogger) {
                $debug->eventManager->unsubscribe(EventManager::EVENT_PHP_SHUTDOWN, $callable);
            }
        }
    }

    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = new SwiftMailerLogger($this->debug);
        }
        return $this->logger;
    }

    public function getMessage()
    {
        return (new Swift_Message('Test Message'))
            ->setFrom(['fake@fake.com' => 'Brad Kent'])
            // ->setTo(['receiver@domain.org', 'randyr@domain.org' => 'Randy Recipient'])
            ->setTo(['test@test.com'])
            ->setBody('Here is the message itself');
    }

    public function getTransport()
    {
        if (!$this->transport) {
            $sendmailPath = \ini_get('sendmail_path') ?: '/usr/sbin/sendmail -bs';
            \set_error_handler(static function () {});
            $this->transport = new Swift_SendmailTransport($sendmailPath);
            \restore_error_handler();
        }
        return $this->transport;
    }

    public function testConstruct()
    {
        $transport = $this->getTransport();

        /*
        $transport = new Swift_SmtpTransport('smtp.mandrillapp.com');
        $transport->setPort(587)
            ->setEncryption('tls')
            ->setUsername('support@brickwire.com')
            ->setPassword('xNjvL74n1DJbFFDBIP7X8w');
        */

        $mailer = new Swift_Mailer($transport);
        $logger = $this->getLogger();
        $mailer->registerPlugin($logger);

        self::assertTrue(true);
    }

    public function testBeforeSendPerformed()
    {
        $logger = $this->getLogger();
        $event = new Swift_Events_SendEvent($this->getTransport(), $this->getMessage());
        $logger->beforeSendPerformed($event);
        self::assertLogEntries([
            array(
                'method' => 'groupCollapsed',
                'args' => [
                    'sending email',
                    '<test@test.com>',
                    'Test Message',
                ],
                'meta' => array(
                    'channel' => 'general.SwiftMailer',
                    'icon' => 'fa fa-envelope-o',
                ),
            ),
            array(
                'method' => 'log',
                'args' => [
                    0 => 'headers',
                    1 => 'Message-ID: <%s@swift.generated>
Date: %s
Subject: Test Message
From: Brad Kent <fake@fake.com>
To: test@test.com
MIME-Version: 1.0
Content-Type: text/plain; charset=utf-8
Content-Transfer-Encoding: quoted-printable
',
                ],
                'meta' => array(
                    'channel' => 'general.SwiftMailer',
                ),
            ),
        ]);
    }

    public function testSendPerformed()
    {
        $logger = $this->getLogger();
        $event = new Swift_Events_SendEvent($this->getTransport(), $this->getMessage());
        $logger->sendPerformed($event);
        $this->assertSame(0, $this->debug->rootInstance->getPlugin('methodGroup')->getDepth());
        $this->assertSame('', $logger->dump());
    }

    /*
    public function testAdd()
    {
        $logger = $this->getLogger();
        $logger->add();
    }

    public function testClear()
    {
        $logger = $this->getLogger();
        $logger->clear();
    }

    public function testDump()
    {
        $logger = $this->getLogger();
        $logger->dump();
    }
    */

    public function testCommandSent()
    {
        $logger = $this->getLogger();
        $event = new Swift_Events_CommandEvent($this->getTransport(), 'burp');
        $logger->commandSent($event);
        self::assertLogEntries([
            array(
                'method' => 'log',
                'args' => [
                    0 => '>>:',
                    1 => 'burp',
                ],
                'meta' => array(
                    'channel' => 'general.SwiftMailer',
                    'icon' => 'fa fa-envelope-o',
                ),
            ),
        ]);
    }

    public function testResponseReceived()
    {
        $logger = $this->getLogger();
        $event = new Swift_Events_ResponseEvent($this->getTransport(), 'some response');
        $logger->responseReceived($event);
        self::assertLogEntries([
            array(
                'method' => 'log',
                'args' => [
                    '<<:',
                    'some response',
                ],
                'meta' => array(
                    'channel' => 'general.SwiftMailer',
                    'icon' => 'fa fa-envelope-o',
                ),
            ),
        ]);
    }

    public function testBeforeTransportStarted()
    {
        $logger = $this->getLogger();
        $event = new Swift_Events_TransportChangeEvent($this->getTransport());
        $logger->beforeTransportStarted($event);
        self::assertLogEntries([
            array(
                'method' => 'log',
                'args' => [
                    '++:',
                    'Starting Swift_SendmailTransport',
                ],
                'meta' => array(
                    'channel' => 'general.SwiftMailer',
                    'icon' => 'fa fa-envelope-o',
                ),
            ),
        ]);
    }

    public function testBeforeTransportStopped()
    {
        $logger = $this->getLogger();
        $event = new Swift_Events_TransportChangeEvent($this->getTransport());
        $logger->beforeTransportStopped($event);
        self::assertLogEntries([
            array(
                'method' => 'log',
                'args' => [
                    '--:',
                    'Stopping Swift_SendmailTransport',
                ],
                'meta' => array(
                    'channel' => 'general.SwiftMailer',
                    'icon' => 'fa fa-envelope-o',
                ),
            ),
        ]);
    }

    public function testOnShutdown()
    {
        $logger = $this->getLogger();
        $logger->onShutdown();
        // onShutdown exists & doesn't throw an exception
        self::assertTrue(true);
    }

    public function testTransportStarted()
    {
        $logger = $this->getLogger();
        $event = new Swift_Events_TransportChangeEvent($this->getTransport());
        $logger->transportStarted($event);
        self::assertLogEntries([
            array(
                'method' => 'log',
                'args' => array(
                    '++:',
                    'Swift_SendmailTransport started',
                ),
                'meta' => array(
                    'channel' => 'general.SwiftMailer',
                    'icon' => 'fa fa-envelope-o',
                ),
            ),
        ]);
    }

    public function testTransportStopped()
    {
        $logger = $this->getLogger();
        $event = new Swift_Events_TransportChangeEvent($this->getTransport());
        $logger->transportStopped($event);
        self::assertLogEntries([
            array(
                'method' => 'log',
                'args' => [
                    '--:',
                    'Swift_SendmailTransport stopped',
                ],
                'meta' => array(
                    'channel' => 'general.SwiftMailer',
                    'icon' => 'fa fa-envelope-o',
                ),
            ),
        ]);
    }

    public function testExceptionThrown()
    {
        $this->expectException('Swift_TransportException');
        $this->expectExceptionMessage('exception messsage');
        $logger = $this->getLogger();
        $event = new Swift_Events_TransportExceptionEvent($this->getTransport(), new Swift_TransportException('exception messsage'));
        $logger->exceptionThrown($event);
    }
}

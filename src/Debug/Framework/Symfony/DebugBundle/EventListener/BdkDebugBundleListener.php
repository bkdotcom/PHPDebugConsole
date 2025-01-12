<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Framework\Symfony\DebugBundle\EventListener;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Collector\DoctrineLogger;
use bdk\Debug\LogEntry;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * WebDebugToolbarListener injects the Web Debug Toolbar.
 */
class BdkDebugBundleListener implements EventSubscriberInterface
{
    protected $mode;
    private $debug;
    private $debugCfg = array(
        'channels' => array(
            'event' => array(
                'channelIcon' => ':event:',
                'channelShow' => false,
            ),
            'request' => array(
                'channelIcon' => ':request:',
            ),
            'security' => array(
                'channelIcon' => ':security:',
            ),
        ),
        'css' => '.debug .empty {border:none; padding:inherit;}',
        'logFiles' => array(
            'filesExclude' => [
                '/var/cache/',
                '/vendor/',
            ],
        ),
        'routeNonHtml' => null,
        'routeServerLog' => array(
            'logDir' => null,
        ),
    );

    /**
     * Constructor
     *
     * @param Debug            $debug            Debug instance
     * @param Kernel           $kernel           Kernel instance
     * @param DoctrineRegistry $doctrineRegistry Doctrine Registry
     */
    public function __construct(Debug $debug, Kernel $kernel, DoctrineRegistry $doctrineRegistry)
    {
        $this->debug = $debug;
        $this->debug->errorHandler->register();
        $this->debugCfg['routeServerLog']['logDir'] = $kernel->getLogDir();

        // doctrine v3.2 added setMiddlewares
        // doctrine v3.3 added AbstractConnectionMiddleware (and other abstract classes)
        // doctrine v3.4 deprecated SqlLogger
        $doctrineSupportsMiddleware = \method_exists('Doctrine\DBAL\Configuration', 'setMiddlewares')
            && \class_exists('Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware');
        if ($doctrineSupportsMiddleware === false) {
            $connections = $doctrineRegistry->getConnections();
            foreach ($connections as $conn) {
                $logger = new DoctrineLogger($conn);
                $conn->getConfiguration()->setSQLLogger($logger);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', PHP_INT_MAX],
            KernelEvents::RESPONSE => ['onKernelResponse', -128],
        ];
    }

    /**
     * onKernelRequest
     *
     * @param RequestEvent $event RequestEvent
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event)
    {
        $methodName = \method_exists($event, 'isMainRequest')
            ? 'isMainRequest'
            : 'isMasterRequest'; // isMasterRequest deprecated in symfony/http-kernel 5.3
        if (!$event->{$methodName}()) {
            return;
        }
        $this->debug->groupSummary();
        $this->debug->info('Symfony version', \Symfony\Component\HttpKernel\Kernel::VERSION);
        $this->debug->groupEnd();
        $this->debug->setCfg($this->debugCfg, Debug::CONFIG_NO_RETURN);
        $this->debug->eventManager->subscribe(Debug::EVENT_LOG, [$this, 'onDebugLog']);
        $this->debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_START, [$this, 'onObjAbstractStart']);
    }

    /**
     * Debug::EVENT_LOG event listener
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function onDebugLog(LogEntry $logEntry)
    {
        $channelName = $logEntry->getSubject()->getCfg('channelName');
        if ($channelName === 'general.doctrine') {
            $logEntry['appendLog'] = false;
            return;
        }
        if ($channelName === 'general.event') {
            $this->onDebugLogGeneralEvent($logEntry);
        }
        if ($logEntry->getMeta('psr3level') !== LogLevel::CRITICAL) {
            return;
        }
        /*
            test if came via debug's errorHandler
            if so, already logged
        */
        $lastError = $logEntry->getSubject()->errorHandler->getLastError();
        if ($lastError) {
            $str = $this->debug->stringUtil->interpolate(
                '"{typeStr}: {message}" at {file} line {line}',
                $lastError
            );
            if (\strpos($logEntry['args'][0], $str) !== false) {
                $logEntry['appendLog'] = false;
            }
        }
    }

    /**
     * onKernelResponse
     *
     * @param ResponseEvent $event ResponseEvent
     *
     * @return void
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        $methodName = \method_exists($event, 'isMainRequest')
            ? 'isMainRequest'
            : 'isMasterRequest'; // isMasterRequest deprecated in symfony/http-kernel 5.3
        if (!$event->{$methodName}()) {
            return;
        }

        // do not capture redirects or modify XML HTTP Requests
        if ($request->isXmlHttpRequest()) {
            return;
        }

        if ($this->isResponseHtml($event) === false) {
            return;
        }

        $this->debug->writeToResponse($response);
    }

    /**
     * debug.objAbstractStart listener
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    public function onObjAbstractStart(Abstraction $abs)
    {
        if ($abs['debugMethod'] === 'error' && $abs['className'] === 'ErrorException') {
            $abs['isExcluded'] = true;
        }
    }

    /**
     * Is response HTML?
     *
     * @param ResponseEvent $event ResponseEvent
     *
     * @return bool
     */
    private function isResponseHtml(ResponseEvent $event)
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        $responseTypeIsHtml = $response->headers->has('Content-Type') === false || \strpos($response->headers->get('Content-Type'), 'html') !== false;
        return ($responseTypeIsHtml || $request->getRequestFormat() === 'html')
            && \stripos((string) $response->headers->get('Content-Disposition'), 'attachment;') === false;
    }

    /**
     * Handle 'general.event' logEntry
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function onDebugLogGeneralEvent(LogEntry $logEntry)
    {
        if (\preg_match('/^Notified event "(?P<event>[^"]+)" to listener "(?P<listener>.+)"/', $logEntry['args'][0], $matches)) {
            $logEntry['args'] = [
                'Notified event "%s" to listener %s',
                $matches['event'],
                new Abstraction(Type::TYPE_IDENTIFIER, array(
                    'typeMore' => Type::TYPE_IDENTIFIER_METHOD,
                    'value' => $matches['listener'],
                )),
            ];
        }
    }
}

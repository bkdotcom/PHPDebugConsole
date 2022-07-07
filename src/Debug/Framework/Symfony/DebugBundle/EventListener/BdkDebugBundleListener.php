<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Framework\Symfony\DebugBundle\EventListener;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Collector\DoctrineLogger;
use bdk\Debug\LogEntry;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
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
                'channelIcon' => 'fa fa-bell-o',
                'channelShow' => false,
            ),
            'request' => array(
                'channelIcon' => 'fa fa-arrow-left',
            ),
            'security' => array(
                'channelIcon' => 'fa fa-shield',
            ),
        ),
        'css' => '.debug .empty {border:none; padding:inherit;}',
        'logFiles' => array(
            'filesExclude' => array(
                '/var/cache/',
                '/vendor/',
            ),
        ),
    );

    /**
     * Constructor
     *
     * @param Debug            $debug            Debug instance
     * @param DoctrineRegistry $doctrineRegistry Doctrine Regsitry
     */
    public function __construct(Debug $debug, DoctrineRegistry $doctrineRegistry)
    {
        $this->debug = $debug;
        $this->debug->errorHandler->register();

        $connections = $doctrineRegistry->getConnections();
        foreach ($connections as $conn) {
            $logger = new DoctrineLogger($conn);
            $conn->getConfiguration()->setSQLLogger($logger);
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
        if (!$event->isMasterRequest()) {
            return;
        }
        $this->debug->setCfg($this->debugCfg);
        $this->debug->eventManager->subscribe('debug.log', array($this, 'onDebugLog'));
        $this->debug->eventManager->subscribe('debug.objAbstractStart', array($this, 'onObjAbstractStart'));
    }

    /**
     * php.debug event listener
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function onDebugLog(LogEntry $logEntry)
    {
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

        if (!$event->isMasterRequest()) {
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
            && \stripos($response->headers->get('Content-Disposition'), 'attachment;') === false;
    }
}

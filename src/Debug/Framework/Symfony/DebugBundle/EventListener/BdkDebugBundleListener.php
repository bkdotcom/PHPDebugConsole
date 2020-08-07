<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $this->debug->setCfg(array(
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
        ));
        $this->debug->eventManager->subscribe('debug.log', array($this, 'onDebugLog'));
        $this->debug->eventManager->subscribe('debug.objAbstractStart', array($this, 'onObjAbstractStart'));
        $this->debug->eventManager->subscribe('debug.output', array($this, 'logFiles'), 1);
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
        if ($logEntry->getMeta('psr3level') === LogLevel::CRITICAL) {
            /*
                test if came via debug's errorHandler
                if so, already logged
            */
            $lastError = $logEntry->getSubject()->errorHandler->getLastError();
            if ($lastError) {
                $str = $this->debug->utility->strInterpolate(
                    '"{typeStr}: {message}" at {file} line {line}',
                    $lastError
                );
                if (\strpos($logEntry['args'][0], $str) !== false) {
                    $logEntry['appendLog'] = false;
                }
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

        if (
            ($response->headers->has('Content-Type') && \strpos($response->headers->get('Content-Type'), 'html') === false)
            || $request->getRequestFormat() !== 'html'
            || \stripos($response->headers->get('Content-Disposition'), 'attachment;') !== false
        ) {
            return;
        }

        $this->injectDebug($response, $request);
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
     * Log included files in new tab
     *
     * @return void
     */
    public function logFiles()
    {
        $files = $this->debug->utility->getIncludedFiles();
        $files = \array_filter($files, function ($file) {
            $exclude = array(
                '/var/cache/',
                '/vendor/',
            );
            foreach ($exclude as $str) {
                if (\strpos($file, $str) !== false) {
                    return false;
                }
            }
            return true;
        });
        $files = \array_values($files);
        $debugFiles = $this->debug->rootInstance->getChannel('Files', array('nested' => false));
        $debugFiles->log('files', $files, $this->debug->meta('detectFiles', true));
    }

    /**
     * Injects the web debug toolbar into the given Response.
     *
     * @param Response $response Response instance
     * @param Request  $request  Request instance
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function injectDebug(Response $response, Request $request)
    {
        $content = $response->getContent();
        $pos = \strripos($content, '</body>');
        if ($pos === false) {
            return;
        }
        $this->debug->alert('injected into response via ' . __CLASS__, 'success');
        $content = \substr($content, 0, $pos)
            . $this->debug->output()
            . \substr($content, $pos);
        $response->setContent($content);
    }
}

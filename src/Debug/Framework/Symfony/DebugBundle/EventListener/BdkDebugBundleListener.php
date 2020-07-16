<?php

namespace bdk\Debug\Framework\Symfony\DebugBundle\EventListener;

use bdk\Debug;
use bdk\PubSub\Event;
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
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * {@ineritdoc}
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
     * @param ResponseEvent $event ResponseEvent
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        $this->debug->setCfg(array(
            'collect' => true,
            'output' => true,
            'stream' => __DIR__ . '/../log.txt',
            'channels' => array(
                'doctrine' => array(
                    'channelIcon' => 'fa fa-database',
                ),
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
        ));
        $this->debug->eventManager->subscribe('debug.output', array($this, 'logFiles'), 1);
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
     * Injects the web debug toolbar into the given Response.
     *
     * @param Response $response Response instance
     * @param Request  $request  Request instance
     *
     * @return void
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

    /**
     * Log included files in new tab
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function logFiles(Event $event)
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
}

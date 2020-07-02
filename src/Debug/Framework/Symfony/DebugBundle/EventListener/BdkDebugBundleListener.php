<?php

namespace bdk\Debug\Framework\Symfony\DebugBundle\EventListener;

use bdk\Debug;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
            KernelEvents::RESPONSE => ['onKernelResponse', -128],
        ];
    }

    /**
     * [onKernelResponse description]
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
     * @param Response $response
     * @param REequest $request
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
}

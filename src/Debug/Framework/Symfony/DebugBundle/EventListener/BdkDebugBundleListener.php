<?php

namespace bdk\Debug\Framework\Symfony\DebugBundle\EventListener;

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

    public function __construct()
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -128],
        ];
    }

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

        $this->injectToolbar($response, $request);
    }

    /**
     * Injects the web debug toolbar into the given Response.
     */
    protected function injectToolbar(Response $response, Request $request)
    {
        $content = $response->getContent();
        $pos = \strripos($content, '</body>');

        if ($pos === false) {
            return;
        }
        $content = \substr($content, 0, $pos)
            . \bdk\Debug::_output()
            . \substr($content, $pos);
        $response->setContent($content);
    }
}

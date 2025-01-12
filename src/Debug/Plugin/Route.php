<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\Debug\Route\RouteInterface;
use bdk\HttpMessage\Utility\ContentType;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Handle "auto" route
 */
class Route extends AbstractComponent implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var string[] */
    protected $methods = [
        'getDefaultRoute',
    ];

    /** @var bool */
    private $isBootstrapped = false;

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_BOOTSTRAP => ['onBootstrap', PHP_INT_MAX * -1],
            Debug::EVENT_CONFIG => 'onConfig',
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
            Debug::EVENT_OUTPUT => ['onOutput', PHP_INT_MAX],
        );
    }

    /**
     * Determine default route
     *
     * @return string
     */
    public function getDefaultRoute()
    {
        $interface = $this->debug->rootInstance->getInterface();
        if (\strpos($interface, 'ajax') !== false) {
            return $this->debug->getCfg('routeNonHtml', Debug::CONFIG_DEBUG);
        }
        if ($interface === 'http') {
            $contentType = $this->debug->rootInstance->getResponseHeader('Content-Type');
            if ($contentType && \strpos($contentType, ContentType::HTML) === false) {
                return $this->debug->getCfg('routeNonHtml', Debug::CONFIG_DEBUG);
            }
            return 'html';
        }
        return 'stream';
    }

    /**
     * Debug::EVENT_BOOTSTRAP subscriber
     *
     * @param Event $event Debug::EVENT_BOOTSTRAP Event instance
     *
     * @return void
     */
    public function onBootstrap(Event $event)
    {
        $this->isBootstrapped = true;
        $debug = $event->getSubject();
        if ($debug->parentInstance) {
            return;
        }
        // this is the root instance
        $route = $debug->getCfg('route');
        if ($route === 'stream') {
            // normally we don't init the route until output
            // but stream needs to begin listening now
            $debug->setCfg('route', $route, Debug::CONFIG_NO_RETURN);
        }
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $this->debug = $event->getSubject();
        $cfg = $event['debug'];
        if (!$cfg) {
            return;
        }
        $valActions = array(
            'route' => [$this, 'onCfgRoute'],
        );
        $valActions = \array_intersect_key($valActions, $cfg);
        foreach ($valActions as $key => $callable) {
            /** @psalm-suppress TooManyArguments */
            $cfg[$key] = $callable($cfg[$key]);
        }
        $event['debug'] = $cfg;
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * If we're the target channel, check if configured route is a string or obj
     * if string, reset the route... which will instantiate the route
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onOutput(Event $event)
    {
        $debug = $event->getSubject();
        $route = $debug->getCfg('route', Debug::CONFIG_DEBUG);
        if (\is_string($route)) {
            // Route is string.  Set it to set route
            $debug->setCfg('route', $route, Debug::CONFIG_NO_RETURN);
        }
    }

    /**
     * If "core" route, store in lazyObjects property
     *
     * @param mixed $val route value
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgRoute($val)
    {
        $routePrev = null;
        if ($this->isBootstrapped) {
            /*
                Only need to worry about previous route if we've bootstrapped
            */
            $routePrev = $this->debug->getCfg('route');
        }
        if (\is_object($routePrev)) {
            /*
                Unsubscribe current route
                There can only be one 'route' at a time:
                If multiple output routes are desired, use debug->addPlugin()
            */
            $this->debug->removePlugin($routePrev);
        }
        if (\is_string($val) && $val !== 'auto') {
            $val = $this->debug->getRoute($val);
        }
        if ($val instanceof RouteInterface) {
            $this->debug->addPlugin($val);
        }
        return $val;
    }
}

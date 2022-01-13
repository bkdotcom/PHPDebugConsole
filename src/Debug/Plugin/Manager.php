<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\AssetProviderInterface;
use bdk\Debug\LogEntry;
use bdk\Debug\Route\RouteInterface;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use SplObjectStorage;

/**
 * Plugin management
 */
class Manager implements SubscriberInterface
{
    private $debug;
    /** @var SplObjectStorage */
    protected $registeredPlugins;
    protected $methods = array(
        'addPlugin',
        'removePlugin',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->registeredPlugins = new SplObjectStorage();
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
        );
    }

    /**
     * Debug::EVENT_LOG event subscriber
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return void
     */
    public function onCustomMethod(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        if (!\in_array($method, $this->methods)) {
            return;
        }
        $this->debug = $logEntry->getSubject();
        $logEntry['handled'] = true;
        $logEntry['return'] = \call_user_func_array(array($this, $method), $logEntry['args']);
        $logEntry->stopPropagation();
    }

    /**
     * Extend debug with a plugin
     *
     * @param AssetProviderInterface|SubscriberInterface $plugin object implementing SubscriberInterface and/or AssetProviderInterface
     *
     * @return Debug
     * @throws InvalidArgumentException
     */
    public function addPlugin($plugin)
    {
        $this->assertPlugin($plugin);
        if ($this->registeredPlugins->contains($plugin)) {
            return $this->debug;
        }
        if ($plugin instanceof AssetProviderInterface) {
            $this->debug->rootInstance->getRoute('html')->addAssetProvider($plugin);
        }
        if ($plugin instanceof SubscriberInterface) {
            $this->addSubscriberInterface($plugin);
        }
        if ($plugin instanceof RouteInterface) {
            $refMethod = new \ReflectionMethod($this->debug, 'onCfgRoute');
            $refMethod->setAccessible(true);
            $refMethod->invoke($this->debug, $plugin, false);
        }
        $this->registeredPlugins->attach($plugin);
        return $this->debug;
    }

    /**
     * Remove plugin
     *
     * @param SubscriberInterface $plugin object implementing SubscriberInterface
     *
     * @return $this
     */
    public function removePlugin(SubscriberInterface $plugin)
    {
        $this->registeredPlugins->detach($plugin);
        if ($plugin instanceof AssetProviderInterface) {
            $this->debug->rootInstance->getRoute('html')->removeAssetProvider($plugin);
        }
        if ($plugin instanceof SubscriberInterface) {
            $this->debug->eventManager->RemoveSubscriberInterface($plugin);
        }
        return $this;
    }

    /**
     * Add SubscriberInterface plugin
     *
     * @param SubscriberInterface $plugin SubscriberInterface instance
     *
     * @return void
     */
    private function addSubscriberInterface(SubscriberInterface $plugin)
    {
        $this->debug->eventManager->addSubscriberInterface($plugin);
        $subscriptions = $plugin->getSubscriptions();
        if (isset($subscriptions[Debug::EVENT_PLUGIN_INIT])) {
            /*
                plugin we just added subscribes to Debug::EVENT_PLUGIN_INIT
                call subscriber directly
            */
            \call_user_func(
                array($plugin, $subscriptions[Debug::EVENT_PLUGIN_INIT]),
                new Event($this->debug),
                Debug::EVENT_PLUGIN_INIT,
                $this->debug->eventManager
            );
        }
    }

    /**
     * Validate plugin
     *
     * @param AssetProviderInterface|SubscriberInterface $plugin PHPDebugCpnsole plugin
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertPlugin($plugin)
    {
        $isPlugin = false;
        if ($plugin instanceof AssetProviderInterface) {
            $isPlugin = true;
        }
        if ($plugin instanceof SubscriberInterface) {
            $isPlugin = true;
        }
        if (!$isPlugin) {
            $type = \is_object($plugin)
                ? \get_class($plugin)
                : \gettype($plugin);
            throw new InvalidArgumentException('addPlugin expects \\bdk\\Debug\\AssetProviderInterface and/or \\bdk\\PubSub\\SubscriberInterface.  ' . $type . ' provided');
        }
    }
}

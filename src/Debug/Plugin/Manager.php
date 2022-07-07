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

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\AssetProviderInterface;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\Debug\Route\RouteInterface;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use InvalidArgumentException;
use SplObjectStorage;

/**
 * Plugin management
 */
class Manager implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var SplObjectStorage */
    protected $registeredPlugins;
    protected $methods = array(
        'addPlugin',
        'hasPlugin',
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
        if ($this->hasPlugin($plugin)) {
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
     * Test if we already have plugin
     *
     * @param SubscriberInterface $plugin Plugin to check
     *
     * @return bool
     */
    public function hasPlugin($plugin)
    {
        $this->assertPlugin($plugin);
        return $this->registeredPlugins->contains($plugin);
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
            $this->debug->eventManager->removeSubscriberInterface($plugin);
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
        if ($plugin instanceof AssetProviderInterface) {
            return;
        }
        if ($plugin instanceof SubscriberInterface) {
            return;
        }
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $type = \is_object($plugin)
            ? \get_class($plugin)
            : \gettype($plugin);
        throw new InvalidArgumentException($backtrace[1]['function'] . ' expects \\bdk\\Debug\\AssetProviderInterface and/or \\bdk\\PubSub\\SubscriberInterface.  ' . $type . ' provided');
    }
}

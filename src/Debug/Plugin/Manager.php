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

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\AssetProviderInterface;
use bdk\Debug\ConfigurableInterface;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\Debug\PluginInterface;
use bdk\Debug\Route\RouteInterface;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use Closure;
use InvalidArgumentException;
use OutOfBoundsException;
use SplObjectStorage;

/**
 * Plugin management
 */
class Manager implements SubscriberInterface, PluginInterface
{
    use CustomMethodTrait;

    /** @var string[] */
    protected $methods = [
        'addPlugin',
        'addPlugins',
        'hasPlugin',
        'getPlugin',
        'removePlugin',
    ];

    /** @var SplObjectStorage */
    protected $registeredPlugins;

    /** @var array<non-empty-string,AssetProviderInterface|SubscriberInterface> */
    protected $namedPlugins = array();

    /** @var bool */
    private $isBootstrapped = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->registeredPlugins = new SplObjectStorage();
    }

    /**
     * Extend debug with a plugin
     *
     * @param AssetProviderInterface|SubscriberInterface $plugin Object implementing SubscriberInterface and/or AssetProviderInterface
     * @param string                                     $name   Optional plugin name
     *
     * @return Debug
     * @throws InvalidArgumentException
     */
    public function addPlugin($plugin, $name = null)
    {
        $this->assertPlugin($plugin);
        if ($this->hasPlugin($plugin)) {
            return $this->debug;
        }
        if ($plugin instanceof PluginInterface) {
            $plugin->setDebug($this->debug);
        }
        if ($plugin instanceof AssetProviderInterface) {
            $this->debug->rootInstance->getRoute('html')->addAssetProvider($plugin);
        }
        if ($plugin instanceof SubscriberInterface) {
            $this->addSubscriberInterface($plugin);
        }
        if ($plugin instanceof RouteInterface) {
            $this->addRouteInterface($plugin);
        }
        if (\is_string($name)) {
            $this->namedPlugins[$name] = $plugin;
        }
        $this->registeredPlugins->attach($plugin);
        return $this->debug;
    }

    /**
     * Add plugins defined in configuration
     *
     * @param array $plugins List of plugins and/or plugin-definitions
     *
     * @return Debug
     * @throws InvalidArgumentException
     */
    public function addPlugins(array $plugins)
    {
        \array_walk($plugins, function ($plugin, $key) {
            try {
                $plugin = $this->instantiatePlugin($plugin);
                $this->addPlugin($plugin, $key);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException(\sprintf('plugins[%s]: %s', $key, $e->getMessage()));
            }
        });
        return $this->debug;
    }

    /**
     * Get all registered Plugins
     *
     * @return array
     */
    public function getPlugins()
    {
        $plugins = array();
        $this->registeredPlugins->rewind();
        while ($this->registeredPlugins->valid()) {
            $plugin = $this->registeredPlugins->current();
            $pluginName = \array_search($plugin, $this->namedPlugins, true);
            $this->registeredPlugins->next();
            if ($pluginName === false) {
                $plugins[] = $plugin;
                continue;
            }
            $plugins[$pluginName] = $plugin;
        }
        \ksort($plugins);
        return $plugins;
    }

    /**
     * Get plugin by name
     *
     * @param string $pluginName Plugin name
     *
     * @return AssetProviderInterface|SubscriberInterface
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    public function getPlugin($pluginName)
    {
        if (\is_string($pluginName) === false) {
            throw new InvalidArgumentException(\sprintf(
                'getPlugin expects a string. %s provided',
                $this->debug->php->getDebugType($pluginName)
            ));
        }
        if ($this->hasPlugin($pluginName) === false) {
            throw new OutOfBoundsException(\sprintf(
                'getPlugin(%s) - no such plugin',
                $pluginName
            ));
        }
        return $this->namedPlugins[$pluginName];
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
        );
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
        if (\is_string($plugin)) {
            return isset($this->namedPlugins[$plugin]);
        }
        $this->assertPlugin($plugin);
        return $this->registeredPlugins->contains($plugin);
    }

    /**
     * Debug::EVENT_BOOTSTRAP subscriber
     *
     * @return void
     */
    public function onBootstrap()
    {
        $this->isBootstrapped = true;
    }

    /**
     * Remove plugin
     *
     * @param string|SubscriberInterface $plugin Plugin name or object implementing SubscriberInterface
     *
     * @return Debug
     * @throws InvalidArgumentException
     */
    public function removePlugin($plugin)
    {
        if (\is_string($plugin)) {
            $plugin = $this->findPluginByName($plugin);
        } elseif (\is_object($plugin) === false) {
            throw new InvalidArgumentException(\sprintf(
                '%s expects %s.  %s provided',
                __FUNCTION__,
                'plugin name or object',
                $this->debug->php->getDebugType($plugin)
            ));
        }
        if ($plugin === false) {
            return $this->debug;
        }
        $pluginName = \array_search($plugin, $this->namedPlugins, true);
        if ($pluginName !== false) {
            unset($this->namedPlugins[$pluginName]);
        }
        $this->registeredPlugins->detach($plugin);
        if ($plugin instanceof AssetProviderInterface) {
            $this->debug->rootInstance->getRoute('html')->removeAssetProvider($plugin);
        }
        if ($plugin instanceof SubscriberInterface) {
            $this->debug->eventManager->removeSubscriberInterface($plugin);
        }
        return $this->debug;
    }

    /**
     * {@inheritDoc}
     */
    public function setDebug(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Add RouteInterface plugin
     *
     * @param RouteInterface $route RouteInterface instance
     *
     * @return void
     */
    private function addRouteInterface(RouteInterface $route)
    {
        $classname = \get_class($route);
        $prefix = 'bdk\\Debug\\Route\\';
        $containerName = 'route' . \substr($classname, \strlen($prefix));
        if (\strpos($classname, $prefix) === 0 && isset($this->debug->{$containerName}) === false) {
            $this->debug->setCfg('serviceProvider', array(
                $containerName => $route,
            ), Debug::CONFIG_NO_RETURN);
        }
        if ($route->appendsHeaders()) {
            $this->debug->obStart();
        }
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
        $subscriptions = $plugin->getSubscriptions();
        if (isset($subscriptions[Debug::EVENT_PLUGIN_INIT])) {
            /*
                plugin subscribes to Debug::EVENT_PLUGIN_INIT
                call subscriber directly
            */
            \call_user_func(
                $this->getSubscriberCallable($plugin, $subscriptions[Debug::EVENT_PLUGIN_INIT]),
                new Event($this->debug),
                Debug::EVENT_PLUGIN_INIT,
                $this->debug->eventManager
            );
        }
        if (isset($subscriptions[Debug::EVENT_BOOTSTRAP]) && $this->isBootstrapped) {
            /*
                plugin subscribes to Debug::EVENT_BOOTSTRAP
                and we've already bootstrapped
            */
            \call_user_func(
                $this->getSubscriberCallable($plugin, $subscriptions[Debug::EVENT_BOOTSTRAP]),
                new Event($this->debug),
                Debug::EVENT_BOOTSTRAP,
                $this->debug->eventManager
            );
        }
        $this->debug->eventManager->addSubscriberInterface($plugin);
    }

    /**
     * Validate plugin
     *
     * @param AssetProviderInterface|SubscriberInterface $plugin PHPDebugConsole plugin
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
        throw new InvalidArgumentException(\sprintf(
            '%s expects %s.  %s provided',
            $backtrace[1]['function'],
            '\\bdk\\Debug\\AssetProviderInterface and/or \\bdk\\PubSub\\SubscriberInterface',
            $this->debug->php->getDebugType($plugin)
        ));
    }

    /**
     * Remove plugin by name
     *
     * @param string $pluginName Plugin name
     *
     * @return AssetProviderInterface|SubscriberInterface|false The removed plugin instance, or false
     */
    private function findPluginByName($pluginName)
    {
        return isset($this->namedPlugins[$pluginName])
            ? $this->namedPlugins[$pluginName]
            : false;
    }

    /**
     * Instantiate plugin
     *
     * @param object|array|classname $plugin Plugin info
     *
     * @return object
     *
     * @throws InvalidArgumentException
     */
    private function instantiatePlugin($plugin)
    {
        $cfg = array();
        if (\is_string($plugin)) {
            $plugin = array('class' => $plugin);
        }
        if (\is_array($plugin)) {
            $cfg = $plugin;
            if (empty($cfg['class'])) {
                throw new InvalidArgumentException(\sprintf('missing "class" value'));
            }
            $class = $cfg['class'];
            $plugin = new $class();
            unset($cfg['class']);
        }
        if ($plugin instanceof ConfigurableInterface && !empty($cfg)) {
            $plugin->setCfg($cfg);
        }
        return $plugin;
    }

    /**
     * Determine callable from raw SubscriberInterface::getSubscribers  return value
     *
     * @param SubscriberInterface $plugin SubscriberInterface instance
     * @param mixed               $mixed  Closure or method name (array not yet supported)
     *
     * @return callable
     */
    private function getSubscriberCallable(SubscriberInterface $plugin, $mixed)
    {
        return $mixed instanceof Closure
            ? $mixed
            : [$plugin, $mixed];
    }
}

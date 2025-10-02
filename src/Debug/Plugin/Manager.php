<?php

/**
 * @package   bdk/debug
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
class Manager implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var string[] */
    protected $methods = [
        'addPlugin',
        'addPlugins',
        'getAssetProviders',
        'getPlugin',
        'hasPlugin',
        'removePlugin',
    ];

    /** @var array<non-empty-string,AssetProviderInterface|SubscriberInterface> */
    protected $namedPlugins = array();

    /** @var SplObjectStorage */
    protected $registeredPlugins;

    /** @var bool */
    private $isBootstrapped = false;

    /** @var array */
    private $pluginCfg = array();

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
        // we don't check for RouteInterface (it extends SubscriberInterface)
        \bdk\Debug\Utility\PhpType::assertType($plugin, 'bdk\Debug\AssetProviderInterface|bdk\PubSub\SubscriberInterface', 'plugin');
        if ($this->hasPlugin($plugin)) {
            return $this->debug;
        }
        if ($plugin instanceof PluginInterface) {
            $this->addPluginInterface($plugin);
        }
        if ($plugin instanceof AssetProviderInterface) {
            $this->debug->assetManager->addProvider($plugin);
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
                list($plugin, $this->pluginCfg) = $this->instantiatePlugin($plugin);
                $this->addPlugin($plugin, $key);
                $this->pluginCfg = array();
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
        $numericIndex = 0;
        while ($this->registeredPlugins->valid()) {
            $plugin = $this->registeredPlugins->current();
            $index = \array_search($plugin, $this->namedPlugins, true) ?: $numericIndex++;
            $this->registeredPlugins->next();
            $plugins[$index] = $plugin;
        }
        \ksort($plugins);
        return $plugins;
    }

    /**
     * Get all registered asset providers
     * Clears the enqueued asset providers
     *
     * @return AssetProviderInterface[]
     *
     * @deprecated 3.5
     */
    public function getAssetProviders()
    {
        return $this->debug->assetManager->getProviders();
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
            throw new InvalidArgumentException($this->debug->i18n->trans('exception.method-expects', array(
                'actual' => $this->debug->php->getDebugType($pluginName),
                'expect' => 'string',
                'method' => __METHOD__ . '()',
            )));
        }
        if ($this->hasPlugin($pluginName) === false) {
            throw new OutOfBoundsException(__METHOD__ . '(' . $pluginName . ') - ' . $this->debug->i18n->trans('plugin.not-exist'));
        }
        return $this->namedPlugins[$pluginName];
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_BOOTSTRAP => function () {
                $this->isBootstrapped = true;
            },
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
        );
    }

    /**
     * Test if we already have plugin
     *
     * @param string|AssetProviderInterface|SubscriberInterface $plugin Plugin to check
     *
     * @return bool
     */
    public function hasPlugin($plugin)
    {
        if (\is_string($plugin)) {
            return isset($this->namedPlugins[$plugin]);
        }
        // we don't check for RouteInterface (it extends SubscriberInterface)
        \bdk\Debug\Utility\PhpType::assertType($plugin, 'string|bdk\Debug\AssetProviderInterface|bdk\PubSub\SubscriberInterface');
        return $this->registeredPlugins->contains($plugin);
    }

    /**
     * Remove plugin
     *
     * @param string|AssetProviderInterface|SubscriberInterface $plugin Plugin name or object implementing SubscriberInterface
     *
     * @return Debug
     * @throws InvalidArgumentException
     */
    public function removePlugin($plugin)
    {
        if (\is_string($plugin)) {
            $plugin = $this->findPluginByName($plugin);
        }
        if ($plugin === false) {
            return $this->debug;
        }
        // we don't check for RouteInterface (it extends SubscriberInterface)
        \bdk\Debug\Utility\PhpType::assertType($plugin, 'string|bdk\Debug\AssetProviderInterface|bdk\PubSub\SubscriberInterface');
        $pluginName = \array_search($plugin, $this->namedPlugins, true);
        if ($pluginName !== false) {
            unset($this->namedPlugins[$pluginName]);
        }
        $this->registeredPlugins->detach($plugin);
        if ($plugin instanceof AssetProviderInterface) {
            $this->debug->assetManager->removeProvider($plugin);
        }
        if ($plugin instanceof SubscriberInterface) {
            $this->debug->eventManager->removeSubscriberInterface($plugin);
        }
        return $this->debug;
    }

    /**
     * Handle plugin implementing PluginInterface
     *
     * @param PluginInterface $plugin PluginInterface instance
     *
     * @return void
     */
    private function addPluginInterface(PluginInterface $plugin)
    {
        $this->debug->warn(
            $this->debug->i18n->trans('deprecated.pluginInterface', array(
                'class' => \get_class($plugin),
            )),
            $this->debug->meta(array(
                'file' => null,
                'line' => null,
            ))
        );
        $plugin->setDebug($this->debug);
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
            $this->callPluginEventSubscriber($plugin, Debug::EVENT_PLUGIN_INIT, $subscriptions[Debug::EVENT_PLUGIN_INIT], $this->pluginCfg);
        }
        if (isset($subscriptions[Debug::EVENT_BOOTSTRAP]) && $this->isBootstrapped) {
            /*
                plugin subscribes to Debug::EVENT_BOOTSTRAP
                and we've already bootstrapped
            */
            $this->callPluginEventSubscriber($plugin, Debug::EVENT_BOOTSTRAP, $subscriptions[Debug::EVENT_BOOTSTRAP]);
        }
        $this->debug->eventManager->addSubscriberInterface($plugin);
    }

    /**
     * Call plugin's event subscriber directly
     *
     * @param SubscriberInterface $plugin      SubscriberInterface instance
     * @param string              $eventName   Event name (Debug::EVENT_PLUGIN_INIT or Debug::EVENT_BOOTSTRAP)
     * @param mixed               $callable    closure or method name
     * @param array               $eventValues Event values
     *
     * @return void
     */
    private function callPluginEventSubscriber(SubscriberInterface $plugin, $eventName, $callable, array $eventValues = array())
    {
        \call_user_func(
            $callable instanceof Closure
                ? $callable
                : [$plugin, $callable],
            new Event($this->debug, $eventValues),
            $eventName,
            $this->debug->eventManager
        );
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
     * @return array plugin instance and config
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
                throw new InvalidArgumentException($this->debug->i18n->trans('plugin.missing-class'));
            }
            $plugin = $this->debug->container->getObject($cfg['class'], false);
            unset($cfg['class']);
        }
        if ($plugin instanceof ConfigurableInterface && !empty($cfg)) {
            $plugin->setCfg($cfg);
        }
        return [$plugin, $cfg];
    }
}

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

namespace bdk\Debug;

use bdk\Container;
use bdk\Container\ServiceProviderInterface;
use bdk\Container\Utility as ContainerUtility;
use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\ServiceProvider;
use bdk\PubSub\Event;

/**
 * Handle underlying Debug bootstrapping and config
 *
 * @psalm-consistent-constructor
 */
abstract class AbstractDebug
{
    /** @var array<string,mixed> */
    protected $cfg = array();

    /** @var bool */
    protected $bootstrapped = false;

    /** @var \bdk\Debug\Config */
    protected $config;

    /** @var Container */
    protected $container;

    /** @var Container */
    protected $serviceContainer;

    /** @var Debug|null */
    protected static $instance;

    /** @var Debug|null */
    protected $parentInstance;

    /** @var Debug */
    protected $rootInstance;

    /** @var list<string> */
    protected $readOnly = [
        'parentInstance',
        'rootInstance',
    ];

    /**
     * Constructor
     *
     * @param array $cfg config
     */
    public function __construct($cfg = array())
    {
        if (!isset(self::$instance)) {
            // self::getInstance() will always return initial/first instance
            self::$instance = $this;
        }
        $this->bootstrap($cfg);
    }

    /**
     * Magic method... inaccessible method called.
     *
     * Try custom method.
     *
     * @param string $methodName Inaccessible method name
     * @param array  $args       Arguments passed to method
     *
     * @return mixed
     */
    public function __call($methodName, array $args)
    {
        $logEntry = new LogEntry($this, $methodName, $args);
        $this->publishBubbleEvent(Debug::EVENT_CUSTOM_METHOD, $logEntry);
        if ($logEntry['handled'] !== true) {
            $logEntry->setMeta('isCustomMethod', true);
            $this->rootInstance->getPlugin('methodBasic')->log($logEntry);
        }
        return $logEntry['return'];
    }

    /**
     * Magic method to allow us to call instance methods statically
     *
     * @param string $methodName Inaccessible method name
     * @param array  $args       Arguments passed to method
     *
     * @return mixed
     */
    public static function __callStatic($methodName, array $args)
    {
        // prior to v3.1 it was required to have underscore prefix to disambiguate from instance method
        //   as of v3.1, all methods provided via plugin
        $methodName = \ltrim($methodName, '_');
        if (!self::$instance && $methodName === 'setCfg') {
            /*
                Treat as a special case
                Want to initialize with the passed config vs initialize, then setCfg
                ie _setCfg(array('route'=>'html')) via command line
                we don't want to first initialize with default STDERR output
            */
            $cfg = \is_array($args[0])
                ? $args[0]
                : array($args[0] => $args[1]);
            new static($cfg);
            return;
        }
        if (!self::$instance) {
            new static();
        }
        return \call_user_func_array([self::$instance, $methodName], $args);
    }

    /**
     * Magic method to get inaccessible / undefined properties
     * Lazy load child classes
     *
     * @param string $property property name
     *
     * @return mixed property value
     */
    public function __get($property)
    {
        if ($this->serviceContainer->has($property)) {
            return $this->serviceContainer[$property];
        }
        if ($this->container->has($property)) {
            return $this->container[$property];
        }
        if (\in_array($property, $this->readOnly, true)) {
            return $this->{$property};
        }
        return null;
    }

    /**
     * Triggered by calling isset() or empty() on inaccessible (protected or private) or non-existing properties
     *
     * @param string $property Property name to test
     *
     * @return bool
     */
    public function __isset($property)
    {
        if ($this->serviceContainer->has($property)) {
            return true;
        }
        if ($this->container->has($property)) {
            return true;
        }
        return \in_array($property, $this->readOnly, true);
    }

    /**
     * Debug::EVENT_CONFIG event listener
     *
     * Since setCfg() passes config through Config, we need a way for Config to pass values back.
     *
     * @param Event $event Debug::EVENT_CONFIG Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event['debug'];
        if (!$cfg || !$event['isTarget']) {
            return;
        }
        $valActions = \array_intersect_key(array(
            'channelIcon' => [$this, 'onCfgChannelIcon'],
            'channels' => [$this, 'onCfgChannels'],
            'logServerKeys' => [$this, 'onCfgLogServerKeys'],
            'serviceProvider' => [$this, 'onCfgServiceProvider'],
        ), $cfg);
        foreach ($valActions as $key => $callable) {
            /** @psalm-suppress TooManyArguments */
            $cfg[$key] = $callable($cfg[$key]);
        }
        $this->cfg = $this->arrayUtil->mergeDeep($this->cfg, $cfg);
        $this->onConfigPropagate($event, $cfg);
    }

    /**
     * Update dependencies
     *
     * @param ServiceProviderInterface|callable|array $val dependency definitions
     *
     * @return array
     */
    public function onCfgServiceProvider($val)
    {
        $rawValues = ContainerUtility::toRawValues($val);
        $services = $this->container['services'];
        foreach ($rawValues as $k => $v) {
            if (\in_array($k, $services, true)) {
                $this->serviceContainer[$k] = $v;
                unset($rawValues[$k]);
                continue;
            }
            $this->container[$k] = $v;
        }
        return $rawValues;
    }

    /**
     * Publish/Trigger/Dispatch event
     * Event will get published on ancestor channels if propagation not stopped
     *
     * @param string     $eventName Event name
     * @param Event      $event     Event instance
     * @param Debug|null $debug     Specify Debug instance to start on.
     *                                If not specified will check if getSubject returns Debug instance
     *                                Fallback: this
     *
     * @return Event
     */
    public function publishBubbleEvent($eventName, Event $event, $debug = null)
    {
        $this->utility->assertType($debug, 'bdk\Debug');
        if ($debug === null) {
            $debug = $event->getSubject();
        }
        if (!($debug instanceof Debug)) {
            $debug = $this;
        }
        do {
            $debug->eventManager->publish($eventName, $event);
            $debug = $debug->parentInstance;
        } while ($debug && !$event->isPropagationStopped());
        return $event;
    }

    /**
     * Initialize container, & config
     *
     * @param array $cfg passed cfg
     *
     * @return void
     */
    private function bootstrap($cfg)
    {
        $cfgBootstrap = $this->bootstrapConfig($cfg);
        $this->bootstrapSetInstances($cfgBootstrap);
        $this->bootstrapContainer($cfgBootstrap);

        $this->config = $this->container['config'];
        $this->container->setCfg('onInvoke', [$this->config, 'onContainerInvoke']);
        $this->serviceContainer->setCfg('onInvoke', [$this->config, 'onContainerInvoke']);
        $this->eventManager->addSubscriberInterface($this->container['pluginManager']);

        if (!$this->parentInstance) {
            // we're the root instance
            $this->serviceContainer['errorHandler'];
            $this->addPlugins($cfgBootstrap['plugins']);
            $this->data->set('requestId', $this->requestId());
            $this->data->set('entryCountInitial', $this->data->get('log/__count__'));
        }

        $this->eventManager->subscribe(Debug::EVENT_CONFIG, [$this, 'onConfig']);
        $this->config->set($cfg, Debug::CONFIG_NO_RETURN);
        $this->eventManager->publish(Debug::EVENT_BOOTSTRAP, $this);
        $this->bootstrapped = true;
    }

    /**
     * Get config values needed for bootstrapping
     *
     * @param array $cfg Config passed to constructor
     *
     * @return array
     */
    private function bootstrapConfig(&$cfg)
    {
        $cfgDefault = array(
            'container' => array(),
            'parent' => null,
            'plugins' => $this->cfg['plugins'],
            'serviceProvider' => $this->cfg['serviceProvider'],
        );

        $cfgValues = array();
        foreach (\array_keys($cfgDefault) as $k) {
            if (isset($cfg['debug'][$k])) {
                $cfgValues[$k] = $cfg['debug'][$k];
            } elseif (isset($cfg[$k])) {
                $cfgValues[$k] = $cfg[$k];
            }
        }

        unset(
            $cfg['debug']['parent'],
            $cfg['debug']['serviceProvider'],
            $cfg['serviceProvider']
        );

        return \array_replace_recursive($cfgDefault, $cfgValues);
    }

    /**
     * Initialize dependency containers
     *
     * @param array $cfg Initial cfg values
     *
     * @return void
     */
    private function bootstrapContainer($cfg)
    {
        $this->container = new Container(
            array(
                'debug' => $this,
            ),
            $cfg['container']
        );
        $this->container->registerProvider(new ServiceProvider());
        if (empty($this->parentInstance)) {
            // root instance
            $this->serviceContainer = new Container(
                array(
                    'debug' => $this,
                ),
                $cfg['container']
            );
            foreach ($this->container['services'] as $service) {
                $this->serviceContainer[$service] = $this->container->raw($service);
                unset($this->container[$service]);
            }
        }
        $this->serviceContainer = $this->rootInstance->serviceContainer;
        $this->cfg['serviceProvider'] = $this->onCfgServiceProvider($cfg['serviceProvider']);
    }

    /**
     * Set instance, rootInstance, & parentInstance
     *
     * @param array $cfg Raw config passed to constructor
     *
     * @return void
     */
    private function bootstrapSetInstances($cfg)
    {
        $this->rootInstance = $this;
        if (isset($cfg['parent'])) {
            $this->parentInstance = $cfg['parent'];
            $this->rootInstance = $this->parentInstance->rootInstance;
        }
    }

    /**
     * Handle "channelIcon" config update
     *
     * @param string|null $val config value
     *
     * @return string|null
     */
    private function onCfgChannelIcon($val)
    {
        if (\preg_match('/^:(.+):$/', (string) $val, $matches)) {
            $val = $this->getCfg('icons.' . $matches[1], Debug::CONFIG_DEBUG);
        }
        return $val;
    }

    /**
     * Handle "channels" config update
     *
     * @param array $val config value
     *
     * @return array
     */
    private function onCfgChannels($val)
    {
        foreach ($val as $channelName => $channelCfg) {
            if ($this->hasChannel($channelName)) {
                $this->getChannel($channelName)->config->set($channelCfg);
                unset($val[$channelName]);
            }
        }
        return $val;
    }

    /**
     * Handle "channelIcon" config update
     *
     * @param string|null $val config value
     *
     * @return string|null
     */
    private function onCfgLogServerKeys($val)
    {
        // don't append, replace
        $this->cfg['logServerKeys'] = array();
        return $val;
    }

    /**
     * Propagate updated vals to child channels
     *
     * @param Event $event Debug::EVENT_CONFIG Event instance
     * @param array $cfg   Debug config values
     *
     * @return void
     */
    private function onConfigPropagate(Event $event, array $cfg)
    {
        $channels = $this->getChannels(false, true);
        if (empty($channels)) {
            return;
        }
        $event['debug'] = $cfg;
        $cfg = $this->rootInstance->getPlugin('channel')->getPropagateValues($event->getValues());
        unset($cfg['currentSubject'], $cfg['isTarget']);
        if ($this->bootstrapped === false) {
            // edge case:
            //  channel created via plugin constructor or EVENT_PLUGIN_INIT
            //  with channelShow: false
            //  bootstrap propagates initial config with channelShow: true
            //  channels should be created via EVENT_BOOTSTRAP handler
            unset($cfg['debug']['channelShow']);
        }
        if (empty($cfg)) {
            return;
        }
        foreach ($channels as $channel) {
            $channel->config->set($cfg);
        }
    }
}

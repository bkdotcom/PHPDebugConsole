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

    /** @var Container */
    protected $container;

    /** @var Debug|null */
    protected static $instance;

    /** @var Debug|null */
    protected $parentInstance;

    /** @var Debug */
    protected $rootInstance;

    /** @var list<string> */
    protected $readOnly = [
        'container',
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
                ie Debug::setCfg(array('route'=>'html')) via command line
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
            'channelName' => [$this, 'onCfgChannelName'],
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
     * Update container values
     *
     * @param ServiceProviderInterface|callable|array $val dependency definitions
     *
     * @return array
     */
    public function onCfgServiceProvider($val)
    {
        $rawValues = ContainerUtility::toRawValues($val);
        $serviceNames = $this->container['services'];
        $services = \array_intersect_key($rawValues, \array_flip($serviceNames));
        $channels = $this->bootstrapped
            ? $this->getChannels(true, true)
            : array();
        foreach ($services as $serviceName => $value) {
            unset($rawValues[$serviceName]);
            $this->container[$serviceName] = $value;
            foreach ($channels as $channel) {
                $channel->container[$serviceName] = function () use ($serviceName) {
                    return $this->rootInstance->container[$serviceName];
                };
            }
        }
        foreach ($rawValues as $k => $v) {
            unset($rawValues[$k]);
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
        \bdk\Debug\Utility\PhpType::assertType($debug, 'bdk\Debug|null', 'debug');
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
        $cfgBootstrap = $this->bootstrapConfigValues($cfg);
        $this->bootstrapInstances($cfgBootstrap['parent']);
        $this->bootstrapContainer($cfgBootstrap['container']);
        $this->onCfgServiceProvider($cfgBootstrap['serviceProvider']);

        $this->eventManager->addSubscriberInterface($this->container['pluginManager']);

        $cfg = $this->configNormalizer->normalizeArray($cfg);
        unset($cfg['debug']['serviceProvider'], $cfg['debug']['parent']);

        if (!$this->parentInstance) {
            // we're the root instance
            $cfgBackup = $this->cfg;
            $cfg = \array_merge(array('debug' => array()), $cfg);
            $this->cfg = $this->arrayUtil->mergeDeep($this->cfg, $cfg['debug']);

            $this->container['errorHandler']; // instantiate errorHandler
            $this->addPlugins($this->cfg['plugins']);
            $this->data->set('requestId', $this->requestId());
            $this->data->set('entryCountInitial', $this->data->get('log/__count__'));

            $this->cfg = $cfgBackup;
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
    private function bootstrapConfigValues($cfg)
    {
        $cfgDefault = array(
            'container' => array(),
            'parent' => null,
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

        return \array_replace_recursive($cfgDefault, $cfgValues);
    }

    /**
     * Initialize dependency containers
     *
     * @param array $cfg Container configuration values
     *
     * @return void
     */
    private function bootstrapContainer(array $cfg)
    {
        $this->container = new Container(
            array(
                'bdk\Debug' => $this,
            ),
            $cfg
        );
        $this->container->addAlias('debug', 'bdk\Debug');
        $this->container->registerProvider(new ServiceProvider());
        if ($this->parentInstance) {
            // we are a child instance
            // shared services should come from root instance
            foreach ($this->container['services'] as $serviceName) {
                $this->container[$serviceName] = function () use ($serviceName) {
                    return $this->rootInstance->container[$serviceName];
                };
            }
        }
        $this->container->setCfg('onInvoke', [$this->config, 'onContainerInvoke']);
    }

    /**
     * Set instance, rootInstance, & parentInstance
     *
     * @param static|null $parentInstance Parent instance (or null)
     *
     * @return void
     */
    private function bootstrapInstances($parentInstance = null)
    {
        $this->rootInstance = $this;
        if ($parentInstance) {
            $this->parentInstance = $parentInstance;
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
        return \preg_match('/^:(.+):$/', (string) $val, $matches)
            ? $this->getCfg('icons.' . $matches[1], Debug::CONFIG_DEBUG)
            : $val;
    }

    /**
     * Handle "channelName" config update
     *
     * @param string|null $val config value
     *
     * @return string|null
     */
    private function onCfgChannelName($val)
    {
        return \preg_match('/^(.+)\|trans$/', $val, $matches)
            ? $this->i18n->trans($matches[1])
            : $val;
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

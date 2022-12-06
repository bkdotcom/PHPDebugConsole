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

namespace bdk\Debug;

use bdk\Container;
use bdk\Container\ServiceProviderInterface;
use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Route\RouteInterface;
use bdk\Debug\ServiceProvider;
use bdk\PubSub\Event;
use ReflectionMethod;

/**
 * Handle underlying Debug bootstraping and config
 */
class AbstractDebug
{
    /** @var \bdk\Debug\Config */
    protected $config;

    /** @var \bdk\Container */
    protected $container;
    protected $serviceContainer;

    /** @var \bdk\Debug */
    protected static $instance;

    protected static $methodDefaultArgs = array();
    protected $parentInstance;
    protected $rootInstance;

    protected $readOnly = array(
        'parentInstance',
        'rootInstance',
    );

    /**
     * Constructor
     *
     * @param array $cfg config
     */
    public function __construct($cfg)
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
    public function __call($methodName, $args)
    {
        $logEntry = new LogEntry(
            $this,
            $methodName,
            $args
        );
        $this->publishBubbleEvent(Debug::EVENT_CUSTOM_METHOD, $logEntry);
        if ($logEntry['handled'] !== true) {
            $logEntry->setMeta('isCustomMethod', true);
            $this->appendLog($logEntry);
        }
        return $logEntry['return'];
    }

    /**
     * Magic method to allow us to call instance methods statically
     *
     * Prefix the instance method with an underscore ie
     *    \bdk\Debug::_log('logged via static method');
     *
     * @param string $methodName Inaccessible method name
     * @param array  $args       Arguments passed to method
     *
     * @return mixed
     */
    public static function __callStatic($methodName, $args)
    {
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
        /*
            Add 'statically' meta arg
            Not all methods expect meta args... so make sure it comes after expected args
        */
        $defaultArgs = self::getMethodDefaultArgs($methodName);
        $args = \array_replace(\array_values($defaultArgs), $args);
        $args[] = static::meta('statically');
        return \call_user_func_array(array(self::$instance, $methodName), $args);
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
        if (!$cfg) {
            return;
        }
        $valActions = array(
            'logServerKeys' => function ($val) {
                // don't append, replace
                $this->cfg['logServerKeys'] = array();
                return $val;
            },
            'route' => array($this, 'onCfgRoute'),
        );
        $valActions = \array_intersect_key($valActions, $cfg);
        foreach ($valActions as $key => $callable) {
            /** @psalm-suppress TooManyArguments */
            $cfg[$key] = $callable($cfg[$key]);
        }
        $this->cfg = $this->arrayUtil->mergeDeep($this->cfg, $cfg);
        /*
            propagate updated vals to child channels
        */
        $channels = $this->getChannels(false, true);
        if (empty($channels)) {
            return;
        }
        $event['debug'] = $cfg;
        $cfg = $this->container['pluginChannel']->getPropagateValues($event->getValues());
        foreach ($channels as $channel) {
            $channel->config->set($cfg);
        }
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
        $val = $this->serviceProviderToArray($val);
        if (\is_array($val) === false) {
            return $val;
        }
        $services = $this->container['services'];
        foreach ($val as $k => $v) {
            if (\in_array($k, $services, true)) {
                $this->serviceContainer[$k] = $v;
                unset($val[$k]);
                continue;
            }
            $this->container[$k] = $v;
        }
        return $val;
    }

    /**
     * Publish/Trigger/Dispatch event
     * Event will get published on ancestor channels if propagation not stopped
     *
     * @param string $eventName Event name
     * @param Event  $event     Event instance
     * @param Debug  $debug     Specify Debug instance to start on.
     *                            If not specified will check if getSubject returns Debug instance
     *                            Fallback: this->debug
     *
     * @return Event
     */
    public function publishBubbleEvent($eventName, Event $event, Debug $debug = null)
    {
        if ($debug === null) {
            $subject = $event->getSubject();
            /** @var Debug */
            $debug = $subject instanceof Debug
                ? $subject
                : $this;
        }
        do {
            $debug->eventManager->publish($eventName, $event);
            if (!$debug->parentInstance) {
                break;
            }
            $debug = $debug->parentInstance;
        } while (!$event->isPropagationStopped());
        return $event;
    }

    /**
     * Store the arguments
     * if collect is false -> does nothing
     * otherwise:
     *   + abstracts values
     *   + publishes Debug::EVENT_LOG event
     *   + appends log (if event propagation not stopped)
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return bool whether or not entry got appended
     */
    protected function appendLog(LogEntry $logEntry)
    {
        if (!$this->cfg['collect'] && !$logEntry['forcePublish']) {
            return false;
        }
        $cfgRestore = array();
        if (isset($logEntry['meta']['cfg'])) {
            $cfgRestore = $this->setCfg($logEntry['meta']['cfg']);
            $logEntry->setMeta('cfg', null);
        }
        $logEntry->crate();
        $this->publishBubbleEvent(Debug::EVENT_LOG, $logEntry);
        if ($cfgRestore) {
            $this->setCfg($cfgRestore);
        }
        if ($logEntry['appendLog']) {
            $this->data->appendLog($logEntry);
            return true;
        }
        return false;
    }

    /**
     * Get Method's default argument list
     *
     * @param string $methodName Name of the method
     *
     * @return array
     */
    protected static function getMethodDefaultArgs($methodName)
    {
        if (isset(self::$methodDefaultArgs[$methodName])) {
            return self::$methodDefaultArgs[$methodName];
        }
        if (\method_exists(self::$instance, $methodName) === false) {
            return array();
        }
        $defaultArgs = array();
        $refMethod = new ReflectionMethod(self::$instance, $methodName);
        $params = $refMethod->getParameters();
        foreach ($params as $refParameter) {
            $name = $refParameter->getName();
            $defaultArgs[$name] = $refParameter->isOptional()
                ? $refParameter->getDefaultValue()
                : null;
        }
        unset($defaultArgs['args']);
        self::$methodDefaultArgs[$methodName] = $defaultArgs;
        return $defaultArgs;
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
        $bootstrapConfig = $this->bootstrapConfig($cfg);
        $this->bootstrapSetInstances($bootstrapConfig);
        $this->bootstrapContainer($bootstrapConfig);

        $this->config = $this->container['config'];
        $this->container->setCfg('onInvoke', array($this->config, 'onContainerInvoke'));
        $this->serviceContainer->setCfg('onInvoke', array($this->config, 'onContainerInvoke'));
        $this->eventManager->addSubscriberInterface($this->container['pluginManager']);
        $this->addPlugin($this->serviceContainer['customMethodGeneral']);
        $this->addPlugin($this->serviceContainer['customMethodReqRes']);
        $this->addPlugin($this->container['pluginChannel']);
        $this->addPlugin($this->container['configEvents']);
        $this->addPlugin($this->container['internalEvents']);
        $this->addPlugin($this->container['pluginRedaction']);
        $this->eventManager->subscribe(Debug::EVENT_CONFIG, array($this, 'onConfig'));

        $this->serviceContainer['errorHandler'];
        $this->config->set($cfg);

        if (!$this->parentInstance) {
            // we're the root instance
            // this is the root instance
            $this->data->set('requestId', $this->requestId());
            $this->data->set('entryCountInitial', $this->data->get('log/__count__'));

            $this->addPlugin($this->container['pluginLogEnv']);
            $this->addPlugin($this->container['pluginLogFiles']);
            $this->addPlugin($this->container['pluginLogPhp']);
            $this->addPlugin($this->container['pluginLogReqRes']);
        }
        $this->eventManager->publish(Debug::EVENT_BOOTSTRAP, $this);
    }

    /**
     * Get config values needed for bootstraping
     *
     * @param array $cfg Config passed to container
     *
     * @return array
     */
    private function bootstrapConfig(&$cfg)
    {
        $cfgValues = array(
            'container' => array(),
            'parent' => null,
            'serviceProvider' => $this->cfg['serviceProvider'],
        );

        if (isset($cfg['debug']['container'])) {
            $cfgValues['container'] = $cfg['debug']['container'];
        } elseif (isset($cfg['container'])) {
            $cfgValues['container'] = $cfg['container'];
        }

        if (isset($cfg['debug']['serviceProvider'])) {
            $cfgValues['serviceProvider'] = $cfg['debug']['serviceProvider'];
            // unset so we don't do this again with setCfg
            unset($cfg['debug']['serviceProvider']);
        } elseif (isset($cfg['serviceProvider'])) {
            $cfgValues['serviceProvider'] = $cfg['serviceProvider'];
            // unset so we don't do this again with setCfg
            unset($cfg['serviceProvider']);
        }

        if (isset($cfg['debug']['parent'])) {
            $cfgValues['parent'] = $cfg['debug']['parent'];
            unset($cfg['debug']['parent']);
        }
        return $cfgValues;
    }

    /**
     * Initialize dependancy containers
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
     * If "core" route, store in container
     *
     * @param mixed $val       route value
     * @param bool  $addPlugin (true) Should we add as plugin?
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgRoute($val, $addPlugin = true)
    {
        if (!($val instanceof RouteInterface)) {
            return $val;
        }
        if ($addPlugin) {
            $this->addPlugin($val);
        }
        $classname = \get_class($val);
        $prefix = __NAMESPACE__ . '\\Route\\';
        $containerName = 'route' . \substr($classname, \strlen($prefix));
        if (\strpos($classname, $prefix) === 0 && !$this->container->has($containerName)) {
            $this->container->offsetSet($containerName, $val);
        }
        if ($val->appendsHeaders()) {
            $this->obStart();
        }
        return $val;
    }

    /**
     * Convert serviceProvider to array of name => value
     *
     * @param ServiceProviderInterface|callable|array $val dependency definitions
     *
     * @return array
     */
    private function serviceProviderToArray($val)
    {
        $getContainerRawVals = static function (Container $container) {
            $keys = $container->keys();
            $return = array();
            foreach ($keys as $key) {
                $return[$key] = $container->raw($key);
            }
            return $return;
        };
        if ($val instanceof ServiceProviderInterface) {
            /*
                convert to array
            */
            $containerTmp = new Container();
            $containerTmp->registerProvider($val);
            return $getContainerRawVals($containerTmp);
        }
        if (\is_callable($val)) {
            /*
                convert to array
            */
            $containerTmp = new Container();
            \call_user_func($val, $containerTmp);
            return $getContainerRawVals($containerTmp);
        }
        return $val;
    }
}

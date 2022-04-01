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

namespace bdk\Debug;

use bdk\Debug;
use bdk\Debug\Utility\FileStreamWrapper;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Handle configuration changes
 */
class ConfigEvents implements SubscriberInterface
{
    protected $debug;
    private $isBootstrapped = false;
    private $isConfigured = false;
    private static $profilingEnabled = false;

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
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CONFIG => array('onConfig', PHP_INT_MAX),
            Debug::EVENT_BOOTSTRAP => array('onBootstrap', PHP_INT_MAX * -1),
        );
    }

    /**
     * Debug::EVENT_BOOTSTRAP subscriber
     *
     * @return void
     */
    public function onBootstrap()
    {
        $this->isBootstrapped = true;
        if ($this->debug->parentInstance) {
            return;
        }
        // this is the root instance
        $route = $this->debug->getCfg('route');
        if ($route === 'stream') {
            // normally we don't init the route until output
            // but stream needs to begin listening now
            $this->debug->setCfg('route', $route);
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
        $configs = $event->getValues();
        if (isset($configs['routeStream']['stream'])) {
            $this->debug->addPlugin($this->debug->getRoute('stream'));
        }
        if (empty($configs['debug'])) {
            // no debug config values have changed
            return;
        }
        $cfgDebug = $this->onConfigInit($configs['debug']);
        $valActions = \array_intersect_key(array(
            'emailFrom' => array($this, 'onCfgEmail'),
            'emailFunc' => array($this, 'onCfgEmail'),
            'emailTo' => array($this, 'onCfgEmail'),
            'key' => array($this, 'onCfgKey'),
            'logEnvInfo' => array($this, 'onCfgList'),
            'logRequestInfo' => array($this, 'onCfgList'),
            'logResponse' => array($this, 'onCfgLogResponse'),
            'onBootstrap' => array($this, 'onCfgOnBootstrap'),
            'onLog' => array($this, 'onCfgOnLog'),
            'onMiddleware' => array($this, 'onCfgOnMiddleware'),
            'onOutput' => array($this, 'onCfgOnOutput'),
            'route' => array($this, 'onCfgRoute'),
            'serviceProvider' => array($this, 'onCfgServiceProvider'),
            'enableProfiling' => array($this, 'onCfgEnableProfiling'),
        ), $cfgDebug);
        foreach ($valActions as $key => $callable) {
            /** @psalm-suppress TooManyArguments */
            $cfgDebug[$key] = $callable($cfgDebug[$key], $key, $event);
        }
        $event['debug'] = \array_merge($event['debug'], $cfgDebug);
    }

    /**
     * Handle "emailTo" config update
     *
     * @param string $val   config value
     * @param string $key   config param name
     * @param Event  $event The config change event
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgEmail($val, $key, Event $event)
    {
        if ($val === 'default' && $key === 'emailTo') {
            $val = $this->debug->getServerParam('SERVER_ADMIN');
        }
        $errorEmailerCfg = $event['errorEmailer'];
        if (!isset($errorEmailerCfg[$key])) {
            // also set for errorEmailer
            $errorEmailerCfg[$key] = $val;
            $event['errorEmailer'] = $errorEmailerCfg;
        }
        return $val;
    }

    /**
     * Test if we need to enable profiling
     *
     * @param string $val   config value
     * @param string $key   config param name
     * @param Event  $event The config change event
     *
     * @return bool
     */
    private function onCfgEnableProfiling($val, $key, Event $event)
    {
        if (static::$profilingEnabled) {
            // profiling already enabled
            return $val;
        }
        $cfgAll = \array_merge(
            $this->debug->getCfg(null, Debug::CONFIG_DEBUG),
            $event['debug']
        );
        if ($cfgAll['enableProfiling'] && $cfgAll['collect']) {
            static::$profilingEnabled = true;
            FileStreamWrapper::setEventManager($this->debug->eventManager);
            FileStreamWrapper::setPathsExclude(array(
                __DIR__,
            ));
            FileStreamWrapper::register();
        }
        return $val;
    }

    /**
     * Test $_REQUEST['debug'] against passed configured key
     * Update collect & output values based on key value
     *
     * @param string $val   configured debug key
     * @param string $name  config param name
     * @param Event  $event The config change event
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    private function onCfgKey($val, $name, Event $event)
    {
        if ($val === null || $this->debug->isCli()) {
            return $val;
        }
        $request = $this->debug->request;
        $cookieParams = $request->getCookieParams();
        $queryParams = $request->getQueryParams();
        $requestKey = null;
        if (isset($queryParams['debug'])) {
            $requestKey = $queryParams['debug'];
        } elseif (isset($cookieParams['debug'])) {
            $requestKey = $cookieParams['debug'];
        }
        $isValidKey = $requestKey === $val;
        $valsNew = array();
        if ($isValidKey) {
            // only enable collect / don't disable it
            $valsNew['collect'] = true;
        }
        $valsNew['output'] = $isValidKey;
        $event['debug'] = \array_merge($event['debug'], $valsNew);
        return $val;
    }

    /**
     * Convert logEnvInfo & logRequestInfo values to key=>bool arrays
     *
     * @param mixed  $val  value
     * @param string $name 'logEnvInfo'|'logRequestInfo'
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgList($val, $name)
    {
        $curVal = $this->debug->getCfg($name, Debug::CONFIG_DEBUG);
        $allKeys = \array_keys($curVal);
        if (\is_bool($val)) {
            $val = \array_fill_keys($allKeys, $val);
        } elseif ($this->debug->arrayUtil->isList($val)) {
            $val = \array_merge(
                \array_fill_keys($allKeys, false),
                \array_fill_keys($val, true)
            );
        }
        return $val;
    }

    /**
     * Handle "logResponse" config update
     *
     * @param mixed $val config value
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgLogResponse($val)
    {
        if ($val === 'auto') {
            $serverParams = \array_merge(array(
                'HTTP_SOAPACTION' => null,
                'HTTP_USER_AGENT' => '',
            ), $this->debug->request->getServerParams());
            $val = \count(
                \array_filter(array(
                    \strpos($this->debug->getInterface(), 'http') !== false,
                    $serverParams['HTTP_SOAPACTION'],
                    \stripos($serverParams['HTTP_USER_AGENT'], 'curl') !== false,
                ))
            ) > 0;
        }
        if ($val) {
            $this->debug->obStart();
        }
        return $val;
    }

    /**
     * Handle "onBootstrap" config update
     *
     * @param mixed $val config value
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgOnBootstrap($val)
    {
        if (!$val) {
            return;
        }
        if ($this->isBootstrapped) {
            // boostrap has already occured, so go ahead and call
            \call_user_func($val, new Event($this->debug));
            return;
        }
        // we're bootstraping
        $this->debug->eventManager->subscribe(Debug::EVENT_BOOTSTRAP, $val);
    }

    /**
     * Handle "onLog" config update
     *
     * @param mixed $val config value
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgOnLog($val)
    {
        /*
            Replace - not append - subscriber set via setCfg
        */
        $prev = $this->debug->getCfg('onLog', Debug::CONFIG_DEBUG);
        if ($prev) {
            $this->debug->eventManager->unsubscribe(Debug::EVENT_LOG, $prev);
        }
        if ($val) {
            $this->debug->eventManager->subscribe(Debug::EVENT_LOG, $val);
        }
        return $val;
    }

    /**
     * Handle "onOutput" config update
     *
     * @param mixed $val config value
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgOnOutput($val)
    {
        /*
            Replace - not append - subscriber set via setCfg
        */
        $prev = $this->debug->getCfg('onOutput', Debug::CONFIG_DEBUG);
        if ($prev) {
            $this->debug->eventManager->unsubscribe(Debug::EVENT_OUTPUT, $prev);
        }
        if ($val) {
            $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, $val);
        }
        return $val;
    }

    /**
     * Handle "onMiddleware" config update
     *
     * @param mixed $val config value
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgOnMiddleware($val)
    {
        /*
            Replace - not append - subscriber set via setCfg
        */
        $prev = $this->debug->getCfg('onMiddleware', Debug::CONFIG_DEBUG);
        if ($prev) {
            $this->debug->eventManager->unsubscribe(Debug::EVENT_MIDDLEWARE, $prev);
        }
        if ($val) {
            $this->debug->eventManager->subscribe(Debug::EVENT_MIDDLEWARE, $val);
        }
        return $val;
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

        if ($this->isBootstrapped) {
            /*
                Only need to worry about previous route if we're bootstrapped
                There can only be one 'route' at a time:
                If multiple output routes are desired, use debug->addPlugin()
                unsubscribe current OutputInterface
            */
            $routePrev = $this->debug->getCfg('route');
            if (\is_object($routePrev)) {
                $this->debug->removePlugin($routePrev);
            }
        }
        if (\is_string($val) && $val !== 'auto') {
            $val = $this->debug->getRoute($val);
        }
        return $val;
    }

    /**
     * Handle updates to serviceProvider here
     * We need to clear serverParamCache
     *
     * @param mixed $val ServiceProvider value
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgServiceProvider($val)
    {
        return $this->debug->onCfgServiceProvider($val);
    }

    /**
     * Merge in default config values if not yet configured
     *
     * @param array $cfg Config vals being updated
     *
     * @return array
     */
    private function onConfigInit($cfg)
    {
        if ($this->isConfigured) {
            return $cfg;
        }
        $this->isConfigured = true;
        return \array_merge(
            \array_diff_key(
                // remove current collect & output values,
                //   so don't trigger updates for existing values
                $this->debug->getCfg(null, Debug::CONFIG_DEBUG),
                \array_flip(array('collect','output'))
            ),
            $cfg
        );
    }
}

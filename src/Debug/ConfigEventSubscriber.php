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
class ConfigEventSubscriber implements SubscriberInterface
{

    protected $debug;
    private $event;
    private $isBootstrapped = false;
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
        if (empty($configs['debug'])) {
            // no debug config values have changed
            return;
        }
        $this->event = $event;
        $valActions = array(
            'emailTo' => array($this, 'onCfgEmailTo'),
            'key' => array($this, 'onCfgKey'),
            'logEnvInfo' => array($this, 'onCfgList'),
            'logRequestInfo' => array($this, 'onCfgList'),
            'logResponse' => array($this, 'onCfgLogResponse'),
            'onBootstrap' => array($this, 'onCfgOnBootstrap'),
            'onLog' => array($this, 'onCfgOnLog'),
            'onMiddleware' => array($this, 'onCfgOnMiddleware'),
            'onOutput' => array($this, 'onCfgOnOutput'),
            'route' => array($this, 'onCfgRoute'),
        );
        $cfgDebug = $configs['debug'];
        $valActions = \array_intersect_key($valActions, $cfgDebug);
        foreach ($valActions as $key => $callable) {
            /** @psalm-suppress TooManyArguments */
            $cfgDebug[$key] = $callable($cfgDebug[$key], $key, $event);
        }
        $configs['debug'] = \array_merge($event['debug'], $cfgDebug);
        foreach (array('emailFrom','emailFunc','emailTo') as $key) {
            if (!isset($cfgDebug[$key])) {
                continue;
            }
            if (!isset($configs['errorEmailer'][$key]) && $cfgDebug[$key] !== 'default') {
                // also set for errorEmailer
                $configs['errorEmailer'][$key] = $cfgDebug[$key];
            }
        }
        $event->setValues($configs);
        $this->onCfgProfilingUpdate($configs['debug']);
    }

    /**
     * Handle "emailTo" config update
     *
     * @param string $val config value
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgEmailto($val)
    {
        return $val === 'default'
            ? $this->debug->getServerParam('SERVER_ADMIN')
            : $val;
    }

    /**
     * Test $_REQUEST['debug'] against passed configured key
     * Update collect & output values based on key value
     *
     * @param string $key configured debug key
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgKey($key)
    {
        if ($key === null || $this->debug->isCli()) {
            return $key;
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
        $isValidKey = $requestKey === $key;
        $valsNew = array();
        if ($isValidKey) {
            // only enable collect / don't disable it
            $valsNew['collect'] = true;
        }
        $valsNew['output'] = $isValidKey;
        $this->event['debug'] = \array_merge($this->event['debug'], $valsNew);
        return $key;
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
                'HTTP_ACCEPT' => null,
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
     * Test if we need to enable profiling
     *
     * @param array $cfgDebug Debug config values being updated
     *
     * @return void
     */
    private function onCfgProfilingUpdate($cfgDebug)
    {
        if (static::$profilingEnabled) {
            // profiling already enabled
            return;
        }
        $cfgAll = \array_merge(
            $this->debug->getCfg(null, Debug::CONFIG_DEBUG),
            $cfgDebug
        );
        if ($cfgAll['enableProfiling'] && $cfgAll['collect']) {
            static::$profilingEnabled = true;
            FileStreamWrapper::setEventManager($this->debug->eventManager);
            FileStreamWrapper::setPathsExclude(array(
                __DIR__,
            ));
            FileStreamWrapper::register();
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
}

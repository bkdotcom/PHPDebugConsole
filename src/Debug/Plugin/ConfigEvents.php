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
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_BOOTSTRAP => array('onBootstrap', PHP_INT_MAX * -1),
            Debug::EVENT_CONFIG => array('onConfig', PHP_INT_MAX),
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
        if (!$event['debug'] || $event['isTarget'] === false) {
            return;
        }
        $this->debug = $event->getSubject();
        $cfgDebug = $this->onConfigInit($event->getValues());
        $valActions = \array_intersect_key(array(
            'channels' => array($this, 'onCfgChannels'),
            'emailFrom' => array($this, 'onCfgEmail'),
            'emailFunc' => array($this, 'onCfgEmail'),
            'emailTo' => array($this, 'onCfgEmail'),
            'enableProfiling' => array($this, 'onCfgEnableProfiling'),
            'key' => array($this, 'onCfgKey'),
            'logEnvInfo' => array($this, 'onCfgList'),
            'logRequestInfo' => array($this, 'onCfgList'),
            'logResponse' => array($this, 'onCfgLogResponse'),
            'onBootstrap' => array($this, 'onCfgOnBootstrap'),
            'onLog' => array($this, 'onCfgReplaceSubscriber'),
            'onMiddleware' => array($this, 'onCfgReplaceSubscriber'),
            'onOutput' => array($this, 'onCfgReplaceSubscriber'),
            'serviceProvider' => array($this, 'onCfgServiceProvider'),
        ), $cfgDebug);
        foreach ($valActions as $key => $callable) {
            /** @psalm-suppress TooManyArguments */
            $cfgDebug[$key] = $callable($cfgDebug[$key], $key, $event);
        }
        $event['debug'] = \array_merge($event['debug'], $cfgDebug);
    }

    /**
     * Handle "channels" config update
     *
     * Ensure that channels is a "tree" vs "flat"
     *
     * @param string $val channels config value
     *
     * @return array channels tree
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgChannels($val)
    {
        $tree = array();
        foreach ($val as $name => $config) {
            $ref = &$tree;
            $path = \explode('.', $name);
            $name = \array_pop($path);
            foreach ($path as $k) {
                if (!isset($ref[$k])) {
                    $ref[$k] = array();
                }
                if (!isset($ref[$k]['channels'])) {
                    $ref[$k]['channels'] = array();
                }
                $ref = &$ref[$k]['channels'];
            }
            if (!isset($ref[$name])) {
                $ref[$name] = array();
            }
            $ref[$name] = \array_merge($ref[$name], $config);
        }
        return $tree;
    }

    /**
     * Handle "emailTo" config update
     *
     *   * emailFrom
     *   * emailFunc
     *   * emailTo
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
        if ($key === 'emailTo' && $val === 'default') {
            $val = $this->debug->getServerParam('SERVER_ADMIN');
        }
        $errorHandlerCfg = isset($event['errorHandler'])
            ? $event['errorHandler']
            : array();
        $errorEmailerCfg = isset($errorHandlerCfg['emailer'])
            ? $errorHandlerCfg['emailer']
            : array();
        if (!isset($errorEmailerCfg[$key])) {
            // also set for errorEmailer
            $errorEmailerCfg[$key] = $val;
        }
        $errorHandlerCfg['emailer'] = $errorEmailerCfg;
        $event['errorHandler'] = $errorHandlerCfg;
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
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    private function onCfgEnableProfiling($val, $key, Event $event)
    {
        if (static::$profilingEnabled) {
            // profiling currently enabled
            if ($val === false) {
                FileStreamWrapper::unregister();
                static::$profilingEnabled = false;
            }
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
                \realpath(__DIR__ . '/../'),
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
        $request = $this->debug->serverRequest;
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
            ), $this->debug->serverRequest->getServerParams());
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
     * Handle "onLog", "onBootstrap", & "onMiddleware" config update
     *
     * Replace - not append - subscriber set via setCfg
     *
     * @param callable $val  config value
     * @param string   $name 'onLog', 'onOutput', or 'onMiddleware'
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgReplaceSubscriber($val, $name)
    {
        $events = array(
            'onLog' => Debug::EVENT_LOG,
            'onMiddleware' => Debug::EVENT_MIDDLEWARE,
            'onOutput' => Debug::EVENT_OUTPUT,
        );
        $event = $events[$name];
        $prev = $this->debug->getCfg($name, Debug::CONFIG_DEBUG);
        if ($prev) {
            $this->debug->eventManager->unsubscribe($event, $prev);
        }
        if ($val) {
            $this->debug->eventManager->subscribe($event, $val);
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
     * @param array $configs Config vals being updated
     *
     * @return array
     */
    private function onConfigInit($configs)
    {
        $configs = \array_merge(array(
            'debug' => array(),
            'errorHandler' => array(),
        ), $configs);
        $cfgDebug = $configs['debug'];
        if (isset($configs['routeStream']['stream'])) {
            $this->debug->addPlugin($this->debug->getRoute('stream'));
        }
        if (empty($cfgDebug) || $this->isConfigured) {
            return $cfgDebug;
        }
        $this->isConfigured = true;
        return \array_merge(
            \array_diff_key(
                // remove current collect & output values,
                //   so don't trigger updates for existing values
                $this->debug->getCfg(null, Debug::CONFIG_DEBUG),
                \array_flip(array('collect', 'output'))
            ),
            $cfgDebug
        );
    }
}

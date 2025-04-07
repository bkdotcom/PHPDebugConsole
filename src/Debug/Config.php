<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     1.3.2
 */

namespace bdk\Debug;

use bdk\Debug;

/**
 * Configuration manager
 */
class Config
{
    /** @var Debug */
    protected $debug;

    /** @var array */
    protected $invokedServices = array();

    /** @var ConfigNormalizer */
    protected $normalizer;

    /** @var array */
    protected $valuesPending = array();

    /**
     * Constructor
     *
     * @param Debug                 $debug      debug instance
     * @param ConfigNormalizer|null $normalizer (optional) specify config normalizer
     */
    public function __construct(Debug $debug, $normalizer = null)
    {
        $this->debug = $debug;
        $this->normalizer = $normalizer ?: new ConfigNormalizer($debug);
    }

    /**
     * Get debug or child configuration value(s)
     *
     * @param string $path    what to get
     * @param bool   $forInit for initial object construction (also accepts `Debug::CONFIG_INIT`)
     *
     * @return mixed
     */
    public function get($path, $forInit = false)
    {
        $path = $this->normalizer->normalizePath($path);
        $forInit = \in_array($forInit, array(true, Debug::CONFIG_INIT), true);
        $debugProp = \array_shift($path);
        if ($debugProp === '*') {
            $keys = \array_unique(\array_merge($this->normalizer->serviceKeys(), \array_keys($this->valuesPending)));
            $values = array();
            foreach ($keys as $debugProp) {
                $values[$debugProp] = $this->getPropCfg($debugProp, array(), $forInit, false);
            }
            \ksort($values);
            return $values;
        }
        $val = $this->getPropCfg($debugProp, $path, $forInit, $forInit);
        return $forInit && $val === null
            ? array()
            : $val;
    }

    /**
     * Called when container closure invoked
     *
     * @param mixed  $val  value returned from closure
     * @param string $name value identifier
     *
     * @return void
     */
    public function onContainerInvoke($val, $name)
    {
        $isConfigurable = \is_object($val) && \method_exists($val, 'setCfg');
        if (!$isConfigurable) {
            $this->invokedServices[] = $name;
            return;
        }
        $cfg = $this->get($name, true);
        $val->setCfg($cfg);
        $this->invokedServices[] = $name;
    }

    /**
     * Set one or more config values
     *
     *    set('key', 'value', $publish)
     *    set('level1.level2', 'value', $publish)
     *    set(array('k1'=>'v1', 'k2'=>'v2'), $publish)
     *
     * Triggers a `Debug::EVENT_CONFIG` event that contains all changed values
     *
     * @param array|string $path    (string) path or array of values
     * @param mixed        $value   If setting via path, this is the value
     * @param int          $options Bitmask of CONFIG_NO_PUBLISH, CONFIG_NO_RETURN
     *
     * @return mixed previous value(s)
     */
    public function set($path, $value = null, $options = 0)
    {
        if (\is_array($path)) {
            $values = $this->normalizer->normalizeArray($path);
            $options = \is_int($value) ? $value : $options;
            return $this->doSet($values, $options);
        }
        $path = $this->normalizer->normalizePath($path);
        $values = array();
        $this->debug->arrayUtil->pathSet($values, $path, $value);
        $return = $this->doSet($values, $options);
        return $this->debug->arrayUtil->pathGet($return, $path);
    }

    /**
     * Set cfg values for Debug and child classes
     *
     * @param array $configs Config values grouped by class
     * @param int   $options Bitmask of CONFIG_NO_PUBLISH, CONFIG_NO_RETURN
     *
     * @return array previous values
     */
    private function doSet(array $configs, $options = 0)
    {
        $return = array();
        if (!$configs) {
            return $return;
        }
        if (($options & Debug::CONFIG_NO_RETURN) !== Debug::CONFIG_NO_RETURN) {
            $return = $this->doSetReturn($configs);
        }
        /*
            Publish config event first... it may add/update debugProp values
        */
        if (($options & Debug::CONFIG_NO_PUBLISH) !== Debug::CONFIG_NO_PUBLISH) {
            $configs = $this->publishConfigEvent($configs);
        }
        /*
            Now set the values
        */
        // debug uses a Debug::EVENT_CONFIG subscriber to get updated config values
        unset($configs['debug']); // 'debug' was set via event above
        foreach ($configs as $debugProp => $cfg) {
            $this->setPropCfg($debugProp, $cfg);
        }
        return $return;
    }

    /**
     * Find the previous values
     *
     * @param array $configs New config values
     *
     * @return array
     */
    private function doSetReturn(array $configs)
    {
        $return = array();
        foreach ($configs as $debugProp => $cfg) {
            $cfgWas = $debugProp === 'debug'
                ? $this->debug->getCfg(null, Debug::CONFIG_DEBUG)
                : $this->getPropCfg($debugProp, array(), true, false);
            $cfgWas = \array_intersect_key($cfgWas, $cfg);
            $keys = \array_keys($cfg);
            $keysWas = \array_keys($cfgWas);
            if ($debugProp !== 'debug' && \array_intersect($keys, $keysWas) !== $keys) {
                // we didn't get all the expected previous values...
                $cfgWas = $this->getPropCfg($debugProp, array());
                $cfgWas = \array_intersect_key($cfgWas, $cfg);
            }
            $return[$debugProp] = $cfgWas;
        }
        return $return;
    }

    /**
     * Get debug property config value(s)
     *
     * @param string $debugProp  debug property name
     * @param array  $path       path/key
     * @param bool   $forInit    (false) Get values for bootstrap (don't initialize obj)
     * @param bool   $delPending Delete pending values (if forInit)
     *
     * @return mixed
     */
    private function getPropCfg($debugProp, $path = array(), $forInit = false, $delPending = true)
    {
        $val = null;
        if (isset($this->valuesPending[$debugProp])) {
            $val = $this->debug->arrayUtil->pathGet($this->valuesPending[$debugProp], $path);
            if ($delPending) {
                unset($this->valuesPending[$debugProp]);
            }
        }
        if ($val !== null) {
            return $val;
        }
        return $debugProp === 'debug'
            ? $this->debug->getCfg($path, Debug::CONFIG_DEBUG)
            : $this->getPropCfgFromChildObj($debugProp, $path, $forInit);
    }

    /**
     * Get value from object
     *
     * @param string $debugProp debug property name
     * @param array  $path      path/key
     * @param bool   $forInit   (false) Get values for bootstrap (don't initialize obj)
     *
     * @return mixed
     */
    private function getPropCfgFromChildObj($debugProp, $path = array(), $forInit = false)
    {
        $obj = null;
        $matches = array();
        if (\in_array($debugProp, $this->invokedServices, true)) {
            $obj = $this->debug->{$debugProp};
        } elseif ($forInit) {
            return array();
        } elseif (\preg_match('/^(dump|route)(.+)$/', $debugProp, $matches)) {
            $cat = $matches[1];
            $what = $matches[2];
            $func = 'get' . \ucfirst($cat);
            $obj = $this->debug->{$func}($what);
        } elseif (isset($this->debug->{$debugProp})) {
            $obj = $this->debug->{$debugProp};
        }
        return $obj && \method_exists($obj, 'getCfg')
            ? $obj->getCfg(\implode('/', $path))
            : ($path ? null : array()); // not invoked or invalid dump/route
    }

    /**
     * Publish the Debug::EVENT_CONFIG event
     *
     * @param array $configs Config values grouped by class
     *
     * @return array
     */
    private function publishConfigEvent(array $configs)
    {
        $configs = $this->debug->eventManager->publish(
            Debug::EVENT_CONFIG,
            $this->debug,
            \array_merge($configs, array(
                'currentSubject' => $this->debug,
                'isTarget' => true,
            ))
        )->getValues();
        if ($this->debug->parentInstance) {
            $configs = $this->debug->rootInstance->eventManager->publish(
                Debug::EVENT_CONFIG,
                $this->debug,
                \array_merge($configs, array(
                    'currentSubject' => $this->debug->rootInstance,
                    'isTarget' => false,
                ))
            )->getValues();
        }
        unset($configs['currentSubject'], $configs['isTarget']);
        return $configs;
    }

    /**
     * Set debug service config value(s)
     *
     * @param string $debugProp debug property name
     * @param array  $cfg       property config values
     *
     * @return void
     */
    private function setPropCfg($debugProp, array $cfg)
    {
        $obj = null;
        if (\in_array($debugProp, $this->invokedServices, true)) {
            $obj = $this->debug->{$debugProp};
        } elseif (\preg_match('/^(dump|route)(.+)$/', $debugProp) && isset($this->debug->{$debugProp})) {
            // getDump and getRoute store the instance in debug's container
            $obj = $this->debug->{$debugProp};
        }
        if (\is_object($obj)) {
            $this->debug->{$debugProp}->setCfg($cfg);
            return;
        }
        if (isset($this->valuesPending[$debugProp])) {
            // update valuesPending
            $cfg = \array_merge($this->valuesPending[$debugProp], $cfg);
        }
        // set valuesPending
        $this->valuesPending[$debugProp] = $cfg;
    }
}

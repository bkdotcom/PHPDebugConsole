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
use bdk\Debug\ConfigurableInterface;

/**
 * Configuration manager
 */
class Config
{
    /** @var Debug */
    protected $debug;

    /** @var array */
    protected $valuesPending = array();

    /** @var array */
    protected $invokedServices = array();

    /** @var array<string,list<string|list<string>>> */
    protected $configKeys = array(
        'abstracter' => [
            'brief',
            'caseAttributeCollect',
            'caseAttributeOutput',
            'caseCollect',
            'caseOutput',
            'constAttributeCollect',
            'constAttributeOutput',
            'constCollect',
            'constOutput',
            'fullyQualifyPhpDocType',
            'interfacesCollapse',
            'maxDepth',
            'methodAttributeCollect',
            'methodAttributeOutput',
            'methodCollect',
            'methodDescOutput',
            'methodOutput',
            'methodStaticVarCollect',
            'methodStaticVarOutput',
            'objAttributeCollect',
            'objAttributeOutput',
            'objectSectionOrder',
            'objectsExclude',
            'objectSort',
            'objectsWhitelist',
            'paramAttributeCollect',
            'paramAttributeOutput',
            'phpDocCollect',
            'phpDocOutput',
            'propAttributeCollect',
            'propAttributeOutput',
            'propVirtualValueCollect',
            'stringMaxLen',
            'stringMaxLenBrief',
            'stringMinLen',
            'toStringOutput',
            'useDebugInfo',
        ],
        'debug' => [
            // any key not found falls under 'debug'...
        ],
        'errorHandler' => array(
            'continueToPrevHandler',
            'errorFactory',
            'errorReporting',
            'errorThrow',
            'onError',
            'onFirstError',
            'onEUserError',
            'suppressNever',
            'enableEmailer',
            'emailer' => [
                'dateTimeFmt',
                'emailBacktraceDumper',
                // 'emailFrom',
                // 'emailFunc',
                'emailMask',
                'emailMin',
                'emailThrottledSummary',
                'emailTraceMask',
                // 'emailTo',
            ],
            'enableStats',
            'stats' => [
                'dataStoreFactory',
                'errorStatsFile',
            ],
        ),
        'routeHtml' => [
            'css',
            'drawer',
            'filepathCss',
            'filepathScript',
            'jqueryUrl',
            'outputCss',
            'outputScript',
            'sidebar',
            'tooltip',
        ],
        'routeStream' => [
            'ansi',
            'stream',
        ],
    );

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
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
        $path = $this->normalizePath($path);
        $forInit = \in_array($forInit, array(true, Debug::CONFIG_INIT), true);
        $debugProp = \array_shift($path);
        if ($debugProp === '*') {
            $keys = \array_keys($this->configKeys + $this->valuesPending);
            $values = array();
            foreach ($keys as $debugProp) {
                $values[$debugProp] = $this->getPropCfg($debugProp, array(), $forInit, false);
            }
            \ksort($values);
            return $values;
        }
        $val = $this->getPropCfg($debugProp, $path, $forInit, $forInit);
        if ($forInit && $val === null) {
            $val = array();
        }
        return $val;
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
        if (!($val instanceof ConfigurableInterface)) {
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
            $values = $this->normalizeArray($path);
            $options = \is_int($value) ? $value : $options;
            return $this->doSet($values, $options);
        }
        $path = $this->normalizePath($path);
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
        unset($configs['debug']);
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
        return $obj
            ? $obj->getCfg(\implode('/', $path))
            : null; // not invoked or invalid dump/route
    }

    /**
     * Normalizes cfg..  groups values by class
     *
     * converts
     *   array(
     *      'methodCollect' => false,
     *      'emailMask' => 123,
     *   )
     * to
     *   array(
     *       'abstracter' => array(
     *           'methodCollect' => false,
     *       ),
     *       'errorHandler' => array(
     *           'emailer' => array(
     *               'emailMask' => 123,
     *           ),
     *       ),
     *   )
     *
     * @param array $cfg config array
     *
     * @return array
     */
    private function normalizeArray(array $cfg)
    {
        $return = array();
        foreach ($cfg as $path => $v) {
            $ref = &$return;
            $path = $this->normalizePath($path);
            foreach ($path as $k) {
                if (!isset($ref[$k])) {
                    $ref[$k] = array();
                }
                $ref = &$ref[$k];
            }
            $ref = \is_array($v)
                ? \array_merge($ref, $v)
                : $v;
        }
        return $return;
    }

    /**
     * Normalize string path
     * Returns one of
     *     ['*']             all config values grouped by class
     *     ['class']         we want all config values for class
     *     ['class', key...] want specific value from this class'
     *
     * 'class' may be debug
     *
     * @param array|string|null $path path
     *
     * @return array
     */
    private function normalizePath($path)
    {
        if (\is_string($path)) {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        if (\in_array($path, [null, []], true) || $path[0] === '*') {
            return ['*'];
        }
        if (\end($path) === '*') {
            \array_pop($path);
        }
        return isset($this->configKeys[$path[0]])
            ? $path
            : $this->normalizePathFind($path);
    }

    /**
     * Find config key's full path in this->configKeys
     *
     * @param array $path Config path
     *
     * @return array
     */
    private function normalizePathFind($path)
    {
        if (\count($path) > 1) {
            \array_unshift($path, 'debug');
            return $path;
        }
        $pathNew = $this->debug->arrayUtil->searchRecursive($path[0], $this->configKeys, true);
        $pathNew =  $pathNew
            ? $pathNew
            : \array_merge(['debug'], $path);
        if (\end($pathNew) !== \end($path)) {
            \array_pop($pathNew);
            $pathNew[] = \end($path);
        }
        return $pathNew;
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
        $matches = array();
        if (\in_array($debugProp, $this->invokedServices, true)) {
            $obj = $this->debug->{$debugProp};
        } elseif (\preg_match('/^(dump|route)(.+)$/', $debugProp, $matches)) {
            $cat = $matches[1];
            $what = $matches[2];
            $func = 'get' . \ucfirst($cat);
            $obj = $this->debug->{$func}($what);
        }
        if (\is_object($obj)) {
            $this->debug->{$debugProp}->setCfg($cfg);
            return;
        }
        if (isset($this->valuesPending[$debugProp])) {
            // update valuesPending
            $this->valuesPending[$debugProp] = \array_merge($this->valuesPending[$debugProp], $cfg);
            return;
        }
        // set valuesPending
        $this->valuesPending[$debugProp] = $cfg;
    }
}

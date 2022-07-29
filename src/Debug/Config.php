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

use bdk\Debug;
use bdk\Debug\ConfigurableInterface;

/**
 * Configuration manager
 */
class Config
{
    protected $debug;
    protected $debugPropChecked = array();
    protected $valuesPending = array();
    protected $invokedServices = array();
    protected $configKeys = array(
        'debug' => array(
            // any key not found falls under 'debug'...
        ),
        'abstracter' => array(
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
            'methodAttributeCollect',
            'methodAttributeOutput',
            'methodCache',
            'methodCollect',
            'methodDescOutput',
            'methodOutput',
            'objAttributeObj',
            'objAttributeOutput',
            'objectsExclude',
            'objectSort',
            'objectsWhitelist',
            'paramAttributeCollect',
            'paramAttributeOutput',
            'phpDocCollect',
            'phpDocOutput',
            'propAttributeCollect',
            'propAttributeOutput',
            'stringMaxLen',
            'stringMinLen',
            'toStringOutput',
            'useDebugInfo',
        ),
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
            'emailer' => array(
                'dateTimeFmt',
                'emailBacktraceDumper',
                // 'emailFrom',
                // 'emailFunc',
                'emailMask',
                'emailMin',
                'emailThrottledSummary',
                'emailTraceMask',
                // 'emailTo',
            ),
            'enableStats',
            'stats' => array(
                'dataStoreFactory',
                'errorStatsFile',
            ),
        ),
        'routeHtml' => array(
            'css',
            'drawer',
            'filepathCss',
            'filepathScript',
            'jqueryUrl',
            'outputCss',
            'outputScript',
            'sidebar',
            'tooltip',
        ),
        'routeStream' => array(
            'ansi',
            'stream',
        )
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
     *    set('key', 'value')
     *    set('level1.level2', 'value')
     *    set(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * Triggers a `Debug::EVENT_CONFIG` event that contains all changed values
     *
     * @param array|string $path  (string) path or array of values
     * @param mixed        $value if setting via path, this is the value
     *
     * @return mixed previous value(s)
     */
    public function set($path, $value = null)
    {
        if (\is_array($path)) {
            $values = $this->normalizeArray($path);
            return $this->doSet($values);
        }
        $path = $this->normalizePath($path);
        $values = array();
        $this->debug->arrayUtil->pathSet($values, $path, $value);
        $return = $this->doSet($values);
        return $this->debug->arrayUtil->pathGet($return, $path);
    }

    /**
     * Set cfg values for Debug and child classes
     *
     * @param array $configs config values grouped by class
     *
     * @return array previous values
     */
    private function doSet($configs)
    {
        if (!$configs) {
            return array();
        }
        /*
            Set previous (return) values
        */
        $return = array();
        foreach ($configs as $debugProp => $cfg) {
            $cfgWas = $debugProp === 'debug'
                ? $this->debug->getCfg(null, Debug::CONFIG_DEBUG)
                : $this->getPropCfg($debugProp, array(), true, false);
            $return[$debugProp] = \array_intersect_key($cfgWas, $cfg);
        }
        /*
            Publish config event first... it may add/update debugProp values
        */
        $configs = $this->debug->eventManager->publish(
            Debug::EVENT_CONFIG,
            $this->debug,
            $configs
        )->getValues();
        /*
            Now set the values
        */
        unset($configs['debug']); // debug uses a Debug::EVENT_CONFIG subscriber to set the value
        foreach ($configs as $debugProp => $cfg) {
            $this->setPropCfg($debugProp, $cfg);
        }
        return $return;
    }

    /**
     * Get debug property config value(s)
     *
     * @param string $debugProp  debug property name
     * @param array  $path       path/key
     * @param bool   $forInit    (false) Get values for bootstap (don't initialize obj)
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
        return $val !== null
            ? $val
            : $this->getPropCfgFromObj($debugProp, $path, $forInit);
    }

    /**
     * Get value from object
     *
     * @param string $debugProp debug property name
     * @param array  $path      path/key
     * @param bool   $forInit   (false) Get values for bootstap (don't initialize obj)
     *
     * @return mixed
     */
    private function getPropCfgFromObj($debugProp, $path = array(), $forInit = false)
    {
        $obj = null;
        $matches = array();
        if ($debugProp === 'debug') {
            return $this->debug->getCfg($path, Debug::CONFIG_DEBUG);
        }
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
    private function normalizeArray($cfg)
    {
        $return = array();
        foreach ($cfg as $path => $v) {
            $ref = &$return;
            $path = isset($this->configKeys[$path])
                ? array($path)
                : $this->normalizePath($path);
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
     * Returns either
     *     array('*')             all config values grouped by class
     *     array('class')         we want all config values for class
     *     array('class', key...) want specific value from this class'
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
        if (\in_array($path, array(null, array()), true) || $path[0] === '*') {
            return array('*');
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
            : \array_merge(array('debug'), $path);
        if (\end($pathNew) !== \end($path)) {
            \array_pop($pathNew);
            $pathNew[] = \end($path);
        }
        return $pathNew;
    }

    /**
     * Set debug service config value(s)
     *
     * @param string $debugProp debug property name
     * @param array  $cfg       property config values
     *
     * @return void
     */
    private function setPropCfg($debugProp, $cfg)
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

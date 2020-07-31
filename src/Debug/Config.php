<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use bdk\Debug;

/**
 * Configuration manager
 */
class Config
{

    protected $debug;
    protected $configKeys;
    protected $debugPropChecked = array();
    protected $valuesPending = array();

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->valuesPending['errorEmailer']['emailBacktraceDumper'] = function ($backtrace) use ($debug) {
            return $debug->getDump('text')->dump($backtrace);
        };
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
        $forInit = $forInit === true || $forInit === Debug::CONFIG_INIT;
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
        $val = $this->getPropCfg($debugProp, $path, $forInit);
        if ($forInit && $val === null) {
            $val = array();
        }
        return $val;
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
     * @param array|string $path  path or array of values
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
        $this->debug->utility->arrayPathSet($values, $path, $value);
        $return = $this->doSet($values);
        return $this->debug->utility->arrayPathGet($return, $path);
    }

    /**
     * Set cfg values for Debug and child classes
     *
     * @param array $cfg config values grouped by class
     *
     * @return array previous values
     */
    private function doSet($cfg)
    {
        if (!$cfg) {
            return array();
        }
        $return = array();
        $cfg = $this->setDupeValues($cfg);
        foreach ($cfg as $debugProp => $v) {
            if ($debugProp === 'debug') {
                $return[$debugProp] = \array_intersect_key($this->debug->getCfg(null, Debug::CONFIG_DEBUG), $v);
                // debug uses a Debug::EVENT_CONFIG subscriber to set the value
                continue;
            }
            $return[$debugProp] = $this->setPropCfg($debugProp, $v);
        }
        $this->debug->eventManager->publish(
            Debug::EVENT_CONFIG,
            $this->debug,
            $cfg
        );
        return $return;
    }

    /**
     * get available config keys for objects
     *
     * @return array
     */
    private function getConfigKeys()
    {
        if (isset($this->configKeys)) {
            return $this->configKeys;
        }
        $this->configKeys = array(
            'debug' => array(
                // any key not found falls under 'debug'...
            ),
            'abstracter' => array(
                'cacheMethods',
                'collectConstants',
                'collectMethods',
                'fullyQualifyPhpDocType',
                'objectsExclude',
                'objectSort',
                'outputConstants',
                'outputMethodDesc',
                'outputMethods',
                'useDebugInfo',
            ),
            'errorEmailer' => array(
                'emailBacktraceDumper',
                // 'emailFrom',
                // 'emailFunc',
                'emailMask',
                'emailMin',
                'emailThrottledSummary',
                'emailThrottleFile',
                'emailThrottleRead',
                'emailThrottleWrite',
                // 'emailTo',
                'emailTraceMask',
            ),
            'errorHandler' => array(
                'continueToPrevHandler',
                'errorFactory',
                'errorReporting',
                'onError',
                'onEUserError',
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
            ),
            'routeStream' => array(
                'ansi',
                'stream',
            )
        );
        return $this->configKeys;
    }

    /**
     * Get debug property config value(s)
     *
     * @param string $debugProp  debug property name
     * @param array  $path       path/key
     * @param bool   $forInit    Get values for bootstap
     * @param bool   $delPending Delete pending values (if forInit)
     *
     * @return mixed
     */
    private function getPropCfg($debugProp, $path = array(), $forInit = false, $delPending = true)
    {
        if ($debugProp === 'debug') {
            return $this->debug->getCfg($path, Debug::CONFIG_DEBUG);
        }
        if (isset($this->valuesPending[$debugProp])) {
            $val = $this->debug->utility->arrayPathGet($this->valuesPending[$debugProp], $path);
            if ($forInit && $delPending) {
                unset($this->valuesPending[$debugProp]);
            }
            if ($val !== null) {
                return $val;
            }
        }
        if ($forInit) {
            return array();
        }
        $matches = array();
        $obj = $this->debug->{$debugProp};
        if (\preg_match('/^(dump|route)(.+)$/', $debugProp, $matches)) {
            $cat = $matches[1];
            $what = $matches[2];
            $func = 'get' . \ucfirst($cat);
            $obj = $this->debug->{$func}($what);
        }
        if ($obj) {
            $path = \implode('/', $path);
            return $obj->getCfg($path);
        }
        return null;
    }

    /**
     * Normalizes cfg..  groups values by class
     *
     * converts
     *   array(
     *      'collectMethods' => false,
     *   )
     * to
     *   array(
     *       'abstracter' => array(
     *           'collectMethods' => false,
     *       )
     *   )
     *
     * @param array $cfg config array
     *
     * @return array
     */
    private function normalizeArray($cfg)
    {
        $return = array();
        $configKeys = $this->getConfigKeys();
        foreach ($cfg as $k => $v) {
            $translated = false;
            foreach ($configKeys as $objName => $objKeys) {
                if (\is_array($v)) {
                    if ($k === $objName) {
                        $return[$objName] = isset($return[$objName])
                            ? \array_merge($return[$objName], $v)
                            : $v;
                        $translated = true;
                        break;
                    }
                    if (isset($configKeys[$k])) {
                        continue;
                    }
                }
                if (\in_array($k, $objKeys)) {
                    $return[$objName][$k] = $v;
                    $translated = true;
                    break;
                }
            }
            if ($translated === false) {
                $return['debug'][$k] = $v;
            }
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
     * @param array|string $path path
     *
     * @return array
     */
    private function normalizePath($path)
    {
        if (\is_array($path) === false) {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        if (\count($path) === 0 || $path[0] === '*') {
            return array('*');
        }
        $configKeys = $this->getConfigKeys();
        $found = false;
        foreach ($configKeys as $objName => $objKeys) {
            if ($path[0] === $objName) {
                $found = true;
                break;
            }
            if (\in_array($path[0], $objKeys)) {
                $found = true;
                \array_unshift($path, $objName);
                break;
            }
        }
        if (!$found) {
            // we didn't find our key... assume debug
            \array_unshift($path, 'debug');
        }
        if (\end($path) === '*') {
            \array_pop($path);
        }
        return $path;
    }

    /**
     * some config values exist in multiple modules
     *
     * @param array $values values
     *
     * @return array
     */
    private function setDupeValues($values)
    {
        foreach (array('emailFrom','emailFunc','emailTo') as $key) {
            if (isset($values['debug'][$key]) && !isset($values['errorEmailer'][$key])) {
                // also set for errorEmailer
                $values['errorEmailer'][$key] = $values['debug'][$key];
            }
        }
        return $values;
    }

    /**
     * Set debug property value(s)
     *
     * @param string $debugProp debug property name
     * @param array  $cfg       property config values
     *
     * @return array existing config
     */
    private function setPropCfg($debugProp, $cfg)
    {
        $obj = null;
        $matches = array();
        $serviceVal = $this->debug->getCfg('services/' . $debugProp, Debug::CONFIG_DEBUG);
        $isLoadedService = $serviceVal !== null && !($serviceVal instanceof \Closure);
        if ($isLoadedService) {
            $obj = $this->debug->{$debugProp};
        } elseif (\preg_match('/^(dump|route)(.+)$/', $debugProp, $matches)) {
            $obj = $this->debug->{$debugProp};
        }
        if ($obj && \is_object($obj)) {
            $return = \array_intersect_key($obj->getCfg(), $cfg);
            $this->debug->{$debugProp}->setCfg($cfg);
            return $return;
        }
        if (isset($this->valuesPending[$debugProp])) {
            $return = \array_intersect_key($this->valuesPending[$debugProp], $cfg);
            $this->valuesPending[$debugProp] = \array_merge($this->valuesPending[$debugProp], $cfg);
            return $return;
        }
        $this->valuesPending[$debugProp] = $cfg;
        return array();
    }
}

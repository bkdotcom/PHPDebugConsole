<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
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
    protected $values = array();
    protected $valuesPending = array();

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     * @param array $cfg   configuration
     */
    public function __construct(Debug $debug, &$cfg = array())
    {
        $this->values = &$cfg;
        $this->debug = $debug;
        $this->valuesPending['errorEmailer']['emailBacktraceDumper'] = function ($backtrace) use ($debug) {
            return $debug->dumpText->dump($backtrace);
        };
    }

    /**
     * Get debug or child configuration value(s)
     *
     * @param string $path    what to get
     * @param mixed  $default default value
     *
     * @return mixed
     */
    public function getValue($path, $default = null)
    {
        $path = $this->normalizePath($path);
        $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        if (empty($path)) {
            return $this->getValues('*');
        }
        $classname = \array_shift($path);
        if ($classname == 'debug') {
            if ($path === array('route') && $this->values['route'] == 'auto') {
                return $this->getDefaultRoute();
            }
            return $this->debug->utilities->arrayPathGet($this->values, $path);
        }
        if (isset($this->debug->{$classname}) && \is_object($this->debug->{$classname})) {
            $pathRel = \implode('/', $path);
            return $this->debug->{$classname}->getCfg($pathRel, $default);
        }
        if (isset($this->valuesPending[$classname]) && $path) {
            // want a config value of obj that has not yet been instantiated...
            $val = $this->debug->utilities->arrayPathGet($this->valuesPending[$classname], $path);
            if ($val !== null) {
                return $val;
            }
        }
        $obj = $this->debug->{$classname};
        if ($obj) {
            // getting value of previously uninitialized obj
            $pathRel = \implode('/', $path);
            return $this->debug->{$classname}->getCfg($pathRel, $default);
        }
        return $default;
    }

    /**
     * Get all values
     *
     * @param string $classname (optional) classname
     *
     * @return array
     */
    public function getValues($classname = null)
    {
        if (!$classname) {
            return $this->values;
        }
        if ($classname === '*') {
            foreach (\array_keys($this->configKeys) as $classname) {
                if (isset($this->debug->{$classname})) {
                    $values[$classname] = $this->debug->{$classname}->getCfg();
                } elseif (isset($this->valuesPending[$classname])) {
                    $values[$classname] = $this->valuesPending[$classname];
                } else {
                    $values[$classname] = array();
                }
            }
            \ksort($values);
            $values = array('debug' => $this->values) + $values;
            return $values;
        }
        if (!isset($this->valuesPending[$classname])) {
            return array();
        }
        $return = $this->valuesPending[$classname];
        unset($this->valuesPending[$classname]);
        return $return;
    }

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg('level1.level2', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * Setting/updating 'key' will also set 'collect' and 'output'
     *
     * Triggers a debug.config event that contains all changed values
     *
     * @param string $path key/path or cfg array
     * @param mixed  $val  (optional) value
     *
     * @return mixed
     */
    public function setValue($path, $val = null)
    {
        $path = $this->normalizePath($path);
        $values = $this->keyValToArray($path, $val);
        $values = $this->setDupeValues($values);
        $return = $this->doSetCfg($values);
        return $this->debug->utilities->arrayPathGet($return, $path);
    }

    /**
     * Set multiple config values
     *
     * @param array $values key/value array
     *                         may be organized by classname
     *
     * @return array
     */
    public function setValues(array $values = array())
    {
        $values = $this->normalizeArray($values);
        $values = $this->setDupeValues($values);
        return $this->doSetCfg($values);
    }

    /**
     * Set cfg values for Debug and child classes
     *
     * @param array $cfg config values grouped by class
     *
     * @return array previous values
     */
    private function doSetCfg($cfg)
    {
        $return = array();
        if ($cfg) {
            $event = $this->debug->eventManager->publish(
                'debug.config',
                $this->debug,
                $cfg
            );
            $cfg = $event->getValues();
        }
        foreach ($cfg as $classname => $v) {
            if ($classname == 'debug') {
                $return[$classname] = \array_intersect_key($this->values, $v);
                $this->setDebugValues($v);
            } elseif (isset($this->debug->{$classname}) && \is_object($this->debug->{$classname})) {
                $return[$classname] = \array_intersect_key($this->debug->{$classname}->getCfg(), $v);
                $this->debug->{$classname}->setCfg($v);
            } elseif (isset($this->valuesPending[$classname])) {
                $return[$classname] = \array_intersect_key($this->valuesPending[$classname], $v);
                $this->valuesPending[$classname] = \array_merge($this->valuesPending[$classname], $v);
            } else {
                $return[$classname] = array();
                $this->valuesPending[$classname] = $v;
            }
        }
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
            'errorHandler' => \array_keys($this->debug->errorHandler->getCfg()),
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
     * Determine default route
     *
     * @return string
     */
    private function getDefaultRoute()
    {
        $interface = $this->debug->utilities->getInterface();
        if ($interface == 'ajax') {
            $ret = $this->values['routeNonHtml'];
        } elseif ($interface == 'http') {
            $ret = 'html';
            $contentType = $this->debug->getResponseContentType();
            if ($contentType && $contentType !== 'text/html') {
                $ret = $this->values['routeNonHtml'];
            }
        } else {
            $ret = 'stream';
        }
        return $ret;
    }

    /**
     * Convert key/path & val to array
     *
     * @param string $key key or path
     * @param mixed  $val value
     *
     * @return array
     */
    private function keyValToArray($key, $val)
    {
        $new = array();
        $path = \preg_split('#[\./]#', $key);
        $ref = &$new;
        foreach ($path as $k) {
            $ref[$k] = array(); // initialize this level
            $ref = &$ref[$k];
        }
        $ref = $val;
        return $new;
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
        $return = array(
            'debug' => array(),  // initialize with debug... we want debug values first
        );
        $configKeys = $this->getConfigKeys();
        foreach ($cfg as $k => $v) {
            $translated = false;
            foreach ($configKeys as $objName => $objKeys) {
                if ($k == $objName && \is_array($v)) {
                    $return[$objName] = isset($return[$objName])
                        ? \array_merge($return[$objName], $v)
                        : $v;
                    $translated = true;
                    break;
                } elseif (\is_array($v) && isset($configKeys[$k])) {
                    continue;
                } elseif (\in_array($k, $objKeys)) {
                    $return[$objName][$k] = $v;
                    $translated = true;
                    break;
                }
            }
            if (!$translated) {
                $return['debug'][$k] = $v;
            }
        }
        if (!$return['debug']) {
            unset($return['debug']);
        }
        return $return;
    }

    /**
     * Normalize string path
     * Returns either
     *     ''         empty string = all config values grouped by class
     *     '{class}'         we want all config values for class
     *     '{class}/{key}    want specific value from this class'
     *   {class} may be debug
     *
     * @param string $path string path
     *
     * @return string
     */
    private function normalizePath($path)
    {
        $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        if (\count($path) == 0 || $path[0] == '*') {
            return '';
        }
        $configKeys = $this->getConfigKeys();
        $found = false;
        foreach ($configKeys as $objName => $objKeys) {
            if (\in_array($path[0], $objKeys) && $objName !== 'debug') {
                $found = true;
                \array_unshift($path, $objName);
                break;
            }
            if ($path[0] == $objName && !empty($path[1])) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            // we didn't find our key... assume debug
            \array_unshift($path, 'debug');
        }
        if (\end($path) == '*') {
            \array_pop($path);
        }
        return \implode('/', $path);
    }

    /**
     * Test $_REQUEST['debug'] against passed key
     * return collect & output values
     *
     * @param string $key secret key
     *
     * @return array
     */
    private function setDebugKeyValues($key)
    {
        $values = array();
        // update 'collect and output'
        $requestKey = null;
        if (isset($_GET['debug'])) {
            $requestKey = $_REQUEST['debug'];
        } elseif (isset($_COOKIE['debug'])) {
            $requestKey = $_COOKIE['debug'];
        }
        $isValidKey = $requestKey == $key;
        if ($isValidKey) {
            // only enable collect / don't disable it
            $values['collect'] = true;
        }
        $values['output'] = $isValidKey;
        return $values;
    }

    /**
     * Set Debug config
     *
     * @param array $values Debug config values
     *
     * @return void
     */
    private function setDebugValues($values)
    {
        $isCli = \in_array($this->debug->utilities->getInterface(), array('cli', 'cron'));
        if (isset($values['key']) && !$isCli) {
            $values = \array_merge($values, $this->setDebugKeyValues($values['key']));
        }
        if (isset($values['logEnvInfo'])) {
            $keys = \array_keys($this->values['logEnvInfo']);
            if (\is_bool($values['logEnvInfo'])) {
                $values['logEnvInfo'] = \array_fill_keys($keys, $values['logEnvInfo']);
            } elseif ($this->debug->utilities->isList($values['logEnvInfo'])) {
                $values['logEnvInfo'] = \array_merge(
                    \array_fill_keys($keys, false),
                    \array_fill_keys($values['logEnvInfo'], true)
                );
            }
        }
        if (isset($values['logServerKeys'])) {
            // don't append, replace
            $this->values['logServerKeys'] = array();
        }
        $this->values = $this->debug->utilities->arrayMergeDeep($this->values, $values);
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
}

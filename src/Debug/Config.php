<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.0.1
 */

namespace bdk\Debug;

use bdk\PubSub\Event;

/**
 * Configuration manager
 */
class Config
{

    protected $cfg = array();
    protected $cfgLazy = array();  // store config for child classes that haven't been loaded yet
    protected $debug;
    protected $configKeys;

    /**
     * Constructor
     *
     * @param object $debug debug object
     * @param array  $cfg   configuration
     */
    public function __construct($debug, &$cfg)
    {
        $this->cfg = &$cfg;
        $this->debug = $debug;
    }

    /**
     * Get debug or child configuration value(s)
     *
     * @param string $path what to get
     *
     * @return mixed
     */
    public function getCfg($path = '')
    {
        $path = $this->expandPath($path);
        $path = preg_split('#[\./]#', $path);
        if (isset($path[1]) && $path[1] === '*') {
            array_pop($path);
        }
        $level1 = isset($path[0]) ? $path[0] : null;
        if (is_object($this->debug->{$level1})) {
            // child class config value
            $pathRel = implode('/', array_slice($path, 1));
            return $this->debug->{$level1}->getCfg($pathRel);
        }
        if ($level1 == 'debug') {
            array_shift($path);
        }
        $ret = $this->cfg;
        foreach ($path as $k) {
            if (isset($ret[$k])) {
                $ret = $ret[$k];
            } else {
                $ret = null;
                break;
            }
        }
        return $ret;
    }

    /**
     * Get config for lazy-loaded class
     *
     * @param string $lazyPropName name of property
     *
     * @return array
     */
    public function getCfgLazy($lazyPropName)
    {
        return isset($this->cfgLazy[$lazyPropName])
            ? $this->cfgLazy[$lazyPropName]
            : array();
    }

    /**
     * Set one or more config values
     *
     * If setting a value via method a or b, old value is returned
     *
     * Setting/updating 'key' will also set 'collect' and 'output'
     *
     *    setCfg('key', 'value')
     *    setCfg('level1.level2', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string|array $pathOrVals key/path or cfg array
     * @param mixed        $val        (optional) value
     *
     * @return mixed
     */
    public function setCfg($pathOrVals, $val = null)
    {
        $return = is_string($pathOrVals)
            ? $this->getCfg($pathOrVals)
            : null;
        if (is_string($pathOrVals)) {
            $path = $this->expandPath($pathOrVals);
            $cfg = $this->keyValToArray($path, $val);
        } elseif (is_array($pathOrVals)) {
            $cfg = $this->normalizeCfgArray($pathOrVals);
        }
        $cfg = $this->setCopyValues($cfg);
        foreach ($cfg as $k => $v) {
            if ($k == 'debug') {
                $this->setDebugCfg($v);
            } elseif (isset($this->debug->{$k}) && is_object($this->debug->{$k})) {
                $this->debug->{$k}->setCfg($v);
            } else {
                $this->cfgLazy[$k] = $v;
            }
        }
        if ($cfg) {
            $this->debug->eventManager->publish(
                'debug.config',
                $this->debug,
                array('config'=>$cfg)
            );
        }
        return $return;
    }

    /**
     * Test $_REQUEST['debug'] against passed key
     * return collect & output values
     *
     * @param string $key secret key
     *
     * @return array
     */
    private function debugKeyValues($key)
    {
        $values = array();
        // update 'collect and output'
        $requestKey = null;
        if (isset($_REQUEST['debug'])) {
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
     * [expandPath description]
     *
     * @param string $path string path
     *
     * @return string
     */
    private function expandPath($path)
    {
        $path = array_filter(preg_split('#[\./]#', $path), 'strlen');
        if (count($path) == 1) {
            $configKeys = $this->getConfigKeys();
            foreach ($configKeys as $objName => $objKeys) {
                if (in_array($path[0], $objKeys)) {
                    array_unshift($path, $objName);
                    break;
                }
                if ($path[0] == $objName) {
                    $path[] = '*';
                    break;
                }
            }
        }
        if (count($path) <= 1) {
            array_unshift($path, 'debug');
        }
        return implode('/', $path);
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
                'output',   // any key not found falls under 'debug'...
                            //  'output' listed to disambiguate from output object
            ),
            'abstracter' => array(
                'collectConstants',
                'collectMethods',
                'objectsExclude',
                'objectSort',
                'useDebugInfo',
            ),
            'errorEmailer' => array(
                // 'emailFunc',
                'emailMask',
                'emailMin',
                'emailThrottleFile',
                'emailThrottledSummary',
                // 'emailTo',
                'emailTraceMask',
            ),
            'errorHandler' => array_keys($this->debug->errorHandler->getCfg()),
            'output' => array(
                'addBR',
                'css',
                'displayListKeys',
                'filepathCss',
                'filepathScript',
                'onOutput',
                'outputAs',
                'outputAsDefaultNonHtml',
                'outputConstants',
                'outputCss',
                'outputMethodDescription',
                'outputMethods',
                'outputScript',
            ),
        );
        return $this->configKeys;
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
        $path = preg_split('#[\./]#', $key);
        $ref = &$new;
        foreach ($path as $k) {
            $ref[$k] = array(); // initialize this level
            $ref = &$ref[$k];
        }
        $ref = $val;
        return $new;
    }

    /**
     * translate configuration keys
     *
     * most / common configuration values may be passed in a flat array structure, or without
     *    the leading child prefix
     *    for example, abstracter.collectMethods can be get/set in 4 different ways
     *         setCfg('collectMethods', false);
     *         setCfg('abtracter.collectMethods', false);
     *         setCfg(array(
     *           'collectMethods'=>false
     *         ));
     *         setCfg(array(
     *           'abstracter'=>array(
     *              'collectMethods'=>false,
     *           )
     *         ));
     *
     * @param array $cfg string key-path or config array
     *
     * @return mixed
     */
    private function normalizeCfgArray($cfg)
    {
        $return = array();
        $configKeys = $this->getConfigKeys();
        foreach ($cfg as $k => $v) {
            $translated = false;
            foreach ($configKeys as $objName => $objKeys) {
                if ($k == $objName && is_array($v)) {
                    $return[$objName] = isset($return[$objName])
                        ? array_merge($return[$objName], $v)
                        : $v;
                    $translated = true;
                    break;
                } elseif (in_array($k, $objKeys)) {
                    $return[$objName][$k] = $v;
                    $translated = true;
                    break;
                }
            }
            if (!$translated) {
                $return['debug'][$k] = $v;
            }
        }
        return $return;
    }

    /**
     * some config values exist in multiple modules
     *
     * @param array $values values
     *
     * @return array
     */
    private function setCopyValues($values)
    {
        if (isset($values['debug']['emailLog']) && $values['debug']['emailLog'] === true) {
            $values['debug']['emailLog'] = 'onError';
        }
        foreach (array('emailFunc','emailTo') as $key) {
            if (isset($values['debug'][$key]) && !isset($values['errorEmailer'][$key])) {
                // also set for errorEmailer
                $values['errorEmailer'][$key] = $values['debug'][$key];
            }
        }
        return $values;
    }

    /**
     * Set Debug config
     *
     * @param array $cfg Debug config values
     *
     * @return void
     */
    private function setDebugCfg($cfg)
    {
        if (isset($cfg['key'])) {
            $cfg = array_merge($cfg, $this->debugKeyValues($cfg['key']));
        }
        if (isset($cfg['logServerKeys'])) {
            // don't append, replace
            $this->cfg['logServerKeys'] = array();
        }
        /*
            Replace - not append - subscriber set via setCfg
        */
        if (isset($cfg['onLog']) && isset($this->cfg['onLog'])) {
            $this->debug->eventManager->unsubscribe('debug.log', $this->cfg['onLog']);
        }
        $this->cfg = $this->debug->utilities->arrayMergeDeep($this->cfg, $cfg);
        if (isset($cfg['onBootstrap'])) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            if ($backtrace[2]['function'] == '__construct') {
                // we're being called from construct... treat this "normal"
                $this->debug->eventManager->subscribe('debug.bootstrap', $cfg['onBootstrap']);
            } else {
                // boostrap has already occured
                call_user_func($cfg['onBootstrap'], new Event($this->debug));
            }
            unset($this->cfg['onBootstrap']);
        }
        if (isset($cfg['onLog'])) {
            $this->debug->eventManager->subscribe('debug.log', $cfg['onLog']);
        }
        if (isset($cfg['file'])) {
            $this->debug->addPlugin($this->debug->output->outputFile);
        }
    }
}

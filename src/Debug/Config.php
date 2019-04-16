<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug;

use bdk\Debug;
use bdk\Debug\FileStreamWrapper;
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
     * @param \bdk\Debug $debug debug object
     * @param array      $cfg   configuration
     */
    public function __construct(Debug $debug, &$cfg)
    {
        $this->cfg = &$cfg;
        $this->debug = $debug;
        $this->cfgLazy['errorEmailer']['emailBacktraceDumper'] = function ($backtrace) use ($debug) {
            return $debug->output->text->dump($backtrace);
        };
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
        $path = $this->normalizePath($path);
        $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        if (empty($path)) {
            return $this->getCfgAll();
        }
        $classname = \array_shift($path);
        if ($classname == 'debug') {
            return $this->debug->utilities->arrayPathGet($this->cfg, $path);
        } elseif (isset($this->debug->{$classname}) && \is_object($this->debug->{$classname})) {
            $pathRel = \implode('/', $path);
            return $this->debug->{$classname}->getCfg($pathRel);
        }
        if (isset($this->cfgLazy[$classname]) && $path) {
            // want a config value of obj that has not yet been instantiated...
            // value may in cfgLazy
            $val = $this->debug->utilities->arrayPathGet($this->cfgLazy[$classname], $path);
            if ($val !== null) {
                return $val;
            }
        }
        if (isset($this->cfg['services'][$classname])) {
            // getting value of uninitialized obj
            // inititalize obj and retry
            $pathRel = \implode('/', $path);
            return $this->debug->{$classname}->getCfg($pathRel);
        }
        return null;
    }

    /**
     * Get config for lazy-loaded class
     *
     * @param string $name name of property being lazy loaded
     *
     * @return array
     */
    public function getCfgLazy($name)
    {
        if (!isset($this->cfgLazy[$name])) {
            return array();
        }
        $return = $this->cfgLazy[$name];
        unset($this->cfgLazy[$name]);
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
     * @param string|array $pathOrVals key/path or cfg array
     * @param mixed        $val        (optional) value
     *
     * @return mixed
     */
    public function setCfg($pathOrVals, $val = null)
    {
        if (\is_array($pathOrVals)) {
            $cfg = $this->normalizeArray($pathOrVals);
        } else {
            $path = $this->normalizePath($pathOrVals);
            $cfg = $this->keyValToArray($path, $val);
        }
        $cfg = $this->setCopyValues($cfg);
        $return = $this->doSetCfg($cfg);
        if (isset($this->cfgLazy['output']['outputAs'])) {
            $lazyPlugins = array('chromeLogger','firephp','html','script','text');
            if (\is_object($this->cfgLazy['output']['outputAs']) || !\in_array($this->cfgLazy['output']['outputAs'], $lazyPlugins)) {
                // output is likely a dependency
                $outputAs = $this->cfgLazy['output']['outputAs'];
                unset($this->cfgLazy['output']['outputAs']);
                // this will autoload output, which will pull in cfgLazy...
                //   we then set outputAs
                $this->debug->output->setCfg('outputAs', $outputAs);
            }
        }
        if (\is_string($pathOrVals)) {
            $return = $this->debug->utilities->arrayPathGet($return, $path);
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
     * Set cfg values for Debug and child classes
     *
     * @param array $cfg config values grouped by class
     *
     * @return array previous values
     */
    private function doSetCfg($cfg)
    {
        $return = array();
        foreach ($cfg as $k => $v) {
            if ($k == 'debug') {
                $return[$k] = \array_intersect_key($this->cfg, $v);
                $this->setDebugCfg($v);
            } elseif (isset($this->debug->{$k}) && \is_object($this->debug->{$k})) {
                $return[$k] = \array_intersect_key($this->getCfg($k.'/*'), $v);
                $this->debug->{$k}->setCfg($v);
            } elseif (isset($this->cfgLazy[$k])) {
                $return[$k] = \array_intersect_key($this->cfgLazy[$k], $v);
                $this->cfgLazy[$k] = \array_merge($this->cfgLazy[$k], $v);
            } else {
                $return[$k] = array();
                $this->cfgLazy[$k] = $v;
            }
        }
        return $return;
    }

    /**
     * Get config for debug.
     * If no path specified, config for debug and dependencies is returned
     *
     * @return mixed
     */
    private function getCfgAll()
    {
        $cfg = array();
        foreach (\array_keys($this->configKeys) as $classname) {
            if ($classname === 'debug') {
                $cfg['debug'] = $this->cfg;
            } elseif (isset($this->debug->{$classname})) {
                $cfg[$classname] = $this->debug->{$classname}->getCfg();
            } elseif (isset($this->cfgLazy[$classname])) {
                $cfg[$classname] = $this->cfgLazy[$classname];
            }
        }
        return $cfg;
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
                'objectsExclude',
                'objectSort',
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
                'outputHeaders',
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
        foreach (array('emailFrom','emailFunc','emailTo') as $key) {
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
            $cfg = \array_merge($cfg, $this->debugKeyValues($cfg['key']));
        }
        if (isset($cfg['logEnvInfo']) && \is_bool($cfg['logEnvInfo'])) {
            $keys = \array_keys($this->cfg['logEnvInfo']);
            $cfg['logEnvInfo'] = \array_fill_keys($keys, $cfg['logEnvInfo']);
        }
        if (isset($cfg['logServerKeys'])) {
            // don't append, replace
            $this->cfg['logServerKeys'] = array();
        }
        $this->cfg = $this->debug->utilities->arrayMergeDeep($this->cfg, $cfg);
    }
}

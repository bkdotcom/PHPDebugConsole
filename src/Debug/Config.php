<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug;

use bdk\Debug;
use bdk\PubSub\Event;
use bdk\Debug\FileStreamWrapper;

/**
 * Configuration manager
 */
class Config
{

    protected $cfg = array();
    protected $cfgLazy = array();  // store config for child classes that haven't been loaded yet
    protected $debug;
    protected $configKeys;
    private static $profilingEnabled = false;

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
        $path = \preg_split('#[\./]#', $path);
        if (isset($path[1]) && $path[1] === '*') {
            \array_pop($path);
        }
        $first = \array_shift($path);
        if ($first == 'debug') {
            return $this->debug->utilities->arrayPathGet($this->cfg, $path);
        } elseif (\is_object($this->debug->{$first})) {
            // child class config value
            $pathRel = \implode('/', $path);
            return $this->debug->{$first}->getCfg($pathRel);
        }
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
                // output may need to subscribe to events.... go ahead and load
                $this->debug->output;
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
                $return[$k] = $this->cfgLazy[$k];
                $this->cfgLazy[$k] = \array_merge($this->cfgLazy[$k], $v);
            } else {
                $return[$k] = array();
                $this->cfgLazy[$k] = $v;
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
                'output',   // any key not found falls under 'debug'...
                            //  'output' listed to disambiguate from output object
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
                // 'emailFunc',
                'emailBacktraceDumper',
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
     * [expandPath description]
     *
     * @param string $path string path
     *
     * @return string
     */
    private function normalizePath($path)
    {
        $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        if (\count($path) == 1) {
            $configKeys = $this->getConfigKeys();
            foreach ($configKeys as $objName => $objKeys) {
                if (\in_array($path[0], $objKeys)) {
                    \array_unshift($path, $objName);
                    break;
                }
                if ($path[0] == $objName) {
                    $path[] = '*';
                    break;
                }
            }
        }
        if (\count($path) <= 1 || $path[0] === 'logEnvInfo') {
            \array_unshift($path, 'debug');
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
            $cfg = \array_merge($cfg, $this->debugKeyValues($cfg['key']));
        }
        if (isset($cfg['logServerKeys'])) {
            // don't append, replace
            $this->cfg['logServerKeys'] = array();
        }
        $this->cfg = $this->debug->utilities->arrayMergeDeep($this->cfg, $cfg);
        if (isset($cfg['file'])) {
            $this->debug->addPlugin($this->debug->output->file);
        }
        if (isset($cfg['onBootstrap'])) {
            $this->setDebugOnBootstrap($cfg['onBootstrap']);
        }
        if (isset($cfg['onLog'])) {
            /*
                Replace - not append - subscriber set via setCfg
            */
            if (isset($this->cfg['onLog'])) {
                $this->debug->eventManager->unsubscribe('debug.log', $this->cfg['onLog']);
            }
            $this->debug->eventManager->subscribe('debug.log', $cfg['onLog']);
        }
        if ($this->cfg['enableProfiling'] && $this->cfg['collect'] && !static::$profilingEnabled) {
            static::$profilingEnabled = true;
            $pathsExclude = array(
                __DIR__,
            );
            FileStreamWrapper::register($pathsExclude);
            $this->debug->errorHandler->eventManager->subscribe(
                'errorHandler.error',
                array('\bdk\Debug\FileStreamWrapper', 'onError'),
                PHP_INT_MAX
            );
        }
    }

    /**
     * Handle setting onBootstrap cfg value
     *
     * @param callable $onBootstrap onBoostrap cfg value
     *
     * @return void
     */
    private function setDebugOnBootstrap($onBootstrap)
    {
        $callingFunc = null;
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($backtrace as $i => $frame) {
            if ($frame['function'] == 'setCfg') {
                $callingFunc = $backtrace[$i+1]['function'];
                break;
            }
        }
        if ($callingFunc == '__construct') {
            // we're being called from construct... subscribe
            $this->debug->eventManager->subscribe('debug.bootstrap', $onBootstrap);
        } else {
            // boostrap has already occured, so go ahead and call
            \call_user_func($onBootstrap, new Event($this->debug));
        }
        unset($this->cfg['onBootstrap']);
    }
}

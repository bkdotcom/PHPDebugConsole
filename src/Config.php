<?php
/**
 * Handle setting configuration values
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 */

namespace bdk\Debug;

/**
 * Config
 */
class Config
{

	protected $cfg = array();
	protected $debug;

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
	 * [get description]
	 *
	 * @param string $path what to get
	 *
	 * @return mixed
	 */
	public function getCfg($path = '')
	{
        $path = $this->translateKeys($path);
        $path = preg_split('#[\./]#', $path);
        if (isset($path[1]) && $path[1] === '*') {
            array_pop($path);
        }
        if (isset($this->debug->{$path[0]}) && is_object($this->debug->{$path[0]})) {
            // child class config value
            $pathRel = implode('/', array_slice($path, 1));
            $ret = $this->debug->{$path[0]}->getCfg($pathRel);
        } else {
            if ($path[0] == 'debug') {
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
        }
        return $ret;
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
     * @param string|array $path   path
     * @param mixed        $newVal value
     *
     * @return mixed
	 */
	public function setCfg($path, $newVal = null)
	{
        $return = is_string($path)
            ? $this->getCfg($path)
            : null;
        $new = $this->normalizeValues($path, $newVal);
        $new = $this->setCopyValues($new);
        if (isset($new['debug']['key'])) {
            $new['debug'] = array_merge(
                $new['debug'],
                $this->setGetKeyValues($new['debug']['key'])
            );
        }
        foreach ($new as $k => $v) {
            if ($k == 'debug') {
                if (isset($v['onLog'])) {
                    $this->debug->eventManager->addListener('debug.log', $v['onLog']);
                    unset($v['onLog']);
                }
                if (isset($v['onInit'])) {
                    $this->debug->eventManager->addListener('debug.init', $v['onInit']);
                    unset($v['onInit']);
                }
                if (isset($v['logServerKeys'])) {
                    // don't append, replace
                    $this->cfg['logServerKeys'] = array();
                }
                $this->cfg =  $this->debug->utilities->arrayMergeDeep($this->cfg, $v);
            } elseif (isset($this->debug->{$k}) && is_object($this->debug->{$k})) {
                $this->debug->{$k}->setCfg($v);
            }
        }
        return $return;
	}

    /**
     * normalize values
     *
     * @param string|array $path   [description]
     * @param mixed        $newVal [description]
     *
     * @return array
     */
    protected function normalizeValues($path, $newVal = null)
    {
        $new = array();
        $path = $this->translateKeys($path);
        if (is_string($path)) {
            // build $new array from the passed string
            $path = preg_split('#[\./]#', $path);
            $ref = &$new;
            foreach ($path as $k) {
                $ref[$k] = array(); // initialize this level
                $ref = &$ref[$k];
            }
            $ref = $newVal;
        } elseif (is_array($path)) {
            $new = $path;
        }
        return $new;
    }

    /**
     * some config values exist in multiple modules
     *
     * @param array $values values
     *
     * @return array
     */
    protected function setCopyValues($values)
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
     * Test $_REQUEST['debug'] against passed key
     * return collect/output values
     *
     * @param string $key secret key
     *
     * @return array
     */
    protected function setGetKeyValues($key)
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
     * @param mixed $mixed string key-path or config array
     *
     * @return mixed
     */
    protected static function translateKeys($mixed)
    {
        $subObjs = array(
            'debug' => array(
                'output',   // any key not found falls under 'debug'...
                            //  'output' listed to disambiguate from output object
            ),
            'abstracter' => array(
                'collectConstants',     // bool
                'collectMethods',       // bool
                'objectsExclude',       // array
                'objectSort',           // string
                'useDebugInfo',         // bool
            ),
            'errorEmailer' => array(
                // 'emailFunc',            // callable
                'emailMask',            // int
                'emailMin',             // int
                // 'emailTo',              // string
                'emailThrottleFile',    // string
                'emailThrottledSummary', // bool
                'emailTraceMask',       // int
            ),
            'errorHandler' => array(
                'continueToPrevHandler', // bool
                'errorReporting',       // int (mask)
                'onError',              // callable
            ),
            'output' => array(
                'addBR',                // bool
                'css',                  // string
                'filepathCss',          // string
                'filepathScript',       // string
                // 'firephpInc',
                // 'firephpOptions',
                'onOutput',             // callable
                'outputAs',             // string
                'outputAsDefaultNonHtml', // string
                'outputConstants',      // bool
                'outputCss',            // bool
                'outputMethodDescription', // bool
                'outputMethods',        // bool
                'outputScript',         // bool
            ),
        );
        if (is_string($mixed)) {
            $path = array_filter(preg_split('#[\./]#', $mixed), 'strlen');
            if (count($path) == 1) {
                foreach ($subObjs as $objName => $objKeys) {
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
        } elseif (is_array($mixed)) {
            $return = array();
            foreach ($mixed as $k => $v) {
                $translated = false;
                foreach ($subObjs as $objName => $objKeys) {
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
        return $mixed;
    }
}

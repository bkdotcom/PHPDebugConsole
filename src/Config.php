<?php
/**
 * Handle setting configuration values
 *
 * @package PHPDebugConsole
 * @author  Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.3.3
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
	public function get($path)
	{
        $path = $this->translateKeys($path);
        $path = preg_split('#[\./]#', $path);
        if (isset($this->debug->{$path[0]}) && is_object($this->debug->{$path[0]}) && isset($path[1])) {
            // child class config value
            $pathRel = implode('/', array_slice($path, 1));
            $ret = $this->debug->{$path[0]}->get($pathRel);
        } elseif ($path[0] == 'data') {
            // @deprecated
            array_shift($path);
            $path = implode('/', $path);
            $ret = $this->debug->dataGet($path);
        } else {
            if ($path[0] == 'cfg') {
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
     *    set('key', 'value')
     *    set('level1.level2', 'value')
     *    set(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string|array $path   path
     * @param mixed        $newVal value
     *
     * @return mixed
	 */
	public function set($path, $newVal = null)
	{
        $return = is_string($path)
            ? $this->get($path)
            : null;
        $new = $this->setGetNewValues($path, $newVal);
        $new = $this->setCopyValues($new);
        if (isset($new['cfg']['key'])) {
            $new['cfg'] = Utilities::arrayMergeDeep(
                $new['cfg'],
                $this->setGetKeyValues($new['cfg']['key'])
            );
        }
        if (isset($new['cfg']['collect']) && $new['cfg']['collect'] !== $this->cfg['collect']) {
            $new['data']['collectToggleCount'] = true;
        }
        foreach ($new as $k => $v) {
            if (isset($this->debug->{$k}) && is_object($this->debug->{$k})) {
                $return = $this->debug->{$k}->set($v);
            } elseif ($k == 'cfg') {
                $this->cfg =  Utilities::arrayMergeDeep($this->cfg, $v);
            } elseif ($k == 'data') {
                // @deprecated
                $this->debug->dataSet($v);
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
    protected function setCopyValues($values)
    {
        if (isset($values['cfg']['emailLog']) && $values['cfg']['emailLog'] === true) {
            $values['cfg']['emailLog'] = 'onError';
        }
        foreach (array('emailFunc','emailTo') as $key) {
            if (isset($values['cfg'][$key]) && !isset($values['errorHandler'][$key])) {
                // also set for errorHandler
                $values['errorHandler'][$key] = $values['cfg'][$key];
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
     * [setGetNew description]
     *
     * @param string|array $path   [description]
     * @param mixed        $newVal [description]
     *
     * @return array
     */
    protected function setGetNewValues($path, $newVal = null)
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
     * translate configuration keys
     *
     * @param mixed $mixed string key or config array
     *
     * @return mixed
     */
    protected static function translateKeys($mixed)
    {
        $objKeys = array(
            'varDump' => array(
                'addBR', 'objectSort', 'objectsExclude', 'useDebugInfo',
                'collectConstants', 'outputConstants', 'collectMethods', 'outputMethods',
            ),
            'errorHandler' => array('emailThrottledSummary', 'lastError', 'onError'),
            'output' => array(
                'css', 'filepathCss', 'filepathScript', 'firephpInc', 'firephpOptions',
                'onOutput', 'outputAs', 'outputCss', 'outputScript',
            ),
        );
        if (is_string($mixed)) {
            $path = preg_split('#[\./]#', $mixed);
            foreach ($objKeys as $objKey => $keys) {
                if (in_array($path[0], $keys)) {
                    array_unshift($path, $objKey);
                    break;
                }
            }
            if (count($path)==1) {
                array_unshift($path, 'cfg');
            }
            $mixed = implode('/', $path);
        } elseif (is_array($mixed)) {
            $mixedNew = array();
            foreach ($mixed as $k => $v) {
                $translated = false;
                foreach ($objKeys as $objKey => $keys) {
                    if ($k == $objKey && is_array($v)) {
                        $mixedNew[$objKey] = $v;
                        $translated = true;
                        break;
                    } elseif (in_array($k, $keys)) {
                        $mixedNew[$objKey][$k] = $v;
                        $translated = true;
                        break;
                    }
                }
                if (!$translated) {
                    $mixedNew['cfg'][$k] = $v;
                }
            }
            $mixed = $mixedNew;
        }
        return $mixed;
    }
}

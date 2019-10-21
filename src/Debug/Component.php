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

/**
 * Base "component" methods
 */
abstract class Component
{

    protected $cfg = array();

    /**
     * Magic getter
     *
     * @param string $prop property to get
     *
     * @return mixed
     */
    public function __get($prop)
    {
        $getter = 'get' . \ucfirst($prop);
        if (\method_exists($this, $getter)) {
            return $this->{$getter}();
        }
    }

    /**
     * Get config value(s)
     *
     * @param string $key (optional) what to get
     *
     * @return mixed
     */
    public function getCfg($key = null)
    {
        if ($key === null || \strlen($key) == 0) {
            return $this->cfg;
        }
        return isset($this->cfg[$key])
            ? $this->cfg[$key]
            : null;
    }

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param array|string $mixed key=>value array or key
     * @param mixed        $val   new value
     *
     * @return mixed returns previous value(s)
     */
    public function setCfg($mixed, $val = null)
    {
        $ret = null;
        if (\is_string($mixed)) {
            $ret = isset($this->cfg[$mixed])
                ? $this->cfg[$mixed]
                : null;
            $mixed = array($mixed => $val);
        } elseif (\is_array($mixed)) {
            $ret = \array_intersect_key($this->cfg, $mixed);
        }
        $this->cfg = \array_merge($this->cfg, $mixed);
        $this->postSetCfg($mixed);
        return $ret;
    }

    /**
     * Called by setCfg
     *
     * extend me to perform class specific config operations
     *
     * @param array $cfg new config values
     *
     * @return void
     */
    protected function postSetCfg($cfg = array())
    {
        return;
    }
}

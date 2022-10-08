<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.2
 */

namespace bdk\ErrorHandler;

/**
 * Base "component" methods
 */
class AbstractComponent
{
    /** @var array */
    protected $cfg = array();
    protected $readOnly = array();
    protected $setCfgMergeCallable = 'array_replace_recursive';

    /**
     * Magic getter
     * Get inaccessible / undefined properties
     * Lazy load child classes
     *
     * @param string $prop property to get
     *
     * @return mixed
     */
    public function __get($prop)
    {
        $getter = \preg_match('/^is[A-Z]/', $prop)
            ? $prop
            : 'get' . \ucfirst($prop);
        if (\method_exists($this, $getter)) {
            return $this->{$getter}();
        }
        if (\in_array($prop, $this->readOnly, true)) {
            return $this->{$prop};
        }
        return null;
    }

    /**
     * Magic isset
     *
     * @param string $prop property to check
     *
     * @return bool
     */
    public function __isset($prop)
    {
        return \in_array($prop, $this->readOnly, true) && isset($this->{$prop});
    }

    /**
     * Retrieve a configuration value
     *
     * @param array|string $path (optional) what to get
     *
     * @return mixed
     */
    public function getCfg($path = null)
    {
        $path = \is_array($path)
            ? $path
            : \array_filter(\preg_split('#[\./]#', (string) $path), 'strlen');
        $first = \reset($path);
        if ($first && \is_object($this->{$first}) && \method_exists($this->{$first}, 'getCfg')) {
            return $this->{$first}->getCfg(\array_slice($path, 1));
        }
        $return = $this->cfg;
        $path = \array_reverse($path);
        while ($path) {
            $key = \array_pop($path);
            if (isset($return[$key])) {
                $return = $return[$key];
                continue;
            }
            return null;
        }
        return $return;
    }

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * Calls self::postSetCfg() with new values and previous values
     *
     * @param array|string $mixed key=>value array or key
     * @param mixed        $val   new value
     *
     * @return mixed previous value(s)
     */
    public function setCfg($mixed, $val = null)
    {
        $prev = null;
        $prevArray = array();
        if (\is_string($mixed)) {
            $prev = isset($this->cfg[$mixed])
                ? $this->cfg[$mixed]
                : null;
            $prevArray = array($mixed => $prev);
            $mixed = array($mixed => $val);
        } elseif (\is_array($mixed)) {
            $prev = \array_intersect_key($this->cfg, $mixed);
        }
        $this->cfg = \call_user_func($this->setCfgMergeCallable, $this->cfg, $mixed);
        $this->postSetCfg($mixed, $prevArray ?: $prev);
        return $prev;
    }

    /**
     * Called by setCfg
     *
     * extend me to perform class specific config operations
     *
     * @param array $cfg  new config values
     * @param array $prev previous config values
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
    }
}

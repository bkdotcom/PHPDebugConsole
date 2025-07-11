<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug;

/**
 * Interface providing means to get and set configuration
 */
interface ConfigurableInterface
{
    /**
     * Get config value(s)
     *
     * @param string $key (optional) key
     *
     * @return mixed
     */
    public function getCfg($key = null);

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string $mixed key=>value array or key
     * @param mixed  $val   new value
     *
     * @return mixed returns previous value(s)
     */
    public function setCfg($mixed, $val = null);
}

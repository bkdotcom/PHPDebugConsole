<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug;

use bdk\Debug;

/**
 * Plugin Interface
 */
interface PluginInterface
{
    /**
     * Set Debug instance
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    public function setDebug(Debug $debug);
}

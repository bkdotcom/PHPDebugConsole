<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
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

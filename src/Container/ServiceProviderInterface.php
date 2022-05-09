<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Container;

use bdk\Container;

/**
 * Container
 */
interface ServiceProviderInterface
{
    /**
     * Registers services, factories, & values on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $container Container instance
     *
     * @return void
     */
    public function register(Container $container);
}

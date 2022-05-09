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

namespace bdk\Debug;

/**
 * Provide css and/or javascript assets
 */
interface AssetProviderInterface
{
    /**
     * Returns an array with the following keys:
     *  * css: filename, css, or array thereof
     *  * script: filename, javascript, or array thereof
     *
     * @return array
     */
    public function getAssets();
}

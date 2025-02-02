<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams;

use OutOfBoundsException;

/**
 * Item interface
 */
interface ItemInterface
{
    /**
     * Get attribute value
     *
     * @param string $name Field name
     *
     * @return mixed
     *
     * @throws OutOfBoundsException
     */
    public function get($name);

    /**
     * Get element content
     *
     * @param float|null $version Card version
     *
     * @return array
     */
    public function getContent($version);
}

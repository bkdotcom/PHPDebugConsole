<?php

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

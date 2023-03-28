<?php

namespace bdk\Teams;

use OutOfBoundsException;

/**
 *
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
     * @param float $version Card version
     *
     * @return array
     */
    public function getContent($version);
}

<?php

namespace bdk\Test\Debug\Fixture;

/**
 * Used to test bdk\Debug\Utility\Php::unserializeSafe()
 *
 * Serializable interface is deprecated as of PHP 8.1.0,
 */
class Serializable implements \Serializable
{
    private $data = 'Brad was here';

    /**
     * Implements Serializable
     *
     * @return string
     */
    public function serialize()
    {
        return $this->data;
    }

    /**
     * Implements Serializable
     *
     * @param string $data serialized data
     *
     * @return void
     */
    public function unserialize($data)
    {
        $this->data = $data;
    }
}

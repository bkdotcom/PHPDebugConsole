<?php

namespace bdk\Cache;

/**
 * For when you don't want a cache at all!
 */
class NullCache extends AbstractBase
{
    /**
     * {@inheritDoc}
     */
    public function get($key, $default = null)
    {
        $this->assertValidKey($key);
        return $default;
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value, $ttl = null)
    {
        [$value]; // silence unused parameter warning
        $this->assertValidKey($key);
        $this->expiry($ttl); // asserts valid ttl
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($key)
    {
        $this->assertValidKey($key);
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        return true;
    }

    // getMultiple, setMultiple, deleteMultiple implemented in AbstractBase

    /**
     * {@inheritDoc}
     */
    public function has($key)
    {
        return false;
    }
}

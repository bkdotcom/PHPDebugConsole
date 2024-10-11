<?php

namespace bdk\Test\Debug\Mock\SimpleCache;

/*
    Wrap in condition.
    PHPUnit code coverage scans all files and will conflict
*/
if (\trait_exists(__NAMESPACE__ . '\\CompatTrait', false) === false) {
    /**
     * Provide method signatures compatible with psr/simple-cache 2.x
     */
    trait CompatTrait
    {
        /**
         * {@inheritDoc}
         */
        public function get(string $key, mixed $default = null)
        {
            return $this->doGet($key, $default);
        }

        /**
         * {@inheritDoc}
         */
        public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null)
        {
            return $this->doSet($key, $value, $ttl);
        }

        /**
         * {@inheritDoc}
         */
        public function delete(string $key)
        {
            return $this->doDelete($key);
        }

        /**
         * {@inheritDoc}
         */
        public function clear()
        {
            return $this->doClear();
        }

        /**
         * {@inheritDoc}
         */
        public function getMultiple(string $keys, mixed $default = null)
        {
            return $this->doGetMultiple($keys, $default);
        }

        /**
         * {@inheritDoc}
         */
        public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null)
        {
            return $this->doSetMultiple($values, $ttl);
        }

        /**
         * {@inheritDoc}
         */
        public function deleteMultiple(iterable $keys)
        {
            return $this->doDeleteMultiple($keys);
        }

        /**
         * {@inheritDoc}
         */
        public function has(string $key)
        {
            return $this->doHas($key);
        }
    }
}

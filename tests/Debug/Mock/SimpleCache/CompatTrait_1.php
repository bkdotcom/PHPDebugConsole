<?php

namespace bdk\Test\Debug\Mock\SimpleCache;

/*
    Wrap in condition.
    PHPUnit code coverage scans all files and will conflict
*/
if (\trait_exists(__NAMESPACE__ . '\\CompatTrait', false) === false) {
    /**
     * Provide method signatures compatible with psr/simple-cache 1.x
     */
    trait CompatTrait
    {
        /**
         * {@inheritDoc}
         */
        public function get($key, $default = null)
        {
            return $this->doGet($key, $default);
        }

        /**
         * {@inheritDoc}
         */
        public function set($key, $value, $ttl = null)
        {
            return $this->doSet($key, $value, $ttl);
        }

        /**
         * {@inheritDoc}
         */
        public function delete($key)
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
        public function getMultiple($keys, $default = null)
        {
            return $this->doGetMultiple($keys, $default);
        }

        /**
         * {@inheritDoc}
         */
        public function setMultiple($values, $ttl = null)
        {
            return $this->doSetMultiple($values, $ttl);
        }

        /**
         * {@inheritDoc}
         */
        public function deleteMultiple($keys)
        {
            return $this->doDeleteMultiple($keys);
        }

        /**
         * {@inheritDoc}
         */
        public function has($key)
        {
            return $this->doHas($key);
        }
    }
}

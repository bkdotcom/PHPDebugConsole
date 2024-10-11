<?php

namespace bdk\Test\Debug\Mock\SimpleCache;

/*
    Wrap in condition.
    PHPUnit code coverage scans all files and will conflict
*/
if (\trait_exists(__NAMESPACE__ . '\\CompatTrait', false) === false) {
    /**
     * Provide method signatures compatible with psr/simple-cache 3.x
     */
    trait CompatTrait
    {
        /**
         * {@inheritDoc}
         */
        public function get(string $key, mixed $default = null): mixed
        {
            return $this->doGet($key, $default);
        }

        /**
         * {@inheritDoc}
         */
        public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
        {
            return $this->doSet($key, $value, $ttl);
        }

        /**
         * {@inheritDoc}
         */
        public function delete(string $key): bool
        {
            return $this->doDelete($key);
        }

        /**
         * {@inheritDoc}
         */
        public function clear(): bool
        {
            return $this->doClear();
        }

        /**
         * {@inheritDoc}
         */
        public function getMultiple(iterable $keys, mixed $default = null): iterable
        {
            return $this->doGetMultiple($keys, $default);
        }

        /**
         * {@inheritDoc}
         */
        public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
        {
            return $this->doSetMultiple($values, $ttl);
        }

        /**
         * {@inheritDoc}
         */
        public function deleteMultiple(iterable $keys): bool
        {
            return $this->doDeleteMultiple($keys);
        }

        /**
         * {@inheritDoc}
         */
        public function has(string $key): bool
        {
            return $this->doHas($key);
        }
    }
}

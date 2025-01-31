<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Collector\SimpleCache;

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
        public function get($key, $default = null) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        {
            return $this->profileCall('get', \func_get_args(), false, $key);
        }

        /**
         * {@inheritDoc}
         */
        public function set($key, $value, $ttl = null) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        {
            return $this->profileCall('set', \func_get_args(), true, $key);
        }

        /**
         * {@inheritDoc}
         */
        public function delete($key)
        {
            return $this->profileCall('delete', \func_get_args(), false, $key);
        }

        /**
         * {@inheritDoc}
         */
        public function clear()
        {
            return $this->profileCall('clear', [], true);
        }

        /**
         * {@inheritDoc}
         */
        public function getMultiple($keys, $default = null) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        {
            $keysDebug = $this->keysDebug($keys);
            return $this->profileCall('getMultiple', \func_get_args(), false, $keysDebug);
        }

        /**
         * {@inheritDoc}
         */
        public function setMultiple($values, $ttl = null) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        {
            $keysDebug = $this->keysDebug($values, true);
            return $this->profileCall('setMultiple', \func_get_args(), true, $keysDebug);
        }

        /**
         * {@inheritDoc}
         */
        public function deleteMultiple($keys)
        {
            $keysDebug = $this->keysDebug($keys);
            return $this->profileCall('deleteMultiple', \func_get_args(), true, $keysDebug);
        }

        /**
         * {@inheritDoc}
         */
        public function has($key)
        {
            return $this->profileCall('has', \func_get_args(), false, $key);
        }
    }
}

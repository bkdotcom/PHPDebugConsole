<?php

namespace bdk\Cache;

use bdk\Cache\InvalidArgumentException;
use DateInterval;
use DateTime;
use DateTimeZone;

/**
 * Abstract Base Class
 *
 * contains helper methods for cache implementations
 */
abstract class AbstractBase
{
    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    abstract public function get($key, $default = null);

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                $key   The key of the item to store.
     * @param mixed                 $value The value of the item to store. Must be serializable.
     * @param null|int|DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    abstract public function set($key, $value, $ttl = null);

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    abstract public function delete($key);

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    abstract public function clear();

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        $this->assertValidIterable($keys);

        $values = array();
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable              $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        $this->assertValidIterable($values);

        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        $this->assertValidIterable($keys);

        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it, making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    abstract public function has($key);

    /**
     * Validate that a value is iterable
     *
     * @param mixed $value Value to validate
     *
     * @return void
     *
     * @throws InvalidArgumentException if value is not iterable
     */
    protected function assertValidIterable($value)
    {
        if (\is_array($value) || $value instanceof \Traversable) {
            return;
        }

        throw new InvalidArgumentException('Value must be an array or implement Traversable');
    }

    /**
     * Validate a cache key according to PSR-16 rules
     *
     * Keys must not contain these characters: {}()/\@:
     *
     * @param string $key Cache key to validate
     *
     * @return void
     *
     * @throws InvalidArgumentException if key is invalid
     */
    protected function assertValidKey($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException('Cache key must be a string');
        }

        if ($key === '') {
            throw new InvalidArgumentException('Cache key cannot be empty');
        }

        // PSR-16 reserved characters: {}()/\\@:
        if (\preg_match('/[{}()\\/\\\\@:]/', $key)) {
            throw new InvalidArgumentException('Cache key contains reserved characters');
        }
    }

    /**
     * Convert expiry to unix timestamp
     *
     * @param mixed $expire null: no expiration (0 is returned)
     *                      integer: relative/absolute time in seconds
     *                          0 : no expiration (0 is returned)
     *                          <= 30days : relative time
     *                          > 30days : absolute time
     *                      string ("YYYY-MM-DD HH:MM:SS") provide in UTC time
     *                      DateTime object
     *                      DateInterval object
     *
     * @return int unix timestamp (0 if no expiration)
     *
     * @throws InvalidArgumentException if expiry value is invalid
     */
    protected function expiry($expire)
    {
        switch (true) {
            case \in_array($expire, [0, null], true):
                return 0;
            case \is_numeric($expire):
                if ($expire <= 30 * 24 * 60 * 60) {
                    // relative time in seconds, <=30 days
                    $expire += \time();
                }
                return (int) \round($expire);
            case $expire instanceof DateTime:
                return (int) $expire->format('U');
            case $expire instanceof DateInterval:
                $datetime = new DateTime();
                $datetime->add($expire);
                return (int) $datetime->format('U');
            case \is_string($expire) && \preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expire):
                // ASSUME UTC
                $expire = new DateTime($expire, new DateTimeZone('UTC'));
                return (int) $expire->format('U');
        }
        throw new InvalidArgumentException('Invalid expiry/ttl value');
    }

    /**
     * Check if cached data has expired
     *
     * @param array $data Cached data array with 'expiry' key
     *
     * @return bool True if expired, false otherwise
     */
    protected function isExpired(array $data)
    {
        if (!isset($data['expiry']) || $data['expiry'] === 0) {
            return false;
        }

        // phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
        return time() > $data['expiry'];
    }
}

<?php

/**
 * This file is part of bdk\PubSub
 *
 * @package   bdk\PubSub
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.0
 * @link      http://www.github.com/bkdotcom/PubSub
 */

namespace bdk\PubSub;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Serializable;

/**
 * Value store
 *
 * Note:
 *   - The Serializable interface - since PHP 5.1.  Deprecated in php 8.1
 *   - __serialize and __unserialize magic methods : since PHP 7.4
 *
 * @template TKey   of array-key
 * @template TValue of mixed
 *
 * @template-implements ArrayAccess<TKey, TValue>
 * @template-implements IteratorAggregate<TKey, TValue>
 */
class ValueStore implements ArrayAccess, IteratorAggregate, JsonSerializable, Serializable
{
    /**
     * @var array<TKey,TValue> Array of key/values
     */
    protected $values = array();

    /**
     * Constructor
     *
     * @param array<TKey,TValue> $values Values to store
     */
    public function __construct(array $values = array())
    {
        $this->setValues($values);
    }

    /**
     * Magic Method
     *
     * @return array
     */
    public function __debugInfo()
    {
        return $this->getValues();
    }

    /**
     * Serialize magic method
     * (since php 7.4)
     *
     * @return array<TKey,TValue>
     */
    public function __serialize()
    {
        return $this->getValues();
    }

    /**
     * Unserialize
     *
     * @param array<TKey,TValue> $data serialized data
     *
     * @return void
     */
    public function __unserialize(array $data)
    {
        $this->values = $data;
    }

    /**
     * Get value by key.
     *
     * @param TKey $key Value name
     *
     * @return TValue|null
     */
    public function getValue($key)
    {
        return $this->offsetGet($key);
    }

    /**
     * Get all stored values
     *
     * @return array<TKey,TValue>
     */
    public function getValues()
    {
        // for consistency between PHP versions, we use SORT_NATURAL
        // @see https://github.com/php/php-src/issues/9296
        \ksort($this->values, SORT_NATURAL);
        return $this->values;
    }

    /**
     * Does specified key have a value?
     *
     * @param TKey $key Value name
     *
     * @return bool
     */
    public function hasValue($key)
    {
        return \array_key_exists($key, $this->values);
    }

    /**
     * Set value
     *
     * @param TKey   $key   Value name
     * @param TValue $value Value
     *
     * @return $this
     */
    public function setValue($key, $value)
    {
        $this->offsetSet($key, $value);
        return $this;
    }

    /**
     * Clears existing values and sets new values
     *
     * @param array<TKey,TValue> $values key=>value array of values
     *
     * @return $this
     */
    public function setValues(array $values = array())
    {
        $this->values = $values;
        $this->onSet($values);
        return $this;
    }

    /**
     * Implements `JsonSerializable`
     *
     * @return array<TKey,TValue>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->getValues();
    }

    /**
     * Implements `Serializable`
     *
     * @return string
     */
    public function serialize()
    {
        return \serialize($this->__serialize());
    }

    /**
     * Implements `Serializable`
     *
     * @param string $data serialized data
     *
     * @return void
     */
    public function unserialize($data)
    {
        /** @var mixed */
        $unserialized = \unserialize($data);
        if (\is_array($unserialized)) {
            $this->__unserialize($unserialized);
        }
    }

    /**
     * `ArrayAccess` hasValue.
     *
     * @param TKey $key Array key
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($key)
    {
        if (isset($this->values[$key])) {
            return true;
        }
        $getter = $this->getter($key);
        return $getter
            ? $this->{$getter}() !== null
            : false;
    }

    /**
     * `ArrayAccess` getValue.
     *
     * @param TKey $key Array key
     *
     * @return TValue|null
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($key)
    {
        if ($this->hasValue($key)) {
            return $this->values[$key];
        }
        $return = null;
        $getter = $this->getter($key);
        if ($getter) {
            /** @var TValue */
            $return = $this->{$getter}();
        }
        return $return;
    }

    /**
     * `ArrayAccess` setValue
     *
     * @param TKey   $offset Array key to set
     * @param TValue $value  Value
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            // appending...  determine key
            $this->values[] = $value;
            \end($this->values);
            $offset = \key($this->values);
        }
        /** @psalm-var TKey $offset */
        $this->values[$offset] = $value;
        $this->onSet(array(
            $offset => $value,
        ));
    }

    /**
     * `ArrayAccess` interface
     *
     * @param TKey $key Array key
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($key)
    {
        unset($this->values[$key]);
    }

    /**
     * `IteratorAggregate` interface
     *
     * Iterate over the object like an array.
     *
     * @return ArrayIterator<TKey,TValue>
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator($this->getValues());
    }

    /**
     * Return the getter method for the given key
     *
     * @param TKey $key Array key
     *
     * @return string|false
     */
    private function getter($key)
    {
        $key = (string) $key;
        $getter = \preg_match('/^is[A-Z]/', $key)
            ? $key
            : 'get' . \ucfirst($key);
        return \method_exists($this, $getter)
            ? $getter
            : false;
    }

    /**
     * Extend me to perform action after setting value/values
     *
     * @param array<TKey,TValue> $values key => values  being set
     *
     * @return void
     *
     * @psalm-suppress PossiblyUnusedParam
     */
    protected function onSet($values = array())
    {
    }
}

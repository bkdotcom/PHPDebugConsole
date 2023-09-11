<?php

/**
 * This file is part of bdk\PubSub
 *
 * @package   bdk\PubSub
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.0
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
 *   The Serializable interface - since PHP 5.1.  Deprecated in php 8.1
 *   __serialize and __unserialize magic methods : since PHP 7.4
 */
class ValueStore implements ArrayAccess, IteratorAggregate, JsonSerializable, Serializable
{
    /**
     * @var array Array of key/values
     */
    protected $values = array();

    /**
     * Constructor
     *
     * @param array $values Values to store
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
        return $this->values;
    }

    /**
     * Serialize magic method
     * (since php 7.4)
     *
     * @return array
     */
    public function __serialize()
    {
        return $this->values;
    }

    /**
     * Unserialize
     *
     * @param array $data serialized data
     *
     * @return void
     */
    public function __unserialize($data)
    {
        $this->values = $data;
    }

    /**
     * Get value by key.
     *
     * @param string $key Value name
     *
     * @return mixed
     */
    public function getValue($key)
    {
        return $this->offsetGet($key);
    }

    /**
     * Get all stored values
     *
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Does specified key have a value?
     *
     * @param string $key Value name
     *
     * @return bool
     */
    public function hasValue($key)
    {
        return \array_key_exists($key, $this->values);
    }

    /**
     * Set event value
     *
     * @param string $key   Value name
     * @param mixed  $value Value
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
     * @param array $values key=>value array of values
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
     * Implements JsonSerializable
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->values;
    }

    /**
     * Implements Serializable
     *
     * @return string
     */
    public function serialize()
    {
        return \serialize($this->__serialize());
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
        $this->__unserialize(\unserialize($data));
    }

    /**
     * ArrayAccess hasValue.
     *
     * @param string $key Array key
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($key)
    {
        return isset($this->values[$key]);
    }

    /**
     * ArrayAccess getValue.
     *
     * @param string $key Array key
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($key)
    {
        if ($this->hasValue($key)) {
            return $this->values[$key];
        }
        $return = null;
        $getter = 'get' . \ucfirst($key);
        if (\method_exists($this, $getter)) {
            $return = $this->{$getter}();
        }
        return $return;
    }

    /**
     * ArrayAccess setValue
     *
     * @param string $key   Array key to set
     * @param mixed  $value Value
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($key, $value)
    {
        if ($key === null) {
            // appending...  determine key
            $this->values[] = $value;
            \end($this->values);
            $key = \key($this->values);
        }
        $this->values[$key] = $value;
        $this->onSet(array(
            $key => $value,
        ));
    }

    /**
     * ArrayAccess interface
     *
     * @param string $key Array key
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($key)
    {
        unset($this->values[$key]);
    }

    /**
     * IteratorAggregate interface
     *
     * Iterate over the object like an array.
     *
     * @return ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator($this->getValues());
    }

    /**
     * Extend me to perform action after setting value/values
     *
     * @param array $values key => values  being set
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function onSet($values = array())
    {
    }
}

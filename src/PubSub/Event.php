<?php

/**
 * This file is part of bdk\PubSub
 *
 * @package   bdk\PubSub
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v2.4
 * @link      http://www.github.com/bkdotcom/PubSub
 */

namespace bdk\PubSub;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;

/**
 * Event
 *
 * Events are passed to event subscribres/listeners
 */
class Event implements ArrayAccess, IteratorAggregate
{
    /**
     * @var bool Whether event subscribers should be called
     */
    private $propagationStopped = false;

    /**
     * @var mixed Event subject: usually object or callable
     */
    protected $subject = null;

    /**
     * @var array Array of key/values
     */
    protected $values = array();

    /**
     * Construct an event with optional subject and values
     *
     * @param mixed $subject The subject of the event (usually an object)
     * @param array $values  Values to store in the event
     */
    public function __construct($subject = null, array $values = array())
    {
        $this->subject = $subject;
        $this->setValues($values);
    }

    /**
     * Magic Method
     *
     * @return array
     */
    public function __debugInfo()
    {
        return array(
            'propagationStopped' => $this->propagationStopped,
            'subject' => \is_object($this->subject)
                ? \get_class($this->subject)
                : $this->subject,
            'values' => $this->values,
        );
    }

    /**
     * Get Event's "subject"
     *
     * @return mixed The observer subject
     */
    public function getSubject()
    {
        return $this->subject;
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
     * Has propagation been stopped?
     *
     * If stopped, no further event subscribers will be called
     *
     * @see Event::stopPropagation()
     *
     * @return bool Whether propagation is stopped for this event
     */
    public function isPropagationStopped()
    {
        return $this->propagationStopped;
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
        $this->values[$key] = $value;
        $this->onSet(array(
            $key => $value,
        ));
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
     * Stops the propagation of the event
     *
     * No further event subscribers will be called
     *
     * @return void
     */
    public function stopPropagation()
    {
        $this->propagationStopped = true;
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
        $null = null;
        return $null;
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
        $this->setValue($key, $value);
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
        return new ArrayIterator($this->values);
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

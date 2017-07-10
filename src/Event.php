<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk\Debug;

/**
 * Event
 *
 * Events are passed to event listener/subscribres
 */
class Event implements \ArrayAccess, \IteratorAggregate
{
    /**
     * @var boolean Whether no further event listeners should be triggered
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
     * @param mixed $subject The subject of the event, usually an object
     * @param array $values  Values to store in the event
     */
    public function __construct($subject = null, array $values = array())
    {
        $this->subject = $subject;
        $this->values = $values;
    }

    /**
     * Getter for subject property.
     *
     * @return mixed $subject The observer subject
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
        if ($this->hasValue($key)) {
            return $this->values[$key];
        }
        return null;
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
     * Has value.
     *
     * @param string $key Value name
     *
     * @return boolean
     */
    public function hasValue($key)
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Returns whether further event listeners should be triggered.
     *
     * @see Event::stopPropagation()
     *
     * @return boolean Whether propagation is stopped for this event
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
        return $this;
    }

    /**
     * Set event values (overwritting existing)
     *
     * @param array $values values
     *
     * @return $this
     */
    public function setValues(array $values = array())
    {
        $this->values = $values;
        return $this;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
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
     * @return boolean
     */
    public function offsetExists($key)
    {
        return $this->hasValue($key);
    }

    /**
     * ArrayAccess getValue.
     *
     * @param string $key Array key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->getValue($key);
    }

    /**
     * ArrayAccess setValue
     *
     * @param string $key   Array key to set
     * @param mixed  $value Value
     *
     * @return void
     */
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
    public function offsetUnset($key)
    {
        unset($this->values[$key]);
    }

    /**
     * IteratorAggregate for iterating over the object like an array.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->values);
    }
}

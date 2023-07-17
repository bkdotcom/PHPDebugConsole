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

/**
 * Event
 *
 * Events are passed to event subscribres/listeners
 */
class Event extends ValueStore
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
}

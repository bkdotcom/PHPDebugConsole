<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\PubSub\Event;
use JsonSerializable;
use Serializable;

/**
 * Abstraction
 *
 * We abstract
 *   objects
 *   array callable
 *   resource
 *   other/custom
 */
class Abstraction extends Event implements JsonSerializable, Serializable
{

    /**
     * Constructor
     *
     * @param array $values abtraction values
     */
    public function __construct($values = array())
    {
        $this->values = $values;
    }

    /**
     * Unattach subject (and allow subject to deconstruct)
     *
     * @return self
     */
    public function removeSubject()
    {
        $this->subject = null;
        return $this;
    }

    /**
     * Set abstraction's subject
     *
     * @param mixed $subject Subject
     *
     * @return self
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Implements JsonSerializable
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->values + array('debug' => Abstracter::ABSTRACTION);
    }

    /**
     * Implements Serializable
     *
     * @return string
     */
    public function serialize()
    {
        return \serialize($this->values);
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
        $this->values = \unserialize($data);
    }
}

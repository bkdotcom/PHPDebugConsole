<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

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
     * @param string $type   value type (one of the Abstracter TYPE_XXX constants)
     * @param array  $values abtraction values
     */
    public function __construct($type, $values = array())
    {
        $this->values = $values;
        $this->values['type'] = $type;
    }

    /**
     * Set abstraction's subject
     *
     * @param mixed $subject (null) Subject omit or set to null to remove subject
     *
     * @return self
     */
    public function setSubject($subject = null)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Return object's string representation
     *
     * @return string
     */
    public function toString()
    {
        $val = '';
        if ($this->values['type'] === Abstracter::TYPE_OBJECT) {
            if ($this->values['stringified']) {
                $val = $this->values['stringified'];
            } elseif (isset($this->values['methods']['__toString']['returnValue'])) {
                $val = $this->values['methods']['__toString']['returnValue'];
            }
        }
        return $val;
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

<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Utility\Utf8;
use bdk\PubSub\Event;

/**
 * Abstraction
 *
 * We abstract
 *   objects
 *   array callable
 *   resource
 *   other/custom
 */
class Abstraction extends Event
{
    /**
     * Constructor
     *
     * @param string $type   value type (one of the Abstracter TYPE_XXX constants)
     * @param array  $values Abstraction values
     */
    public function __construct($type, $values = array())
    {
        $values['type'] = $type;
        if ($type !== Type::TYPE_OBJECT && \array_key_exists('value', $values) === false) {
            // make sure non-object gets value
            $values['value'] = $type === Type::TYPE_ARRAY
                ? array()
                : null;
        }
        \ksort($values);
        $this->setValues($values);
    }

    /**
     * Return stringified value
     *
     * @return string
     */
    public function __toString()
    {
        $typeMore = $this->getValue('typeMore');
        if ($typeMore === Type::TYPE_STRING_BINARY && isset($this->values['chunks'])) {
            return \implode('', \array_map(static function (array $chunk) {
                if ($chunk[0] === Utf8::TYPE_UTF8) {
                    return $chunk[1];
                }
                $hex = \str_replace(' ', '', $chunk[1]);
                return \hex2bin($hex);
            }, $this->values['chunks']));
        }
        if ($typeMore === Type::TYPE_STRING_BINARY) {
            $hex = \str_replace(' ', '', $this->values['value']);
            return \hex2bin($hex);
        }
        if (isset($this->values['value'])) {
            return (string) $this->values['value'];
        }
        return '';
    }

    /**
     * Implements JsonSerializable
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->values + array('debug' => Abstracter::ABSTRACTION);
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
     * Make sure attribs['class'] is an array
     *
     * @param array $values key => values being set
     *
     * @return void
     */
    protected function onSet($values = array())
    {
        if (isset($values['attribs']) === false) {
            return;
        }
        if (!isset($values['attribs']['class'])) {
            $this->values['attribs']['class'] = array();
            \ksort($this->values['attribs']);
        } elseif (\is_string($values['attribs']['class'])) {
            $this->values['attribs']['class'] = \explode(' ', $values['attribs']['class']);
        }
    }
}

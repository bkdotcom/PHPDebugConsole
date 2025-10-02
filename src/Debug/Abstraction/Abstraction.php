<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
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
        if ($typeMore === Type::TYPE_STRING_BINARY) {
            return $this->toStringBinary();
        }
        if ($typeMore === Type::TYPE_STRING_FILEPATH) {
            return $this->toStringFilepath();
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
        $values = $this->values + array('debug' => Abstracter::ABSTRACTION);
        \ksort($values);
        return $values;
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
        if ($this->values['type'] === Type::TYPE_CONST && $this->getValue('name') !== null) {
            \trigger_error('Deprecated: TYPE_CONST - use TYPE_IDENTIFIER instead', \E_USER_DEPRECATED);
            $this->values['type'] = Type::TYPE_IDENTIFIER;
            $this->values['typeMore'] = Type::TYPE_IDENTIFIER_CONST;
            $this->values['backedValue'] = $this->values['value'];
            $this->values['value'] = $this->values['name'];
            unset($this->values['name']);
        }
        if ($this->getValue('typeMore') === Type::TYPE_STRING_CLASSNAME) {
            \trigger_error('Deprecated: TYPE_STRING_CLASSNAME - use TYPE_IDENTIFIER instead', \E_USER_DEPRECATED);
            $this->values['type'] = Type::TYPE_IDENTIFIER;
            $this->values['typeMore'] = Type::TYPE_IDENTIFIER_CLASSNAME;
        }
        if (isset($values['attribs']) === false) {
            return;
        }
        if (!isset($values['attribs']['class'])) {
            $this->values['attribs']['class'] = [];
            \ksort($this->values['attribs']);
        } elseif (\is_string($values['attribs']['class'])) {
            $this->values['attribs']['class'] = \explode(' ', $values['attribs']['class']);
        }
    }

    /**
     * Return stringified value for binary abstraction
     *
     * @return string
     */
    private function toStringBinary()
    {
        if (isset($this->values['chunks']) === false) {
            $hex = \str_replace(' ', '', $this->values['value']);
            return \hex2bin($hex);
        }
        return \implode('', \array_map(static function (array $chunk) {
            if ($chunk[0] === Utf8::TYPE_UTF8) {
                return $chunk[1];
            }
            if ($chunk[0] === Utf8::TYPE_UTF8_CONTROL) {
                // control character(s)
                $hex = \bin2hex($chunk[1]);
                $chunk[1] = \trim(\chunk_split($hex, 2, ' '));
            }
            $hex = \str_replace(' ', '', $chunk[1]);
            return \hex2bin($hex);
        }, $this->values['chunks']));
    }

    /**
     * Return stringified value for filepath abstraction
     *
     * @return string
     */
    private function toStringFilepath()
    {
        $vals = $this->getValues();
        $filepath = ($vals['docRoot'] ? 'DOCUMENT_ROOT' : '')
            . ($vals['pathCommon'] ? $vals['pathCommon'] : '')
            . ($vals['pathRel'] ? $vals['pathRel'] : '')
            . $vals['baseName'];
        $line = '';
        if (!isset($vals['line'])) {
            return $filepath;
        }
        $debug = \bdk\Debug::getInstance();
        $line = isset($vals['evalLine'])
            ? \sprintf(' (%s %s, %s %s)', $debug->i18n->trans('line'), $vals['line'], $debug->i18n->trans('line.evaled'), $vals['evalLine'])
            : \sprintf(' (%s %s)', $debug->i18n->trans('line'), $vals['line']);
        return $filepath . $line;
    }
}

<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Dump\Base;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\AbstractValue;
use bdk\Debug\Dump\Base\BaseObject;
use bdk\Debug\Utility\Utf8;

/**
 * Dump values
 */
class Value extends AbstractValue
{
    /** @var BaseObject */
    protected $lazyObject;

    /**
     * Extend me to format classname/constant, etc
     *
     * @param string|array $val classname or classname(::|->)name (method/property/const)
     *
     * @return string
     */
    public function markupIdentifier($val)
    {
        if (\is_array($val)) {
            $val = $val[0] . '::' . $val[1];
        }
        return $this->highlightChars($val);
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpArray(array $array, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        if ($this->optionGet('isMaxDepth')) {
            return 'array *MAX DEPTH*';
        }
        $arrayNew = array();
        $absKeys = isset($abs['keys'])
            ? $abs['keys']
            : array();
        foreach ($array as $key => $val) {
            if (isset($absKeys[$key])) {
                $key = $absKeys[$key];
            }
            $key = $this->dump($key, array('addQuotes' => false));
            $arrayNew[$key] = $this->dump($val);
        }
        return $arrayNew;
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpBool($val)
    {
        return $val;
    }

    /**
     * Dump callable
     *
     * @param Abstraction $abs array/callable abstraction
     *
     * @return string
     */
    protected function dumpCallable(Abstraction $abs)
    {
        return 'callable: '
            . $this->markupIdentifier($abs['value']);
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpFloat($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        $date = $this->checkTimestamp($val, $abs);
        return $date
            ? $val . ' (' . $date . ')'
            : $val;
    }

    /**
     * Dump identifier
     *
     * @param Abstraction $abs constant abstraction
     *
     * @return string
     */
    protected function dumpIdentifier(Abstraction $abs)
    {
        return $abs['value'];
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpInt($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        $val = $this->dumpFloat($val, $abs);
        return \is_string($val)
            ? $val
            : (int) $val;
    }

    /**
     * Dump non-inspected value (likely object)
     *
     * @return string
     */
    protected function dumpNotInspected()
    {
        return 'NOT INSPECTED';
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpNull()
    {
        return null;
    }

    /**
     * Dump object
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string|array
     */
    protected function dumpObject(ObjectAbstraction $abs)
    {
        return $this->object->dump($abs);
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        return 'array *RECURSION*';
    }

    /**
     * Dump resource
     *
     * @param Abstraction $abs resource abstraction
     *
     * @return string
     */
    protected function dumpResource(Abstraction $abs)
    {
        return $abs['value'];
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpString($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        if (\is_numeric($val)) {
            $date = $this->checkTimestamp($val, $abs);
            return $date
                ? $val . ' (' . $date . ')'
                : $val;
        }
        if ($abs) {
            return $this->dumpStringAbs($abs);
        }
        $val = $this->escapeEscapeSequences($val);
        return $this->highlightChars($val);
    }

    /**
     * Dump string abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return string
     */
    protected function dumpStringAbs(Abstraction $abs)
    {
        if ($abs['strlen'] === null) {
            $abs['strlen'] = \strlen($abs['value']);
        }
        if ($abs['strlenValue'] === null) {
            $abs['strlenValue'] = $abs['strlen'];
        }
        if ($abs['prettified']) {
            $this->optionSet('addQuotes', false);
        }
        $val = $abs['typeMore'] === Type::TYPE_STRING_BINARY
            ? $this->dumpStringBinary($abs)
            : (string) $abs;
        $strLenDiff = $abs['strlen'] - $abs['strlenValue'];
        if ($abs['strlenValue'] && $strLenDiff) {
            $val .= '[' . $strLenDiff . ' more bytes (not logged)]';
        }
        return $val;
    }

    /**
     * Get binary value
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return string
     */
    protected function dumpStringBinary(Abstraction $abs)
    {
        if ($abs['brief'] && $abs['contentType']) {
            return 'string(' . $abs['contentType'] . '): '
                . $this->debug->utility->getBytes($abs['strlen']);
        }
        if ($abs['value']) {
            return $abs['value'];
        }
        return \implode('', \array_map(function ($chunk) {
            return $chunk[0] === Utf8::TYPE_OTHER
                ? '\\x' . \str_replace(' ', ' \\x', $chunk[1])
                : $this->dumpString($chunk[1]);
        }, $abs['chunks'] ?: array()));
    }

    /**
     * Dump undefined
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return Abstracter::UNDEFINED;
    }

    /**
     * Dump Type::TYPE_UNKNOWN
     *
     * @param Abstraction $abs resource abstraction
     *
     * @return array
     */
    protected function dumpUnknown(Abstraction $abs)
    {
        $values = \array_diff_key($abs->getValues(), \array_flip(['brief', 'typeMore']));
        \ksort($values);
        return $values;
    }

    /**
     * Getter for this->object
     *
     * @return BaseObject
     */
    protected function getObject()
    {
        if (!$this->lazyObject) {
            $this->lazyObject = new BaseObject($this);
        }
        return $this->lazyObject;
    }

    /**
     * Get replacement for character
     *
     * @param string $char        single multi-byte character
     * @param bool   $charReplace whether to replace char with code-point
     *                            control chars are always replaced
     *
     * @return string \x##, \u{####}, or original char
     */
    protected function charReplacement($char, $charReplace)
    {
        if (\ord($char) < 0x80) {
            // always replace control chars
            return '\\x' . \str_pad(\dechex(\ord($char)), 2, '0', STR_PAD_LEFT);
        }
        if (isset($this->charData[$char]['class']) && \strpos($this->charData[$char]['class'], 'char-ws') !== false) {
            // always replace "whitespace" chars
            $charReplace = true;
        }
        if ($charReplace) {
            return '\\u{' . \str_pad(\dechex(Utf8::ord($char)), 4, '0', STR_PAD_LEFT) . '}';
        }
        return $char;
    }

    /**
     * Highlight confusable and other characters
     *
     * @param string $str HTML String to update
     *
     * @return string
     */
    protected function highlightChars($str)
    {
        $chars = $this->findChars($str);
        $charReplace = $this->optionGet('charReplace');
        foreach ($chars as $char) {
            $replacement = $this->charReplacement($char, $charReplace);
            $str = \str_replace($char, $replacement, $str);
        }
        return $str;
    }
}

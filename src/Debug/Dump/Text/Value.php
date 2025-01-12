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

namespace bdk\Debug\Dump\Text;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Base\Value as BaseValue;
use bdk\Debug\Dump\Text\TextObject;

/**
 * Dump val as plain text
 */
class Value extends BaseValue
{
    /** @var int used for indentation */
    protected $valDepth = 0;

    /** @var TextObject */
    protected $lazyObject;

    /**
     * Get valDepth value
     *
     * @return int
     */
    public function getValDepth()
    {
        return $this->valDepth;
    }

    /**
     * Increment valDepth
     *
     * @return void
     */
    public function incValDepth()
    {
        $this->valDepth++;
    }

    /**
     * Used to reset valDepth
     *
     * @param string $depth value depth
     *
     * @return void
     */
    public function setValDepth($depth = 0)
    {
        $this->valDepth = $depth;
    }

    /**
     * Wrap string in quotes
     *
     * @param string $val string value
     *
     * @return string
     */
    protected function addQuotes($val)
    {
        return $this->optionGet('addQuotes')
            ? '"' . $val . '"'
            : $val;
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
     */
    protected function dumpArray(array $array, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        $isNested = $this->valDepth > 0;
        $this->valDepth++;
        $array = parent::dumpArray($array, $abs);
        $str = \trim(\print_r($array, true));
        $str = \preg_replace('#^Array\n\(#', 'array(', $str);
        $str = \preg_replace('#^array\s*\(\s+\)#', 'array()', $str); // display empty array on single line
        if ($isNested) {
            $str = \str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump boolean
     *
     * @param bool $val boolean value
     *
     * @return string
     */
    protected function dumpBool($val)
    {
        return $val
            ? 'true'
            : 'false';
    }

    /**
     * Dump float value
     *
     * @param float            $val float value
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return float|string
     */
    protected function dumpFloat($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        if ($val === Type::TYPE_FLOAT_INF) {
            return 'INF';
        }
        if ($val === Type::TYPE_FLOAT_NAN) {
            return 'NaN';
        }
        $date = $this->checkTimestamp($val, $abs);
        return $date
            ? '📅 ' . $val . ' (' . $date . ')'
            : $val;
    }

    /**
     * Dump null value
     *
     * @return string
     */
    protected function dumpNull()
    {
        return 'null';
    }

    /**
     * Dump object
     *
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObject(ObjectAbstraction $abs)
    {
        return $this->object->dump($abs);
    }

    /**
     * Dump string
     *
     * @param string           $val string value
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return string
     */
    protected function dumpString($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        $date = \is_numeric($val)
            ? $this->checkTimestamp($val, $abs)
            : null;
        if ($abs) {
            $val = $this->dumpStringAbs($abs);
        }
        $val = $this->escapeEscapeSequences($val);
        $val = $this->highlightChars($val);
        $val = $this->addQuotes($val);
        return $date
            ? '📅 ' . $val . ' (' . $date . ')'
            : $val;
    }

    /**
     * Dump undefined
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return 'undefined';
    }

    /**
     * Dump Type::TYPE_UNKNOWN
     *
     * @param Abstraction $abs resource abstraction
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
     */
    protected function dumpUnknown(Abstraction $abs)
    {
        $values = parent::dumpUnknown($abs);
        return 'unknown: ' . \print_r($values['value'], true);
    }

    /**
     * Getter for this->object
     *
     * @return TextObject
     */
    protected function getObject()
    {
        if (!$this->lazyObject) {
            $this->lazyObject = new TextObject($this);
        }
        return $this->lazyObject;
    }
}

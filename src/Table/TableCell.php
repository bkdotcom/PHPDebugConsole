<?php

namespace bdk\Table;

use bdk\Debug\Utility\ArrayUtil;
use bdk\Debug\Utility\Html;
use bdk\Debug\Utility\PhpType;
use bdk\Table\Element;
use bdk\Table\Factory;
use InvalidArgumentException;

/**
 * Represents a table cell containing a value of arbitrary type
 */
class TableCell extends Element
{
    /** @var array<string,mixed> */
    protected $defaults = array(
        'tagName' => 'td',
    );

    /** @var string */
    protected $tagName = 'td';

    /** @var mixed raw value to be formatted upon rendering */
    protected $value;

    /** @var callable */
    protected static $valDumper = ['bdk\Table\TableCell', 'valDumper'];

    /**
     * Constructor
     *
     * @param mixed $value Cell value
     *
     * @throws InvalidArgumentException
     */
    public function __construct($value = null)
    {
        $propertyNames = \array_keys(\get_object_vars($this));
        if (\is_array($value) && \array_intersect(\array_keys($value), $propertyNames)) {
            $this->setProperties($value);
            return;
        }
        $this->setValue($value);
    }

    /**
     * {@inheritDoc}
     */
    public function __serialize()
    {
        return parent::__serialize() + array(
            'value' => $this->value,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getHtml()
    {
        $html = parent::getHtml();
        if ($html !== null) {
            return $html;
        }
        $this->buildingHtml = true;
        $value = \call_user_func(self::$valDumper, $this);
        $this->buildingHtml = false;
        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function getOuterHtml()
    {
        // find the table this cell belongs to
        // and access column meta data
        // merge in any attribs
        $table = null;
        $parent = $this->getParent();
        while ($parent !== null) {
            if ($parent instanceof Table) {
                $table = $parent;
                break;
            }
            $parent = $parent->getParent();
        }
        $innerHtml = $this->getHtml();
        $attribs = $this->getAttribs();
        if ($table !== null) {
            $index = $this->getIndex();
            $columnsMeta = \array_replace(array(
                $index => array(),
            ), $table->getMeta('columns', array()));
            $columnMeta = \array_merge(array(
                'attribs' => array(),
            ), $columnsMeta[$index]);
            $attribs = ArrayUtil::mergeDeep($columnMeta['attribs'], $attribs);
        }
        return Html::buildTag($this->getTagName(), $attribs, $innerHtml);
    }

    /**
     * Get cell value
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set value dumper
     *
     * @param callable $valDumper Callable that accepts TableCell and returns string
     *
     * @return void
     */
    public static function setValDumper(callable $valDumper)
    {
        self::$valDumper = $valDumper;
    }

    /**
     * Set cell value
     *
     * @param mixed $value Cell value
     *
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Default value dumper
     *
     * @param self $tableCell tableCell to dump
     *
     * @return string
     */
    public static function valDumper(self $tableCell)
    {
        $value = $tableCell->getValue();
        $type = PhpType::getDebugType($value);

        if (\is_object($value)) {
            $type = 'object';
            $value = self::objectToString($value);
        } elseif (\in_array($value, [true, false, null], true)) {
            $value = \json_encode($value);
            $type = $value;
        } elseif (\is_string($value) === false && \is_numeric($value) === false) {
            // not string or numeric
            $value = \print_r($value, true);
        } elseif ($value === Factory::VAL_UNDEFINED) {
            $type = 'undefined';
            $value = '';
        }

        $class = 't_' . $type;
        $tableCell->addClass($class);

        return \htmlspecialchars($value);
    }

    /**
     * Get string value / representation of object
     *
     * @param object $obj object to convert to string
     *
     * @return string
     */
    private static function objectToString(object $obj)
    {
        if (\method_exists($obj, '__toString')) {
            return (string) $obj;
        }
        if ($obj instanceof \DateTime || $obj instanceof \DateTimeImmutable) {
            return $obj->format(\DateTime::RFC3339);
        }
        return \get_class($obj);
    }
}

<?php

namespace bdk\Table;

use bdk\Debug\Utility\ArrayUtil;
use bdk\Table\Element;
use bdk\Table\TableCell;

/**
 * Represents a table row
 */
class TableRow extends Element
{
    /** @var array<string,mixed> */
    protected $defaults = array(
        'tagName' => 'tr',
    );

    /** @var string */
    protected $tagName = 'tr';

    /**
     * Constructor
     *
     * @param array $children Table cells
     */
    public function __construct(array $children = array())
    {
        if (ArrayUtil::isList($children) || \reset($children) instanceof Element) {
            $this->setChildren($children);
            return;
        }
        foreach ($children as $key => $val) {
            $method = 'set' . \ucfirst($key);
            $this->$method($val);
        }
    }

    /**
     * Get Cells
     *
     * @return array<TableCell>
     */
    public function getCells()
    {
        return $this->children;
    }

    /**
     * Set cells
     *
     * @param array<TableCell>|array<array> $cells Row cells
     *
     * @return $this
     */
    public function setCells(array $cells)
    {
        return $this->setChildren($cells);
    }

    /**
     * {@inheritDoc}
     */
    public function setChildren(array $children)
    {
        $this->children = \array_map(function ($child) {
            if (($child instanceof Element) === false) {
                $child = new TableCell($child);
            }
            $child->setParent($this);
            return $child;
        }, \array_values($children));
        return $this;
    }
}

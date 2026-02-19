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
        $this->setProperties($children);
    }

    /**
     * Append cell to row
     *
     * @param mixed|TableCell $cell Table cell
     *
     * @return $this
     */
    public function appendCell($cell)
    {
        $cell = $cell instanceof TableCell
            ? $cell
            : new TableCell($cell);
        return $this->appendChild($cell);
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
        $children = \array_map(static function ($child) {
            return $child instanceof Element
                ? $child
                : new TableCell($child);
        }, $children);
        return parent::setChildren($children);
    }
}

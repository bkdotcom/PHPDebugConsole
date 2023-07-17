<?php

namespace bdk\Teams\Elements;

use bdk\Teams\AbstractItem;
use bdk\Teams\Elements\ElementInterface;
use bdk\Teams\Elements\TableCell;
use bdk\Teams\Enums;
use InvalidArgumentException;
use Traversable;

/**
 * Represents a row of cells within a Table element.
 *
 * @see https://adaptivecards.io/schemas/adaptive-card.json
 */
class TableRow extends AbstractItem
{
    protected $fields = array(
        'cells' => array(),
        'horizontalCellContentAlignment' => null,
        'style' => null,
        'verticalCellContentAlignment' => null,
    );

    /**
     * Constructor
     *
     * @param iterable|array<int, TableCell|ElementInterface|string|numeric> $cells The cells in this row
     */
    public function __construct($cells = array())
    {
        $this->type = 'TableRow';
        $this->fields['cells'] = self::asCells($cells);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        $attrVersions = array(
            'cells' => 1.5,
            'horizontalCellContentAlignment' => 1.5,
            'style' => 1.5,
            'verticalCellContentAlignment' => 1.5,
        );

        $content = array(
            'type' => $this->type,
        );
        foreach ($attrVersions as $name => $ver) {
            if ($version >= $ver) {
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
    }

    /**
     * Return new instance with added TableCell
     *
     * @param TableCell|ElementInterface|string|numeric $tableCell Table cell to append to this row
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withAddedCell($tableCell)
    {
        return $this->withAdded('cells', self::asCell($tableCell));
    }

    /**
     * Return new instance with the specified cells
     *
     * @param iterable|array<int, ElementInterface|string|numeric> $cells The cells in this row
     *
     * @return static
     */
    public function withCells($cells = array())
    {
        return $this->with('cells', self::asCells($cells));
    }

    /**
     * Return new instance with specified horizontal alignment
     *
     * Controls how the content of all cells in the row is horizontally aligned by default. When specified, this value overrides both the setting at the table and columns level. When not specified, horizontal alignment is defined at the table, column or cell level
     *
     * @param Enums::HORIZONTAL_ALIGNMENT_x $alignment Horizontal alignment
     *
     * @return static
     */
    public function withHorizontalCellContentAlignment($alignment)
    {
        self::assertEnumValue($alignment, 'HORIZONTAL_ALIGNMENT_', 'alignment');
        return $this->with('horizontalCellContentAlignment', $alignment);
    }

    /**
     * Return new instance with specified row style
     *
     * Defines the style of the entire row
     *
     * @param Enums::CONTAINER_STYLE_x $style Container style
     *
     * @return static
     */
    public function withStyle($style)
    {
        self::assertEnumValue($style, 'CONTAINER_STYLE_', 'style');
        return $this->with('style', $style);
    }

    /**
     * Return new instance with specified vertical alignment
     *
     * Controls how the content of all cells in the column is vertically aligned by default. When specified, this value overrides the setting at the table and column level. When not specified, vertical alignment is defined either at the table, column or cell level.
     *
     * @param Enums::HORIZONTAL_ALIGNMENT_x $alignment Horizontal alignment
     *
     * @return static
     */
    public function withVerticalCellContentAlignment($alignment)
    {
        self::assertEnumValue($alignment, 'VERTICAL_ALIGNMENT_', 'alignment');
        return $this->with('verticalCellContentAlignment', $alignment);
    }

    /**
     * Return value as TableCell
     *
     * @param TableCell|ElementInterface|string|numeric $cell Value to normalize
     *
     * @return TableCell
     *
     * @throws InvalidArgumentException
     */
    private function asCell($cell)
    {
        return $cell instanceof TableCell
            ? $cell
            : new TableCell($cell);
    }

    /**
     * Return array with each value converted to instance of TableCell
     *
     * @param iterable|array<int, TableCell|ElementInterface|string|numeric> $cells Cells to convert
     *
     * @return TableCell[]
     *
     * @throws InvalidArgumentException
     */
    private function asCells($cells)
    {
        $isIterable = \is_array($cells) || ($cells instanceof Traversable);
        if ($isIterable === false) {
            throw new InvalidArgumentException('Table cells must be an iterator of cells.');
        }
        try {
            foreach ($cells as $i => $cell) {
                $cells[$i] = self::asCell($cell);
            }
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(\sprintf(
                'Invalid table cell found at index %s. Expected TableCell, ElementInterface, stringable, scalar, or null. %s provided.',
                $i,
                self::getDebugType($cell)
            ));
        }
        return \array_values($cells);
    }
}

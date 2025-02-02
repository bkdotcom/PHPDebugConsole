<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

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
    /**
     * Constructor
     *
     * @param iterable<TableCell|ElementInterface|string|numeric>|mixed $cells The cells in this row
     */
    public function __construct($cells = array())
    {
        parent::__construct(array(
            'cells' => self::asCells($cells),
            'horizontalCellContentAlignment' => null,
            'style' => null,
            'verticalCellContentAlignment' => null,
        ), 'TableRow');
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
                /** @var mixed */
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
    }

    /**
     * Get row's cells
     *
     * @return list<TableCell>
     */
    public function getCells()
    {
        /** @psalm-var list<TableCell> */
        return $this->fields['cells'];
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
     * @param iterable<array-key,ElementInterface|string|numeric> $cells The cells in this row
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
     * @param Enums::HORIZONTAL_ALIGNMENT_* $alignment Horizontal alignment
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
     * @param Enums::CONTAINER_STYLE_* $style Container style
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
     * @param Enums::HORIZONTAL_ALIGNMENT_* $alignment Horizontal alignment
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
     * @param TableCell|ElementInterface|string|numeric|mixed $cell Value to normalize
     *
     * @return TableCell
     *
     * @throws InvalidArgumentException
     */
    private static function asCell($cell)
    {
        return $cell instanceof TableCell
            ? $cell
            : new TableCell($cell);
    }

    /**
     * Return array with each value converted to instance of TableCell
     *
     * @param iterable<TableCell|ElementInterface|string|numeric>|mixed $cells Cells to convert
     *
     * @return list<TableCell>
     *
     * @throws InvalidArgumentException
     */
    private function asCells($cells)
    {
        $isIterable = \is_array($cells) || ($cells instanceof Traversable);
        if ($isIterable === false) {
            throw new InvalidArgumentException('Table cells must be an iterator of cells.');
        }
        $i = 0;
        $cell = null;
        try {
            $cellsNew = array();
            /**
             * @var array-key $i
             * @var mixed     $cell
             */
            foreach ($cells as $i => $cell) {
                $cellsNew[] = self::asCell($cell);
            }
            return $cellsNew;
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(\sprintf(
                'Invalid table cell found at index %s.  Expected TableCell, ElementInterface, stringable, scalar, or null.  %s provided.',
                (string) $i,
                self::getDebugType($cell)
            ));
        }
    }
}

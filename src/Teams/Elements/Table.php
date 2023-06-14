<?php

namespace bdk\Teams\Elements;

use bdk\Teams\Enums;
use InvalidArgumentException;
use Traversable;

/**
 * Provides a way to display data in a tabular form
 *
 * As a convenience, we will auto populate the "columns" attribute.
 * If desired, columns may be specified via withColumns and/or withAddedColumn
 */
class Table extends AbstractElement
{
    /**
     * Constructor
     *
     * @param iterable|TableRow[] $rows Table rows
     */
    public function __construct($rows = array())
    {
        parent::__construct();
        $this->type = 'Table';
        $this->fields = \array_merge($this->fields, array(
            'columns' => array(),
            'firstRowAsHeader' => null,
            'gridStyle' => null,
            'horizontalCellContentAlignment' => null,
            'rows' => self::asRows($rows),
            'showGridLines' => null,
            'verticalCellContentAlignment' => null,
        ));
    }

    /**
     * Returns content of card element
     *
     * @param float $version Card version
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function getContent($version)
    {
        $attrVersions = array(
            'columns' => 1.5,
            'firstRowAsHeader' => 1.5,
            'gridStyle' => 1.5,
            'horizontalCellContentAlignment' => 1.5,
            'rows' => 1.5,
            'showGridLines' => 1.5,
            'verticalCellContentAlignment' => 1.5,
        );

        if ($this->fields['columns'] === array()) {
            $colCount = $this->getColCount();
            $cols = \array_fill(0, $colCount, array());
            $tableTemp = $this->withColumns($cols);
            $this->fields['columns'] = $tableTemp->get('columns');
        }

        $content = parent::getContent($version);
        foreach ($attrVersions as $name => $ver) {
            if ($version >= $ver) {
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
    }

    /**
     * Return new instance with specified items
     *
     * @param string|numeric                $width               (default: 1) Pixel width or relative width
     * @param Enums::HORIZONTAL_ALIGNMENT_x $horizontalAlignment Horizontal alignment of cells
     * @param Enums::VERTICAL_ALIGNMENT_x   $verticalAlignment   Verical alignment of cells
     *
     * @return static
     *
     * @see https://adaptivecards.io/schemas/adaptive-card.json TableColumnDefinition
     */
    public function withAddedColumn($width = 1, $horizontalAlignment = null, $verticalAlignment = null)
    {
        self::assertWidth($width, __METHOD__);
        self::assertEnumValue($horizontalAlignment, 'HORIZONTAL_ALIGNMENT_', 'horizontalAlignment');
        self::assertEnumValue($verticalAlignment, 'VERTICAL_ALIGNMENT_', 'verticalAlignment');
        return $this->withAdded('columns', self::normalizeContent(array(
            'horizontalCellContentAlignment' => $horizontalAlignment,
            'verticalCellContentAlignment' => $verticalAlignment,
            'width' => $width,
        )));
    }

    /**
     * Return new instance with added row
     *
     * @param TableRow|iterable $tableRow TableRow
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withAddedRow($tableRow)
    {
        return $this->withAdded('rows', self::asRow($tableRow));
    }

    /**
     * Return new instance with specified column definitions
     *
     * If a row contains more cells than there are columns defined, the extra cells are ignored
     *
     * @param array{horizontalAlignment: Enums::HORIZONTAL_ALIGNMENT_x, verticalAlignment: Enums::VERTICALL_ALIGNMENT_x, width: string}[] $columns [description]
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withColumns(array $columns = array())
    {
        $defaultCol = array(
            'horizontalAlignment' => null,
            'horizontalCellContentAlignment' => null,
            'type' => 'TableColumnDefinition',
            'verticalAlignment' => null,
            'verticalCellContentAlignment' => null,
            'width' => 1,
        );
        $new = clone $this;
        \array_walk($columns, static function ($column, $i) use ($defaultCol, &$new) {
            if (\is_array($column) === false) {
                throw new InvalidArgumentException(\sprintf('non array TableColumnDefinition value found (index %s)', $i));
            }
            $column = \array_merge($defaultCol, $column);
            $unknownVals = \array_diff_key($column, $defaultCol);
            if (\count($unknownVals) > 0) {
                throw new InvalidArgumentException(\sprintf('unknown TableColumnDefinition key "%s" found (index %s)', \key($unknownVals), $i));
            }
            if ($column['type'] !== 'TableColumnDefinition') {
                throw new InvalidArgumentException(\sprintf('TableColumnDefinition type must be "TableColumnDefinition" (index %s)', $i));
            }
            $new = $new->withAddedColumn(
                $column['width'],
                $column['horizontalAlignment'] ?: $column['horizontalCellContentAlignment'],
                $column['verticalAlignment'] ?: $column['verticalCellContentAlignment']
            );
        });
        return $new;
    }

    /**
     * Return new instance with specified firstRowAsHEader
     *
     * Specifies whether the first row of the table should be treated as a header row, and be announced as such by accessibility software.
     *
     * @param bool $asHeader as firstRowAsHeader value
     *
     * @return static
     */
    public function withFirstRowAsHeader($asHeader = true)
    {
        self::assertBool($asHeader, 'asHeader');
        return $this->with('firstRowAsHeader', $asHeader);
    }

    /**
     * Return new instance with specified grid style
     *
     * @param Enums::CONTAINER_STYLE_x $style Container style
     *
     * @return static
     */
    public function withGridStyle($style)
    {
        self::assertEnumValue($style, 'CONTAINER_STYLE_', 'style');
        return $this->with('gridStyle', $style);
    }

    /**
     * Return new instance with specified horizontal alignment
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
     * Return new instance with the specified table rows
     *
     * @param TableRow[] $rows Rows of the table
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withRows($rows)
    {
        return $this->with('rows', self::asRows($rows));
    }

    /**
     * Return new instance with specified showGrid
     *
     * @param bool $showGrid Show grid?
     *
     * @return static
     */
    public function withShowGridLines($showGrid = true)
    {
        self::assertBool($showGrid, 'showGrid');
        return $this->with('showGridLines', $showGrid);
    }

    /**
     * Return new instance with specified horizontal alignment
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
     * Return row as TableRow instance
     *
     * @param TableRow|iterable $row Row to convert
     *
     * @return TableCell
     *
     * @throws InvalidArgumentException
     */
    private function asRow($row)
    {
        return $row instanceof TableRow
            ? $row
            : new TableRow($row);
    }

    /**
     * Return array with each value converted to instance of TableCell
     *
     * @param iterable|TableRow[] $rows Rows to convert
     *
     * @return TableRow[]
     *
     * @throws InvalidArgumentException
     */
    private function asRows($rows)
    {
        $isIterable = \is_array($rows) || ($rows instanceof Traversable);
        if ($isIterable === false) {
            throw new InvalidArgumentException(\sprintf(
                'Invalid rows. Expecting iterator of rows. %s provided.',
                self::getTypeDebug($rows)
            ));
        }
        foreach ($rows as $i => $row) {
            $rows[$i] = self::asRow($row);
        }
        return \array_values($rows);
    }

    /**
     * Assert valid width
     *
     * @param mixed  $val    Value to test
     * @param string $method Method making assertion
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private static function assertWidth($val, $method)
    {
        $tests = array(
            static function ($val) {
                return $val === null;
            },
            static function ($val) {
                self::assertPx($val);
            },
            static function ($val) {
                $isStrOrNum = \is_string($val) || \is_numeric($val);
                return $isStrOrNum && \preg_match('/^\d+(.\d+)?$/', (string) $val) === 1;
            },
        );
        $message = $method . ' - width should be number representing relative width, or pixel value';
        self::assertAnyOf($val, $tests, $message);
    }

    /**
     * Get maximum number or columns in table
     *
     * @return int
     */
    private function getColCount()
    {
        $colCount = 0;
        foreach ($this->fields['rows'] as $tableRow) {
            $rowColCount = \count($tableRow->get('cells'));
            if ($rowColCount > $colCount) {
                $colCount = $rowColCount;
            }
        }
        return $colCount;
    }
}

<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Elements\Table;
use bdk\Teams\Elements\TableRow;
use bdk\Teams\Enums;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Elements\Table
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class TableTest extends AbstractTestCaseWith
{
    public function testConstruct()
    {
        $table = new Table(array(
            array('name' => 'Billy', 'age' => 26, ),
            array('name' => 'Suzy', 'age' => 25, ),
        ));
        self::assertSame(array(
            'type' => 'Table',
            'columns' => array(
                array(
                    'width' => 1,
                ),
                array(
                    'width' => 1,
                ),
            ),
            'rows' => array(
                array(
                    'type' => 'TableRow',
                    'cells' => array(
                        array(
                            'type' => 'TableCell',
                            'items' => array(
                                array(
                                    'type' => 'TextBlock',
                                    'text' => 'Billy',
                                    'wrap' => true,
                                ),
                            ),
                        ),
                        array(
                            'type' => 'TableCell',
                            'items' => array(
                                array(
                                    'type' => 'TextBlock',
                                    'text' => '26',
                                    'wrap' => true,
                                ),
                            ),
                        ),
                    ),
                ),
                array(
                    'type' => 'TableRow',
                    'cells' => array(
                        array(
                            'type' => 'TableCell',
                            'items' => array(
                                array(
                                    'type' => 'TextBlock',
                                    'text' => 'Suzy',
                                    'wrap' => true,
                                ),
                            ),
                        ),
                        array(
                            'type' => 'TableCell',
                            'items' => array(
                                array(
                                    'type' => 'TextBlock',
                                    'text' => '25',
                                    'wrap' => true,
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        ), $table->getContent(1.5));
    }

    public function testGetContent()
    {
        $table = (new Table())
            ->withColumns(array(
                array(
                    'horizontalAlignment' => Enums::HORIZONTAL_ALIGNMENT_LEFT,
                    'verticalAlignment' => Enums::VERTICAL_ALIGNMENT_TOP,
                    'width' => '42px',
                ),
                array(
                    'horizontalCellContentAlignment' => Enums::HORIZONTAL_ALIGNMENT_LEFT,
                    'type' => 'TableColumnDefinition',
                    'verticalCellContentAlignment' => Enums::VERTICAL_ALIGNMENT_TOP,
                    'width' => '42px',
                ),
            ))
            ->withFirstRowAsHeader()
            ->withGridStyle(Enums::CONTAINER_STYLE_DEFAULT)
            ->withHorizontalCellContentAlignment(Enums::HORIZONTAL_ALIGNMENT_LEFT)
            ->withRows(array(
                new TableRow(),
            ))
            ->withAddedRow(new TableRow())
            ->withShowGridLines()
            ->withVerticalCellContentAlignment(Enums::VERTICAL_ALIGNMENT_TOP);
        self::assertSame(array(
            'type' => 'Table',
            'columns' => array(
                array(
                    'horizontalCellContentAlignment' => Enums::HORIZONTAL_ALIGNMENT_LEFT,
                    'verticalCellContentAlignment' => Enums::VERTICAL_ALIGNMENT_TOP,
                    'width' => '42px',
                ),
                array(
                    'horizontalCellContentAlignment' => Enums::HORIZONTAL_ALIGNMENT_LEFT,
                    'verticalCellContentAlignment' => Enums::VERTICAL_ALIGNMENT_TOP,
                    'width' => '42px',
                ),
            ),
            'firstRowAsHeader' => true,
            'gridStyle' => Enums::CONTAINER_STYLE_DEFAULT,
            'horizontalCellContentAlignment' => Enums::HORIZONTAL_ALIGNMENT_LEFT,
            'rows' => array(
                array(
                    'type' => 'TableRow',
                ),
                array(
                    'type' => 'TableRow',
                ),
            ),
            'showGridLines' => true,
            'verticalCellContentAlignment' => Enums::VERTICAL_ALIGNMENT_TOP,
        ), $table->getContent(1.5));
    }

    public function testGetContentNoColumns()
    {
        $table = (new Table())
            ->withRows(array(
                new TableRow(['foo', 'bar', 'baz']),
            ));
        self::assertSame(array(
            'type' => 'Table',
            'columns' => array(
                array(
                    'width' => 1,
                ),
                array(
                    'width' => 1,
                ),
                array(
                    'width' => 1,
                ),
            ),
            'rows' => array(
                array(
                    'type' => 'TableRow',
                    'cells' => array(
                        array(
                            'type' => 'TableCell',
                            'items' => array(
                                array(
                                    'type' => 'TextBlock',
                                    'text' => 'foo',
                                    'wrap' => true,
                                ),
                            ),
                        ),
                        array(
                            'type' => 'TableCell',
                            'items' => array(
                                array(
                                    'type' => 'TextBlock',
                                    'text' => 'bar',
                                    'wrap' => true,
                                ),
                            ),
                        ),
                        array(
                            'type' => 'TableCell',
                            'items' => array(
                                array(
                                    'type' => 'TextBlock',
                                    'text' => 'baz',
                                    'wrap' => true,
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        ), $table->getContent(1.5));
    }

    protected static function itemFactory()
    {
        return new Table();
    }

    protected static function withTestCases()
    {
        return array(
            array('addedColumn', []),
            array('addedColumn', ['42px']),
            array('addedColumn', ['42']),
            array('addedColumn', [null]),
            array('addedColumn', ['wrong'], true, ' - width should be number representing relative width, or pixel value'),
            array('addedRow', [new TableRow()]),
            array('columns', [
                [
                    [
                        'width' => '42px',
                    ],
                ],
            ]),
            array('columns', [
                [
                    'Not an array',
                ],
            ], true, 'non array TableColumnDefinition value found (index 0)'),
            array('columns', [
                [
                    ['foo' => 'bar'],
                ],
            ], true, 'unknown TableColumnDefinition key "foo" found (index 0)'),
            array('columns', [
                [
                    ['type' => 'Bogus'],
                ],
            ], true, 'TableColumnDefinition type must be "TableColumnDefinition" (index 0)'),
            // array('firstRowAsHeader', []),
            // array('gridStyle', [Enums::CONTAINER_STYLE_DEFAULT]),
            // array('horizontalCellContentAlignment', [Enums::HORIZONTAL_ALIGNMENT_LEFT]),
            array('rows', [[
                new TableRow(),
            ]]),
            array('rows', [
                [
                    ['foo','bar'],
                ],
            ]),
            array('rows', [false], true, 'Invalid rows. Expecting iterator of rows. boolean provided.'),
            array('rows', [
                [
                    false,
                ],
            ], true, 'Table cells must be an iterator of cells.'),
            // array('showGridLines', []),
            // array('verticalCellContentAlignment', [Enums::VERTICAL_ALIGNMENT_TOP]),
            //  AbstractToggleableItem
            array('id', ['123']),
            // AbstractExtendableItem
            // array('isVisible', []),
            array('requires', [['foo' => 123]]),
        );
    }
}

<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Elements\TableCell;
use bdk\Teams\Elements\TableRow;
use bdk\Teams\Elements\TextBlock;
use bdk\Teams\Enums;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Elements\TableRow
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class TableRowTest extends AbstractTestCaseWith
{
    public function testConstruct()
    {
        $tableRow = new TableRow(array(
            (new TableCell('foo'))->withStyle(Enums::CONTAINER_STYLE_DEFAULT),
            (new TextBlock('bar'))->withWeight(Enums::FONT_WEIGHT_BOLDER),
            'baz',
        ));
        self::assertSame(array(
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
                    'style' => Enums::CONTAINER_STYLE_DEFAULT,
                ),
                array(
                    'type' => 'TableCell',
                    'items' => array(
                        array(
                            'type' => 'TextBlock',
                            'text' => 'bar',
                            'weight' => Enums::FONT_WEIGHT_BOLDER,
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
        ), $tableRow->getContent(1.5));
    }

    public function testGetContent()
    {
        $tableRow = (new TableRow())
            ->withCells(array(
                (new TableCell('foo'))->withStyle(Enums::CONTAINER_STYLE_DEFAULT),
                'bar',
            ))
            ->withAddedCell('baz')
            ->withHorizontalCellContentAlignment(Enums::HORIZONTAL_ALIGNMENT_LEFT)
            ->withStyle(Enums::CONTAINER_STYLE_DEFAULT)
            ->withVerticalCellContentAlignment(Enums::VERTICAL_ALIGNMENT_TOP);
        self::assertSame(array(
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
                    'style' => Enums::CONTAINER_STYLE_DEFAULT,
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
            'horizontalCellContentAlignment' => Enums::HORIZONTAL_ALIGNMENT_LEFT,
            'style' => Enums::CONTAINER_STYLE_DEFAULT,
            'verticalCellContentAlignment' => Enums::VERTICAL_ALIGNMENT_TOP,
        ), $tableRow->getContent(1.5));
    }

    protected static function itemFactory()
    {
        return new TableRow();
    }

    protected static function withTestCases()
    {
        return array(
            array('addedCell', ['string']),
            array('addedCell', [new TableCell('foo')]),
            array('addedCell', [\fopen(__FILE__, 'r')], true, 'Invalid TableCell item found at index 0.  resource provided.'),
            array('cells', [[
                new TableCell('foo'),
                'bar',
            ]], false, null, static function (TableRow $tableRow) {
                // validate withCells replaces existing
                $tableRowNew = $tableRow->withCells(array('string',3.14));
                self::assertSame(array(
                    'type' => 'TableRow',
                    'cells' => array(
                        array(
                            'type' => 'TableCell',
                            'items' => array(
                                array(
                                    'type' => 'TextBlock',
                                    'text' => 'string',
                                    'wrap' => true,
                                ),
                            ),
                        ),
                        array(
                            'type' => 'TableCell',
                            'items' => array(
                                array(
                                    'type' => 'TextBlock',
                                    'text' => '3.14',
                                    // 'wrap' => true,
                                ),
                            ),
                        ),
                    ),
                ), $tableRowNew->getContent(1.5));
            }),
            array('cells', [false], true, 'Table cells must be an iterator of cells.'),
            array('cells', [[
                new TableCell('foo'),
                [['array val']],
            ]], true, 'Invalid table cell found at index 1.  Expected TableCell, ElementInterface, stringable, scalar, or null.  array provided.'),
            // array('horizontalCellContentAlignment', [Enums::HORIZONTAL_ALIGNMENT_LEFT]),
            // array('style', [Enums::CONTAINER_STYLE_DEFAULT]),
            // array('verticalCellContentAlignment', [Enums::VERTICAL_ALIGNMENT_TOP]),
        );
    }
}

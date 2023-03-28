<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Actions\ShowCard;
use bdk\Teams\Elements\TableCell;
use bdk\Teams\Elements\TextBlock;
use bdk\Teams\Enums;
use bdk\Test\Teams\AbstractTestCaseWith;
use bdk\Test\Teams\Fixture\Stringable;

/**
 * @covers \bdk\Teams\Elements\TableCell
 */
class TableCellTest extends AbstractTestCaseWith
{
    protected static $withMethods = array(
        'rtl?' => 'withRtl',
    );

    public function testConstruct()
    {
        $tableCell = new TableCell(array(
            (new TextBlock('foo'))->withWeight(Enums::FONT_WEIGHT_BOLDER),
            new Stringable('bar'),
            'baz',
            true,
            false,
            null,
        ));
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'TableCell',
            'items' => array(
                array(
                    'type' => 'TextBlock',
                    'text' => 'foo',
                    'weight' => Enums::FONT_WEIGHT_BOLDER,
                ),
                array(
                    'type' => 'TextBlock',
                    'text' => 'bar',
                    'wrap' => true,
                ),
                array(
                    'type' => 'TextBlock',
                    'text' => 'baz',
                    'wrap' => true,
                ),
                array(
                    'type' => 'TextBlock',
                    'color' => 'good',
                    'fontType' => Enums::FONT_TYPE_MONOSPACE,
                    'text' => 'true',
                ),
                array(
                    'type' => 'TextBlock',
                    'color' => 'warning',
                    'fontType' => Enums::FONT_TYPE_MONOSPACE,
                    'text' => 'false',
                ),
                array(
                    'type' => 'TextBlock',
                    'fontType' => Enums::FONT_TYPE_MONOSPACE,
                    'isSubtle' => true,
                    'text' => 'null',
                ),
            ),
        ), $tableCell->getContent(1.5));
    }

    public function testConstructNotArray()
    {
        $tableCell = new TableCell(
            (new TextBlock('foo'))->withWeight(Enums::FONT_WEIGHT_BOLDER)
        );
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'TableCell',
            'items' => array(
                array(
                    'type' => 'TextBlock',
                    'text' => 'foo',
                    'weight' => Enums::FONT_WEIGHT_BOLDER,
                ),
            ),
        ), $tableCell->getContent(1.5));
    }

    public function testGetContent()
    {
        $tableCell = (new TableCell())
            ->withBackgroundImage('http://example.com/cat.jpg', Enums::FILLMODE_COVER)
            ->withBleed()
            ->withItems(array(
                new TextBlock('foo'),
                'bar',
                3.14,
            ))
            ->withAddedItem(42)
            ->withMinHeight('42px')
            ->withRtl()
            ->withSelectAction(new OpenUrl('http://example.com'))
            ->withStyle(Enums::CONTAINER_STYLE_DEFAULT)
            ->withVerticalContentAlignment(Enums::VERTICAL_ALIGNMENT_TOP);
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'TableCell',
            'backgroundImage' => array(
                'fillmode' => 'cover',
                'url' => 'http://example.com/cat.jpg',
            ),
            'bleed' => true,
            'items' => array(
                array(
                    'type' => 'TextBlock',
                    'text' => 'foo',
                ),
                array(
                    'type' => 'TextBlock',
                    'text' => 'bar',
                    'wrap' => true,
                ),
                array(
                    'type' => 'TextBlock',
                    'text' => '3.14',
                    'wrap' => true,
                ),
                array(
                    'type' => 'TextBlock',
                    'text' => '42',
                    'wrap' => true,
                ),
            ),
            'minHeight' => '42px',
            'rtl?' => true,
            'selectAction' => array(
                'type' => 'Action.OpenUrl',
                'url' => 'http://example.com',
            ),
            'style' => 'default',
            'verticalContentAlignment' => 'top',
        ), $tableCell->getContent(1.5));
    }

    public function testGetContentException()
    {
        $tableCell = new TableCell();
        self::expectException('RuntimeException');
        self::expectExceptionMessage('TableCell items is empty');
        $tableCell->getContent(1.5);
    }

    protected static function itemFactory()
    {
        return new TableCell();
    }

    protected static function withTestCases()
    {
        return array(
            array('addedItem', [new TextBlock(3.14)]),
            array('addedItem', ['string']),
            array('addedItem', [42]),
            array('addedItem', [['array val']], true, 'Invalid TableCell item found. Expecting ElementInterface, stringable, scalar, or null. array provided.'),
            // array('backgroundImage', ['http://example.com/cat.jpg', Enums::FILLMODE_COVER]),
            // array('backgroundImage', ['http://example.com/cat.jpg']),
            // array('bleed', []),
            array('items', [['string']]),
            array('items', [[array('string')]], true, 'Invalid TableCell item type (array) found at index 0'),
            array('minHeight', ['42px']),
            array('minHeight', [null]),
            // array('rtl', []),
            array('selectAction', [new OpenUrl('http://example.com')]),
            array('selectAction', [new ShowCard()], true, 'TableCell selectAction does not support ShowCard'),
            // array('style', [Enums::CONTAINER_STYLE_DEFAULT]),
            // array('verticalContentAlignment', [Enums::VERTICAL_ALIGNMENT_TOP]),
        );
    }
}

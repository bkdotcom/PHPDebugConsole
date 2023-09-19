<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Actions\ShowCard;
use bdk\Teams\Elements\Column;
use bdk\Teams\Elements\ColumnSet;
use bdk\Teams\Elements\TextBlock;
use bdk\Teams\Enums;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Elements\ColumnSet
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class ColumnSetTest extends AbstractTestCaseWith
{
    public function testContentGet()
    {
        $columnSet = (new ColumnSet())
            ->withBleed()
            ->withHorizontalAlignment(Enums::HORIZONTAL_ALIGNMENT_LEFT)
            ->withColumns(array(
                new Column(array(
                    new TextBlock('beans'),
                )),
                new Column(array(
                    new TextBlock('cornbread'),
                )),
            ))
            ->withMinHeight('42px')
            ->withSelectAction(new OpenUrl('http://example.com'))
            ->withStyle(Enums::CONTAINER_STYLE_DEFAULT);
        self::assertSame(array(
            'type' => 'ColumnSet',
            'bleed' => true,
            'columns' => array(
                array(
                    'type' => 'Column',
                    'items' => array(
                        array(
                            'type' => 'TextBlock',
                            'text' => 'beans',
                        ),
                    ),
                ),
                array(
                    'type' => 'Column',
                    'items' => array(
                        array(
                            'type' => 'TextBlock',
                            'text' => 'cornbread',
                        ),
                    ),
                ),
            ),
            'horizontalAlignment' => 'left',
            'selectAction' => array(
                'type' => 'Action.OpenUrl',
                'url' => 'http://example.com',
            ),
            'style' => 'default',
        ), $columnSet->getContent(1.2));
    }

    public function testContentGetException()
    {
        $columnSet = new ColumnSet();
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('ColumnSet columns is empty');
        $columnSet->getContent(1.2);
    }

    protected static function itemFactory()
    {
        return new ColumnSet();
    }

    protected static function withTestCases()
    {
        return array(
            // array('bleed', []),
            // array('horizontalAlignment', [Enums::HORIZONTAL_ALIGNMENT_LEFT]),
            array('columns', []),
            array('columns', [[
                new Column(array(
                    new TextBlock('beans'),
                )),
                'You fool!',
            ]], true, 'ColumnSet: Non-column found at index 1'),
            array('minHeight', ['42px']),
            array('selectAction', [new OpenUrl()]),
            array('selectAction', [new ShowCard()], true, 'ColumnSet selectAction does not support ShowCard'),
            // array('style', [Enums::CONTAINER_STYLE_DEFAULT]),
        );
    }
}

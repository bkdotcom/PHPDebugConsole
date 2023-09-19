<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Actions\ShowCard;
use bdk\Teams\Elements\Column;
use bdk\Teams\Elements\TextBlock;
use bdk\Teams\Enums;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\AbstractItem
 * @covers \bdk\Teams\AbstractExtendableItem
 * @covers \bdk\Teams\Elements\AbstractToggleableItem
 * @covers \bdk\Teams\Elements\Column
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class ColumnTest extends AbstractTestCaseWith
{
    public function testConstruct()
    {
        $column = new Column(array(
            new TextBlock('foo'),
            new TextBlock('bar'),
        ));
        self::assertSame(array(
            'type' => 'Column',
            'items' => array(
                array(
                    'type' => 'TextBlock',
                    'text' => 'foo',
                ),
                array(
                    'type' => 'TextBlock',
                    'text' => 'bar',
                ),
            ),
        ), $column->getContent(1.2));
    }

    public function testGetContent()
    {
        $column = (new Column())
            ->withBackgroundImage('http://example.com/cat.jpg', Enums::FILLMODE_COVER)
            ->withBleed()
            ->withFallback(Enums::FALLBACK_DROP)
            ->withId('123')
            ->withIsVisible()
            ->withItems(array(
                new TextBlock('foo'),
                new TextBlock('bar'),
            ))
            ->withMinHeight('50px')
            ->withRequires(array(
                'foo' => 1.2,
            ))
            ->withRtl()
            ->withSelectAction(new OpenUrl('http://example.com/'))
            ->withSeparator()
            ->withSpacing(Enums::SPACING_SMALL)
            ->withStyle(Enums::CONTAINER_STYLE_DEFAULT)
            ->withVerticalContentAlignment(Enums::VERTICAL_ALIGNMENT_TOP)
            ->withWidth('50px');
        self::assertSame(array(
            'type' => 'Column',
            'backgroundImage' => array(
                'fillmode' => Enums::FILLMODE_COVER,
                'url' => 'http://example.com/cat.jpg',
            ),
            'bleed' => true,
            'fallback' => Enums::FALLBACK_DROP,
            'id' => '123',
            'isVisible' => true,
            'items' => array(
                array(
                    'type' => 'TextBlock',
                    'text' => 'foo',
                ),
                array(
                    'type' => 'TextBlock',
                    'text' => 'bar',
                ),
            ),
            'minHeight' => '50px',
            'requires' => array(
                'foo' => 1.2,
            ),
            'rtl' => true,
            'selectAction' => array(
                'type' => 'Action.OpenUrl',
                'url' => 'http://example.com/',
            ),
            'separator' => true,
            'spacing' => Enums::SPACING_SMALL,
            'style' => Enums::CONTAINER_STYLE_DEFAULT,
            'verticalContentAlignment' => Enums::VERTICAL_ALIGNMENT_TOP,
            'width' => '50px',
        ), $column->getContent(1.5));
    }

    public function testGetContentNoItems()
    {
        $column = new Column();
        $this->expectException('RuntimeException');
        $column->getContent(1.2);
    }

    protected static function itemFactory()
    {
        return new Column();
    }

    protected static function withTestCases()
    {
        return array(
            // array('backgroundImage', ['http://example.com/cat.jpg']),
            // array('bleed', []),
            // array('bleed', ['true'], true, 'bleed must be bool. string provided'),
            // array('bleed', [(object) array()], true, 'bleed must be bool. stdClass provided'),
            // array('fallback', [Enums::FALLBACK_DROP]),
            array('fallback', [new Column()]),
            array('items', []),
            array('items', [['foo', 'bar']], true, 'Invalid column item found at index 0'),
            array('minHeight', ['42px']),
            array('minHeight', [42], true, 'bdk\\Teams\\Elements\\Column::withMinHeight - Invalid pixel value (ie "42px"). integer provided.'),
            // array('rtl', []),
            array('selectAction', [new OpenUrl('http://example.com')]),
            array('selectAction', [new ShowCard()], true, 'Column selectAction does not support ShowCard'),
            // array('separator', []),
            // array('spacing', [Enums::SPACING_SMALL]),
            // array('style', [Enums::CONTAINER_STYLE_DEFAULT]),
            // array('verticalContentAlignment', [Enums::VERTICAL_ALIGNMENT_TOP]),
            array('width', [Enums::COLUMN_WIDTH_AUTO]),
            array('width', [null]),
            array('width', ['42px']),
            array('width', [42]),
            array('width', [(object) array(1,2,3)], true),
            //  AbstractToggleableItem
            array('id', ['123']),
            // AbstractExtendableItem
            // array('isVisible', []),
            array('requires', [['foo' => 123]]),
        );
    }
}

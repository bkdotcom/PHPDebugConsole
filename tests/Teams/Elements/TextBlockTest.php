<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Elements\TextBlock;
use bdk\Teams\Enums;
use bdk\Test\Teams\AbstractTestCaseWith;
use bdk\Test\Teams\Fixture\Stringable;

/**
 * @covers \bdk\Teams\AbstractItem
 * @covers \bdk\Teams\AbstractExtendableItem
 * @covers \bdk\Teams\Elements\AbstractElement
 * @covers \bdk\Teams\Elements\AbstractToggleableItem
 * @covers \bdk\Teams\Elements\TextBlock
 */
class TextBlockTest extends AbstractTestCaseWith
{
    public static function setUpBeforeClass(): void
    {
        // clear cached enums
        parent::setUpBeforeClass();
        $refClass = new \ReflectionClass('bdk\\Teams\\AbstractItem');
        $refProp = $refClass->getProperty('constants');
        $refProp->setAccessible('true');
        $refProp->setValue(null);
    }

    public function testConstruct()
    {
        $textBlock = new TextBlock(new Stringable('hello world'));
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'TextBlock',
            'text' => 'hello world',
        ), $textBlock->getContent(1.2));
    }

    public function testGetContent()
    {
        $textBlock = (new TextBlock())
            ->withText('hello world')
            ->withColor(Enums::COLOR_DEFAULT)
            ->withFontType(Enums::FONT_TYPE_DEFAULT)
            ->withHorizontalAlignment(Enums::HORIZONTAL_ALIGNMENT_CENTER)
            ->withIsSubtle()
            ->withMaxLines(5)
            ->withSize(Enums::FONT_SIZE_DEFAULT)
            ->withWeight(Enums::FONT_WEIGHT_DEFAULT)
            ->withWrap()
            // inherited methods
            ->withFallback(Enums::FALLBACK_DROP)
            ->withHeight(Enums::HEIGHT_AUTO)
            ->withId('test1')
            ->withIsVisible()
            ->withSeparator()
            ->withSpacing(Enums::SPACING_DEFAULT);
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'TextBlock',
            'color' => Enums::COLOR_DEFAULT,
            'fallback' => Enums::FALLBACK_DROP,
            'fontType' => Enums::FONT_TYPE_DEFAULT,
            'height' => Enums::HEIGHT_AUTO,
            'horizontalAlignment' => Enums::HORIZONTAL_ALIGNMENT_CENTER,
            'id' => 'test1',
            'isSubtle' => true,
            'isVisible' => true,
            'maxLines' => 5,
            'separator' => true,
            'size' => Enums::FONT_SIZE_DEFAULT,
            'spacing' => Enums::SPACING_DEFAULT,
            'text' => 'hello world',
            'weight' => Enums::FONT_WEIGHT_DEFAULT,
            'wrap' => true,
        ), $textBlock->getContent(1.2));
    }

    public function testGetContentException()
    {
        $textBlock = new TextBlock();
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('TextBlock text is required');
        $textBlock->getContent(1.2);
    }

    protected static function itemFactory()
    {
        return new TextBlock();
    }

    protected static function withTestCases()
    {
        return array(
            // array('color', [Enums::COLOR_DEFAULT]),
            // array('fontType', [Enums::FONT_TYPE_DEFAULT]),
            // array('horizontalAlignment', [Enums::HORIZONTAL_ALIGNMENT_CENTER]),
            // array('isSubtle', []),
            array('maxlines', [0]),
            array('maxLines', [false], true, 'withMaxLines expects int or null. boolean provided.'),
            // array('size', [Enums::FONT_SIZE_DEFAULT]),
            // array('style', [Enums::TEXTBLOCK_STYLE_DEFAULT]),
            // array('text', ['hello world']),
            // array('text', [false], true, 'bdk\Teams\Elements\TextBlock::withText expects a string or numeric value. boolean provided.'),
            // array('weight', [Enums::FONT_WEIGHT_DEFAULT]),
            // array('wrap', []),
            // inherited
            // array('fallback', [Enums::FALLBACK_DROP]),
            // array('height', [Enums::HEIGHT_AUTO]),
            // array('separator', []),
            // array('spacing', [Enums::SPACING_DEFAULT]),
            array('id', ['test1']),
            // array('isVisible', []),
            array('requires', []),
        );
    }
}

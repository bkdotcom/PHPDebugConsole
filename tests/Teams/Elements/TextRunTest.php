<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Actions\ShowCard;
use bdk\Teams\Elements\TextRun;
use bdk\Teams\Enums;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Elements\TextRun
 */
class TextRunTest extends AbstractTestCaseWith
{
    public function testConstruct()
    {
        $textRun = new TextRun('hello world');
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'TextRun',
            'text' => 'hello world',
        ), $textRun->getContent(1.2));
    }

    public function testGetContent()
    {
        $textRun = (new TextRun())
            ->withText('hello world')
            ->withColor(Enums::COLOR_DEFAULT)
            ->withFontType(Enums::FONT_TYPE_DEFAULT)
            ->withHighlight()
            ->withIsSubtle()
            ->withItalic()
            ->withSelectAction(new OpenUrl('http://example.com/'))
            ->withSize(Enums::FONT_SIZE_DEFAULT)
            ->withStrikethrough()
            ->withUnderline()
            ->withWeight(Enums::FONT_WEIGHT_DEFAULT);
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'TextRun',
            'color' => Enums::COLOR_DEFAULT,
            'fontType' => Enums::FONT_TYPE_DEFAULT,
            'highlight' => true,
            'isSubtle' => true,
            'italic' => true,
            'selectAction' => array(
                'type' => 'Action.OpenUrl',
                'url' => 'http://example.com/',
            ),
            'size' => Enums::FONT_SIZE_DEFAULT,
            'strikethrough' => true,
            'text' => 'hello world',
            'weight' => Enums::FONT_WEIGHT_DEFAULT,
        ), $textRun->getContent(1.2));
    }

    public function testGetContentNoText()
    {
        $textRun = new TextRun();
        self::expectException('RuntimeException');
        self::expectExceptionMessage('TextRun text is required');
        $textRun->getContent(1.2);
    }

    protected static function itemFactory()
    {
        return new TextRun();
    }

    protected static function withTestCases()
    {
        return array(
            // array('color', [Enums::COLOR_DEFAULT]),
            // array('fontType', [Enums::FONT_TYPE_DEFAULT]),
            // array('highlight', []),
            // array('isSubtle', []),
            // array('italic', []),
            // array('size', [Enums::FONT_SIZE_DEFAULT]),
            array('selectAction', [new OpenUrl('http://example.com/')]),
            array('selectAction', [new ShowCard()], true, 'TextRun selectAction does not support ShowCard'),
            // array('strikethrough', []),
            // array('text', ['hello world']),
            // array('underline', []),
            // array('weight', [Enums::FONT_WEIGHT_DEFAULT]),
        );
    }
}

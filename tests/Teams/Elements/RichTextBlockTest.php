<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Elements\RichTextBlock;
use bdk\Teams\Elements\TextRun;
use bdk\Teams\Enums;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Elements\RichTextBlock
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class RichTextBlockTest extends AbstractTestCaseWith
{
    use ExpectExceptionTrait;

    public function testConstruct()
    {
        $rtb = new RichTextBlock(array(
            'block-o-text',
            (new TextRun('look at me'))->withHighlight(),
        ));
        self::assertSame(array(
            'type' => 'RichTextBlock',
            'inlines' => array(
                'block-o-text',
                array(
                    'type' => 'TextRun',
                    'highlight' => true,
                    'text' => 'look at me',
                ),
            ),
        ), $rtb->getContent(1.2));
    }

    public function testConstructException()
    {
        $this->expectException('InvalidArgumentException');
        new RichTextBlock(array(
            3.14,
        ));
    }

    public function testGetContentEmptyInlines()
    {
        $rtb = new RichTextBlock();
        $this->expectException('RuntimeException');
        $rtb->getContent(1.2);
    }

    protected static function itemFactory()
    {
        return new RichTextBlock();
    }

    protected static function withTestCases()
    {
        return array(
            array('addedText', ['look at me'], false, null, static function (RichTextBlock $rtb) {
                $rtb = $rtb->withAddedText('more please');
                self::assertSame(array(
                    'type' => 'RichTextBlock',
                    'inlines' => array(
                        'look at me',
                        'more please',
                    ),
                ), $rtb->getContent(1.2));
            }),
            array('addedText', [[array('abc')]], true, 'bdk\Teams\Elements\RichTextBlock::withAddedText expects a string, numeric, or stringable obj. array provided.'),
            array('addedTextRun', [new TextRun('look at me')], false, null, static function (RichTextBlock $rtb) {
                $rtb = $rtb->withAddedTextRun(new TextRun('more please'));
                self::assertSame(array(
                    'type' => 'RichTextBlock',
                    'inlines' => array(
                        array(
                            'type' => 'TextRun',
                            'text' => 'look at me',
                        ),
                        array(
                            'type' => 'TextRun',
                            'text' => 'more please',
                        ),
                    ),
                ), $rtb->getContent(1.2));
            }),
            // array('horizontalAlignment', [Enums::HORIZONTAL_ALIGNMENT_CENTER]),
            array('inlines', [['a', 'b', 'c']], false, null, static function (RichTextBlock $rtb) {
                $rtb = $rtb->withInlines(array(
                    'block-o-text',
                    (new TextRun('look at me'))->withHighlight(),
                ));
                self::assertSame(array(
                    'type' => 'RichTextBlock',
                    'inlines' => array(
                        'block-o-text',
                        array(
                            'type' => 'TextRun',
                            'highlight' => true,
                            'text' => 'look at me',
                        ),
                    ),
                ), $rtb->getContent(1.2));
            }),
        );
    }
}

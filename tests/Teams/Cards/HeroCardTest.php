<?php

namespace bdk\Test\Teams\Cards;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Cards\HeroCard;
use bdk\Teams\Enums;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Cards\HeroCard
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class HeroCardTest extends AbstractTestCaseWith
{
    public function testGetMessage()
    {
        $card = (new HeroCard())
            ->withAddedButton('openUrl', 'click me', 'http://example.com/')
            ->withAddedButton('openUrl', 'more info', 'http://example.com/foo')
            ->withAddedImage('http://example.com/cat1.png')
            ->withAddedImage('http://example.com/cat2.png')
            ->withTap(new OpenUrl('http://example.com/'))
            ->withText('text')
            ->withTitle('title')
            ->withSubtitle('subtitle');
        self::assertSame(array(
            'type' => 'message',
            'attachments' => array(
                'contentType' => 'application/vnd.microsoft.card.hero',
                'content' => array(
                    'buttons' => array(
                        array(
                            'type' => 'openUrl',
                            'title' => 'click me',
                            'value' => 'http://example.com/',
                        ),
                        array(
                            'type' => 'openUrl',
                            'title' => 'more info',
                            'value' => 'http://example.com/foo',
                        ),
                    ),
                    'images' => array(
                        array(
                            'url' => 'http://example.com/cat1.png',
                        ),
                        array(
                            'url' => 'http://example.com/cat2.png',
                        ),
                    ),
                    'subtitle' => 'subtitle',
                    'tap' => array(
                        'type' => 'Action.OpenUrl',
                        'url' => 'http://example.com/',
                    ),
                    'text' => 'text',
                    'title' => 'title',
                ),
            ),
        ), $card->getMessage());
    }

    protected static function itemFactory()
    {
        return new HeroCard();
    }

    protected static function withTestCases()
    {
        return array(
            array('addedButton', [Enums::ACTION_TYPE_OPENURL, 'title', 'value']),
            array('addedImage', ['http://example.com/cat.png']),
            // array('subtitle', ['sub`title']),
            array('tap', [new OpenUrl('http://example.com')]),
            // array('text', ['text']),
            // array('title', ['title']),
        );
    }
}

<?php

namespace bdk\Test\Teams\Actions;

use bdk\Teams\Actions\ShowCard;
use bdk\Teams\Cards\AdaptiveCard;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Actions\ShowCard
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class ShowCardTest extends AbstractTestCaseWith
{
    use ExpectExceptionTrait;

    public function testGetContent()
    {
        $showCard = (new ShowCard())
            ->withAddedElement(new \bdk\Teams\Elements\Image('http://example.com/image.png'))
            ->withAddedAction(new \bdk\Teams\Actions\OpenUrl('http://example.com/'));
        self::assertSame(array(
            'type' => 'Action.ShowCard',
            'card' => array(
                'type' => 'AdaptiveCard',
                '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                'actions' => array(
                    array(
                        'type' => 'Action.OpenUrl',
                        'url' => 'http://example.com/',
                    ),
                ),
                'body' => array(
                    array(
                        'type' => 'Image',
                        'url' => 'http://example.com/image.png',
                    ),
                ),
                'version' => 1.5,
            ),
        ), $showCard->getContent(1.2));
    }

    protected static function itemFactory()
    {
        return new ShowCard();
    }

    protected static function withTestCases()
    {
        return array(
            array('addedAction', [new \bdk\Teams\Actions\OpenUrl('http://example.com/')]),
            array('addedElement', [new \bdk\Teams\Elements\Image('http://example.com/image.png')]),
            array('card', [new AdaptiveCard()]),
        );
    }
}

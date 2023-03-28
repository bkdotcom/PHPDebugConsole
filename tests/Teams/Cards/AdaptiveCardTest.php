<?php

namespace bdk\Test\Teams\Cards;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Actions\ShowCard;
use bdk\Teams\Cards\AdaptiveCard;
use bdk\Teams\Elements\TextBlock;
use bdk\Teams\Enums;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Cards\AbstractCard
 * @covers \bdk\Teams\Cards\AdaptiveCard
 */
class AdaptiveCardTest extends AbstractTestCaseWith
{
    protected static $unsupportedAttributes = array(
        'authentication',
        'refresh',
        '$schema',
    );

    public function testConstructInvalidArg()
    {
        self::expectException('InvalidArgumentException');
        new AdaptiveCard(123);
    }

    public function testGetMessage()
    {
        $card = (new AdaptiveCard())
            ->withAddedAction(new ShowCard())
            ->withAddedElement(new \bdk\Teams\Elements\TextBlock('test block 1'));
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedKeys.IncorrectKeyOrder
        $expect = array(
            'type' => 'message',
            'attachments' => array(
                array(
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => array(
                        'type' => 'AdaptiveCard',
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'actions' => array(
                            array(
                                'type' => 'Action.ShowCard',
                                'card' => array(
                                    'type' => 'AdaptiveCard',
                                    '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                                    'version' => 1.5,
                                ),
                            ),
                        ),
                        'body' => array(
                            array(
                                'type' => 'TextBlock',
                                'text' => 'test block 1',
                            ),
                        ),
                        'version' => 1.5,
                    ),
                ),
            ),
        );

        self::assertSame($expect, $card->getMessage());
        self::assertSame($expect, \json_decode(\json_encode($card), true));
    }

    public function testWithBackgroundImageObjException()
    {
        $card = new AdaptiveCard(1.1);
        self::expectException('InvalidArgumentException');
        self::expectExceptionMessage('backgroundImage fillmode, horizontalAlignment, & verticalAlignment values required card version 1.2 or greater');
        $card->withBackgroundImage('http://example.com/img.jpg', Enums::FILLMODE_COVER);
    }

    protected static function itemFactory()
    {
        return new AdaptiveCard();
    }

    protected static function withTestCases()
    {
        return array(
            array('addedAction', [new ShowCard()]),
            array('addedElement', [new TextBlock('test block 1')]),
            array('actions', [[new ShowCard()]]),
            array('actions', [['foo']], true, 'bdk\Teams\Cards\AdaptiveCard::withActions: Invalid action found at index 0'),
            // array('backgroundImage', ['http://example.com/img.jpg']),
            array('backgroundImage', ['http://example.com/img.jpg', Enums::FILLMODE_COVER], false, null, static function (AdaptiveCard $card) {
                self::assertSame(array(
                    'fillmode' => Enums::FILLMODE_COVER,
                    'url' => 'http://example.com/img.jpg',
                ), $card->getMessage()['attachments'][0]['content']['backgroundImage']);
            }),
            array('body', [[new TextBlock('test block 1')]]),
            array('body', [['foo']], true, 'bdk\Teams\Cards\AdaptiveCard::withBody: Invalid element found at index 0'),
            array('fallbackText', ['springForward']),
            array('lang', ['US']),
            array('lang', ['bob'], true, 'Lang must be a 2-letter string'),
            array('minHeight', ['1px']),
            // array('rtl', []),
            array('selectAction', [new OpenUrl()]),
            array('selectAction', [new ShowCard()], true, 'AdaptiveCard selectAction does not support ShowCard'),
            array('speak', ['say what']),
            array('version', [1.2]),
            array('version', [666], true, 'Invalid version'),
            // array('verticalContentAlignment', [Enums::VERTICAL_ALIGNMENT_TOP]),
        );
    }
}

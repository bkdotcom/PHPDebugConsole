<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Actions\ShowCard;
use bdk\Teams\Elements\ActionSet;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Elements\ActionSet
 */
class ActionSetTest extends AbstractTestCaseWith
{
    public function testConstructException()
    {
        self::expectException('InvalidArgumentException');
        new ActionSet(array(
            new ShowCard(),
            new OpenUrl('http://example.com/'),
            array('whoa'),
        ));
    }

    public function testGetContent()
    {
        $actionSet = new ActionSet(array(
            new ShowCard(),
            new OpenUrl('http://example.com/'),
        ));
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'ActionSet',
            'actions' => array(
                array(
                    'type' => 'Action.ShowCard',
                    'card' => array(
                        'type' => 'AdaptiveCard',
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'version' => 1.5,
                    ),
                ),
                array(
                    'type' => 'Action.OpenUrl',
                    'url' => 'http://example.com/',
                ),
            ),
        ), $actionSet->getContent(1.2));
    }

    protected static function itemFactory()
    {
        return new ActionSet();
    }

    protected static function withTestCases()
    {
        return array(
            array('actions', [[
                new ShowCard(),
                new OpenUrl('http://example.com/'),
            ]]),
            array('actions', [[
                new ShowCard(),
                new OpenUrl('http://example.com/'),
                'whoa',
            ]], true, 'Invalid action found at index 2'),
            array('addedAction', [new OpenUrl('http://example.com/')]),
        );
    }
}

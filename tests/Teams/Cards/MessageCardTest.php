<?php

namespace bdk\Test\Teams\Cards;

use bdk\Teams\Cards\MessageCard;
use bdk\Teams\Section;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\AbstractItem
 * @covers \bdk\Teams\Cards\MessageCard
 */
class MessageCardTest extends AbstractTestCaseWith
{
    use ExpectExceptionTrait;

    public function testConstruct()
    {
        $card = new MessageCard('title', 'text');
        self::assertSame(array(
            '@context' => 'http://schema.org/extensions',
            '@type' => 'MessageCard',
            'text' => 'text',
            'title' => 'title',
        ), $card->getMessage());
    }

    public function testConstructException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('bdk\Teams\Cards\MessageCard::__construct expects a string, numeric, stringable obj, or null. boolean provided.');
        new MessageCard(true, false);
    }

    public function testGetMessage()
    {
        $card = (new MessageCard())
            ->withTitle('title')
            ->withText('text')
            ->withSummary('summary')
            ->withActivity('title', 'subtitle', 'text', 'http://example.com/cat.jpg')
            ->withColor('BEEFED')
            ->withFacts(array(
                'foo' => 'bar',
                'zip' => 'zap',
            ), 'the more you know')
            ->withHeroImage('http://example.com/dog.gif', 'dog')
            ->withImage('http://example.com/img.png', 'title')
            ->withImages(array(
                'http://example.com/img1.png',
                'http://example.com/img2.png',
            ), 'title')
            ->withAction('text', 'http://example.com/')
            ->withAddedSection(
                (new Section())
                    ->withText('how about them apples')
            );
        self::assertSame(array(
            '@context' => 'http://schema.org/extensions',
            '@type' => 'MessageCard',
            'potentialAction' => array(
                array(
                    '@context' => 'http://schema.org',
                    '@type' => 'ViewAction',
                    'name' => 'text',
                    'target' => array(
                        'http://example.com/',
                    ),
                ),
            ),
            'sections' => array(
                array(
                    'activityImage' => 'http://example.com/cat.jpg',
                    'activitySubtitle' => 'subtitle',
                    'activityText' => 'text',
                    'activityTitle' => 'title',
                ),
                array(
                    'facts' => array(
                        array(
                            'name' => 'foo',
                            'value' => 'bar',
                        ),
                        array(
                            'name' => 'zip',
                            'value' => 'zap',
                        ),
                    ),
                    'title' => 'the more you know',
                ),
                array(
                    'heroImage' => array(
                        'image' => 'http://example.com/dog.gif',
                        'title' => 'dog',
                    ),
                ),
                array(
                    'images' => array(
                        array(
                            'image' => 'http://example.com/img.png',
                        ),
                    ),
                    'title' => 'title',
                ),
                array(
                    'images' => array(
                        array(
                            'image' => 'http://example.com/img1.png',
                        ),
                        array(
                            'image' => 'http://example.com/img2.png',
                        ),
                    ),
                    'title' => 'title',
                ),
                array(
                    'text' => 'how about them apples',
                ),
            ),
            'summary' => 'summary',
            'text' => 'text',
            'themeColor' => 'BEEFED',
            'title' => 'title',
        ), $card->getMessage());
    }

    protected static function itemFactory()
    {
        return new MessageCard();
    }

    protected static function withTestCases()
    {
        return array(
            array('action', ['text', 'http://example.com/']),
            array('activity', ['title', 'subtitle', 'text', 'http://example.com/cat.jpg']),
            array('addedSection', [new Section()]),
            array('color', ['BEEFED']),
            array('facts', [array(
                'foo' => 'bar',
                'zip' => 'zap',
            ), 'the more you know']),
            array('heroImage', ['http://example.com/img.png', 'title']),
            array('image', ['http://example.com/img.png', 'title']),
            array('images', [[
                'http://example.com/img1.png',
                'http://example.com/img2.png',
            ], 'title']),
            array('summary', ['beginning, middle, end']),
            array('text', ['text']),
            array('title', ['title']),
        );
    }
}

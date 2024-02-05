<?php

namespace bdk\Test\Teams;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Section;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\AbstractItem
 * @covers \bdk\Teams\Section
 */
class SectionTest extends AbstractTestCaseWith
{
    public function testDefault()
    {
        $section = new Section();
        self::assertSame(array(
        ), $section->getContent());
    }

    public function testGetContent()
    {
        $section = (new Section())
            ->withActivity('title', 'subtitle', 'text', 'http://example.com/img.png')
            ->withFacts(array(
                'foo' => 'bar',
                'zip' => 'zap',
            ))
            ->withHeroImage('http://example.com/superman.jpg', 'Super Hero')
            ->withImages(array(
                'http://www.example.com/img.jpg',
                array(
                    'image' => 'http://example.com/img2.jpg',
                    'title' => 'nifty',
                ),
            ))
            ->withPotentialAction(new OpenUrl('http://example.com/'))
            ->withStartGroup()
            ->withTitle('Chief Technical Guy')
            ->withText('words go here');
        self::assertSame(array(
            'activityImage' => 'http://example.com/img.png',
            'activitySubtitle' => 'subtitle',
            'activityText' => 'text',
            'activityTitle' => 'title',
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
            'heroImage' => array(
                'image' => 'http://example.com/superman.jpg',
                'title' => 'Super Hero',
            ),
            'images' => array(
                array(
                    'image' => 'http://www.example.com/img.jpg',
                ),
                array(
                    'image' => 'http://example.com/img2.jpg',
                    'title' => 'nifty',
                ),
            ),
            'potentialAction' => array(
                array(
                    'type' => 'Action.OpenUrl',
                    'url' => 'http://example.com/',
                ),
            ),
            'startGroup' => true,
            'text' => 'words go here',
            'title' => 'Chief Technical Guy',
        ), $section->getContent());
    }

    protected static function itemFactory()
    {
        return new Section();
    }

    protected static function withTestCases()
    {
        return array(
            array('activity', []),
            array('facts', [[
                'foo' => 'bar',
                'zip' => 'zap',
            ]], false, null, static function (Section $section) {
                $section = $section->withFacts(array(
                    'replaced' => 'yup',
                    // 'null' => null,
                ));
                self::assertSame(array(
                    'facts' => array(
                        array(
                            'name' => 'replaced',
                            'value' => 'yup',
                        ),
                        /*
                        array(
                            'name' => 'null',
                            'value' => '',
                        ),
                        */
                    ),
                ), $section->getContent());
            }),
            array('facts', [[
                'no sir' => false,
            ]], true, 'bdk\Teams\Section::withFacts - fact value should be a string, numeric, or stringable obj. boolean provided'),
            array('heroImage', ['http://example.com/superman.jpg', 'Super Dooper']),
            array('heroImage', [null]),
            array('images', [[
                'http://example.com/img1.jpg',
                'http://example.com/img2.jpg',
            ]], false, null, static function (Section $section) {
                $section = $section->withImages(array(
                    'http://example.com/img3.jpg',
                ));
                self::assertSame(array(
                    'images' => array(
                        array(
                            'image' => 'http://example.com/img3.jpg',
                        ),
                    ),
                ), $section->getContent());
            }),
            array('potentialAction', [
                new OpenUrl('http://example.com'),
            ]),
            array('potentialAction',
                [
                    \array_fill(0, 5, new OpenUrl('http://example.com')),
                ],
                'OutOfBoundsException',
                'There can be a maximum of 4 actions (whatever their type)',
            ),
            array('potentialAction',
                [
                    'you suck',
                ],
                true,
                'withPotentialAction: Invalid action found at index 0',
            ),
            array('startGroup', []),
            array('text', ['blah blah']),
            array('title', ['text']),
            array('title', [null]),
            array('title', [false], true, 'bdk\\Teams\\Section::withTitle expects a string, numeric, stringable obj, or null. boolean provided.'),
        );
    }
}

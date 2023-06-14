<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Elements\Media;
use bdk\Teams\Elements\MediaSource;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Elements\Media
 */
class MediaTest extends AbstractTestCaseWith
{
    use ExpectExceptionTrait;

    public function testConstruct()
    {
        $media = new Media([
            // 'http://example.com/cat.mp4',
            new MediaSource('http://example.com/cat.mp4', 'video/mp4'),
        ]);
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'Media',
            'sources' => array(
                // 'http://example.com/cat.mp4',
                array(
                    'mimeType' => 'video/mp4',
                    'url' => 'http://example.com/cat.mp4',
                ),
            ),
        ), $media->getContent(1.2));
    }

    public function testGetContent()
    {
        $media = (new Media())
            -> withSources([
                // 'http://example.com/cat.mp4',
                new MediaSource('http://example.com/cat2.mp4', 'video/mp4'),
            ])
            ->withAddedSource('http://example.com/cat3.mp4', 'video/mp4')
            ->withAddedSource(new MediaSource('http://example.com/cat4.mp4', 'video/mp4'))
            ->withAltText('Cat videos!')
            ->withPoster('http://example.com/cat.png');
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'Media',
            'altText' => 'Cat videos!',
            'poster' => 'http://example.com/cat.png',
            'sources' => array(
                // 'http://example.com/cat.mp4',
                array(
                    'mimeType' => 'video/mp4',
                    'url' => 'http://example.com/cat2.mp4',
                ),
                array(
                    'mimeType' => 'video/mp4',
                    'url' => 'http://example.com/cat3.mp4',
                ),
                array(
                    'mimeType' => 'video/mp4',
                    'url' => 'http://example.com/cat4.mp4',
                ),
            ),
        ), $media->getContent(1.2));
    }

    public function testGetContentEmptySources()
    {
        $media = new Media();
        $this->expectException('RuntimeException');
        $media->getContent(1.2);
    }

    protected static function itemFactory()
    {
        return new Media();
    }

    protected static function withTestCases()
    {
        return array(
            array('addedSource', ['http://example.com/cat.mp4', 'video/mp4']),
            array('addedSource', ['invalid source url'], true),
            array('sources', [[
                new MediaSource('http://example.com/cat.mp4', 'video/mp4'),
            ]], false, null, static function (Media $media) {
                // test that we replace
                $mediaNew = $media->withSources([
                    new MediaSource('http://example.com/cat2.mp4', 'video/mp4'),
                ]);
                self::assertSame(array(
                    'type' => 'Media',
                    'sources' => array(
                        // 'http://example.com/cat2.mp4',
                        array(
                            'mimeType' => 'video/mp4',
                            'url' => 'http://example.com/cat2.mp4',
                        ),
                    ),
                ), $mediaNew->getContent(1.2));
            }),
            array('sources', [['boo']], true, 'Invalid source found at index 0'),
            // array('poster', ['http://example.com/cat.png']),
            // array('poster', ['Do I look valid to you?'], true),
            array('altText', ['no media for you']),
        );
    }
}

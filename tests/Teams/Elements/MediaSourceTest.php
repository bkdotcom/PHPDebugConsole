<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Elements\MediaSource;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Elements\MediaSource
 */
class MediaSourceTest extends AbstractTestCaseWith
{
    use ExpectExceptionTrait;

    public function testConstruct()
    {
        $mediaSource = new MediaSource('http://example.com/cat.mp4', 'video/mp4');
        self::assertSame(array(
            'mimeType' => 'video/mp4',
            'url' => 'http://example.com/cat.mp4',
        ), $mediaSource->getContent(1.2));
    }

    public function testConstructException()
    {
        $this->expectException('InvalidArgumentException');
        $mediaSource = new MediaSource('bogus url', 'video/mp4');
    }

    public function testGetContent()
    {
        $mediaSource = (new MediaSource())
            ->withMimeType('video/mp4')
            ->withUrl('http://example.com/cat.mp4');
        self::assertSame(array(
            'mimeType' => 'video/mp4',
            'url' => 'http://example.com/cat.mp4',
        ), $mediaSource->getContent(1.2));
    }

    public function testGetContentNoMimeType()
    {
        $mediaSource = new MediaSource('http://example.com/cat.mp4');
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('MediaSource mimeType is required');
        $mediaSource->getContent(1.2);
    }

    public function testGetContentNoUrl()
    {
        $mediaSource = new MediaSource(null, 'video/mp4');
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('MediaSource url is required');
        $mediaSource->getContent(1.2);
    }

    protected static function itemFactory()
    {
        return new MediaSource();
    }

    protected static function withTestCases()
    {
        return array(
            array('mimeType', ['vide/mp4']),
            array('url', ['http://example.com/cat.mp4']),
        );
    }
}

<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Actions\ShowCard;
use bdk\Teams\Elements\Image;
use bdk\Teams\Enums;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\AbstractItem
 * @covers \bdk\Teams\Elements\Image
 */
class ImageTest extends AbstractTestCaseWith
{
    public function testConstruct()
    {
        $image = new Image('https://example.com/test.png');
        self::assertSame(array(
            'type' => 'Image',
            'url' => 'https://example.com/test.png',
        ), $image->getContent(1.2));
    }

    public function testGetContent()
    {
        $image = (new Image())
            ->withUrl('https://example.com/test.png')
            ->withAltText('No image for you')
            ->withBackgroundColor('#BEEFED')
            ->withHeight(Enums::HEIGHT_AUTO)
            ->withHorizontalAlignment(Enums::HORIZONTAL_ALIGNMENT_CENTER)
            ->withSelectAction(new OpenUrl('http://example.com/'))
            ->withSize(Enums::IMAGE_SIZE_AUTO)
            ->withStyle(Enums::IMAGE_STYLE_DEFAULT)
            ->withWidth('50px');
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'Image',
            'altText' => 'No image for you',
            'backgroundColor' => '#BEEFED',
            'height' => Enums::HEIGHT_AUTO,
            'horizontalAlignment' => Enums::HORIZONTAL_ALIGNMENT_CENTER,
            'selectAction' => array(
                'type' => 'Action.OpenUrl',
                'url' => 'http://example.com/',
            ),
            'size' => Enums::IMAGE_SIZE_AUTO,
            'style' => Enums::IMAGE_STYLE_DEFAULT,
            'url' => 'https://example.com/test.png',
            'width' => '50px',
        ), $image->getContent(1.2));
    }

    public function testGetContentNoUrl()
    {
        $image = new Image();
        self::expectException('RuntimeException');
        $image->getContent(1.2);
    }

    protected static function itemFactory()
    {
        return new Image();
    }

    protected static function withTestCases()
    {
        return array(
            // array('url', ['https://example.com/test.png']),
            array('url', ['data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==']),
            array('url', ['http:///bogus'], true),
            // array('url', ['beans'], true, 'Invalid url (or data url)'),
            // array('url', [true], true, 'Url should be a string. boolean provided.'),
            array('altText', ['No image for you']),
            array('altText', [null]),
            array('backgroundColor', ['#BEEFED']),
            array('backgroundColor', [null]),
            array('backgroundColor', ['BEEFED'], true),
            array('backgroundColor', [Enums::COLOR_DEFAULT], true),
            array('height', ['42px']),
            array('height', [Enums::HEIGHT_AUTO]),
            array('height', [null]),
            array('height', [42], true, 'bdk\Teams\Elements\Image::withHeight - height should be one of the Enums::HEIGHT_x constants or pixel value'),
            array('height', ['super tall'], true, 'bdk\Teams\Elements\Image::withHeight - height should be one of the Enums::HEIGHT_x constants or pixel value'),
            // array('horizontalAlignment', [Enums::HORIZONTAL_ALIGNMENT_CENTER]),
            array('selectAction', [new OpenUrl('http://example.com')]),
            array('selectAction', [new ShowCard()], true, 'Image selectAction does not support ShowCard'),
            // array('size', [Enums::IMAGE_SIZE_AUTO]),
            // array('style', [Enums::IMAGE_STYLE_DEFAULT]),
            array('width', ['42px']),
            array('width', [42], true, 'bdk\Teams\Elements\Image::withWidth - Invalid pixel value (ie "42px"). integer provided.'),
            array('width', ['42'], true, 'bdk\Teams\Elements\Image::withWidth - Invalid pixel value (ie "42px"). string provided.'),
        );
    }
}

<?php

namespace bdk\Test\Teams\Actions;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Enums;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Actions\AbstractAction
 * @covers \bdk\Teams\Actions\OpenUrl
 */
class OpenUrlTest extends AbstractTestCaseWith
{
    use ExpectExceptionTrait;

    public function testConstruct()
    {
        $openUrl = new OpenUrl('http://www.bradkent.com/');
        self::assertSame(array(
            'type' => 'Action.OpenUrl',
            'url' => 'http://www.bradkent.com/',
        ), $openUrl->getContent(1.2));
    }

    public function testConstructInvalidType()
    {
        $this->expectException('InvalidArgumentException');
        $openUrl = new OpenUrl(false);
    }

    public function testConstructInvalidUrl()
    {
        $this->expectException('InvalidArgumentException');
        $openUrl = new OpenUrl('bogus');
    }

    public function testGetContent()
    {
        $openUrl = (new OpenUrl())
            ->withUrl('http://example.com')
            ->withTitle('click here')
            ->withIconUrl('http://example.com/icon.png')
            ->withStyle(Enums::ACTION_STYLE_DEFAULT);
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'Action.OpenUrl',
            'iconUrl' => 'http://example.com/icon.png',
            'style' => Enums::ACTION_STYLE_DEFAULT,
            'title' => 'click here',
            'url' => 'http://example.com',
        ), $openUrl->getContent(1.2));
    }

    public function testGetContentException()
    {
        $openUrl = new OpenUrl();
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('OpenUrl url is required');
        $openUrl->getContent(1.2);
    }

    protected static function itemFactory()
    {
        return new OpenUrl();
    }

    protected static function withTestCases()
    {
        return array(
            // array('url', ['http://www.bradkent.com/']),
            array('fallback', [Enums::FALLBACK_DROP]),
            // array('iconUrl', ['http://example.com/icon.png']),
            array('iconUrl', ['data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==']),
            array('requires', [[
                'foo' => 1.2,
            ]]),
            // array('style', [Enums::ACTION_STYLE_DEFAULT]),
            // array('title', ['Click here']),
            array('id', ['foo']),
            // array('isEnabled', []),
            // array('mode', [Enums::ACTION_MODE_PRIMARY]),
            array('tooltip', ['wear protective eyewear']),
        );
    }
}

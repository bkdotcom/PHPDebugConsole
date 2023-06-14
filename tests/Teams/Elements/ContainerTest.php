<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Actions\OpenUrl;
use bdk\Teams\Actions\ShowCard;
use bdk\Teams\Elements\Container;
use bdk\Teams\Elements\Image;
use bdk\Teams\Elements\TextBlock;
use bdk\Teams\Enums;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use bdk\Test\Teams\AbstractTestCaseWith;

/**
 * @covers \bdk\Teams\Elements\Container
 */
class ContainerTest extends AbstractTestCaseWith
{
    use ExpectExceptionTrait;

    protected static $withMethods = array(
        'rtl?' => 'withRtl',
    );

    public function testConstruct()
    {
        $container = (new Container(array(
            new Image('http://example.com/cat.jpg'),
            new TextBlock('meow'),
        )))->withHeight(Enums::HEIGHT_AUTO);
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'Container',
            'height' => Enums::HEIGHT_AUTO,
            'items' => array(
                array(
                    'type' => 'Image',
                    'url' => 'http://example.com/cat.jpg',
                ),
                array(
                    'type' => 'TextBlock',
                    'text' => 'meow',
                ),
            ),
        ), $container->getContent(1.2));
    }

    public function testGetContent()
    {
        $container = (new Container())
            ->withBackgroundImage('http://example.com/cat.jpg', Enums::FILLMODE_COVER)
            ->withBleed()
            ->withFallback(Enums::FALLBACK_DROP)
            ->withHeight(Enums::HEIGHT_AUTO)
            ->withItems(array(
                new TextBlock('foo'),
                new TextBlock('bar'),
            ))
            ->withMinHeight('42px')
            ->withRtl()
            ->withSelectAction(new OpenUrl('http://example.com'))
            ->withSeparator()
            ->withSpacing(Enums::SPACING_DEFAULT)
            ->withStyle(Enums::CONTAINER_STYLE_DEFAULT)
            ->withVerticalContentAlignment(Enums::VERTICAL_ALIGNMENT_TOP)
            // inherited
            ->withId('123')
            ->withIsVisible()
            ->withRequires(array(
                'foo' => 1.2,
            ));
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        self::assertSame(array(
            'type' => 'Container',
            'backgroundImage' => array(
                'fillmode' => Enums::FILLMODE_COVER,
                'url' => 'http://example.com/cat.jpg',
            ),
            'bleed' => true,
            'fallback' => Enums::FALLBACK_DROP,
            'height' => Enums::HEIGHT_AUTO,
            'id' => '123',
            'isVisible' => true,
            'items' => array(
                array(
                    'type' => 'TextBlock',
                    'text' => 'foo',
                ),
                array(
                    'type' => 'TextBlock',
                    'text' => 'bar',
                ),
            ),
            'minHeight' => '42px',
            'requires' => array(
                'foo' => 1.2,
            ),
            'rtl?' => true,
            'selectAction' => array(
                'type' => 'Action.OpenUrl',
                'url' => 'http://example.com',
            ),
            'separator' => true,
            'spacing' => Enums::SPACING_DEFAULT,
            'style' => Enums::CONTAINER_STYLE_DEFAULT,
            'verticalContentAlignment' => Enums::VERTICAL_ALIGNMENT_TOP,
        ), $container->getContent(1.5));
    }

    public function testGetContentNoItems()
    {
        $container = new Container();
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Container items is empty');
        $container->getContent(1.2);
    }

    protected static function itemFactory()
    {
        return new Container();
    }

    protected static function withTestCases()
    {
        return array(
            // array('backgroundImage', ['http://example.com/cat.jpg']),
            // array('bleed', []),
            // array('bleed', ['true'], true, 'bleed must be bool. string provided'),
            // array('bleed', [(object) array()], true, 'bleed must be bool. stdClass provided'),
            array('items', [[new TextBlock('foo')]]),
            array('items', [['foo', 'bar']], true, 'Invalid container item found at index 0'),
            array('minHeight', ['42px']),
            // array('rtl', []),
            array('selectAction', [new OpenUrl('http://example.com')]),
            array('selectAction', [new ShowCard()], true, 'Container selectAction does not support ShowCard'),
            // array('separator', []),
            // array('style', [Enums::CONTAINER_STYLE_DEFAULT]),
            // array('verticalContentAlignment', [Enums::VERTICAL_ALIGNMENT_TOP]),
            //  AbstractToggleableItem
            array('id', ['123']),
            // AbstractExtendableItem
            // array('isVisible', []),
            array('requires', [['foo' => 123]]),
        );
    }
}

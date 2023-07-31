<?php

namespace bdk\Teams\Elements;

use bdk\Teams\AbstractItem;
use bdk\Teams\Actions\ActionInterface;
use bdk\Teams\Elements\ElementInterface;
use bdk\Teams\Enums;
use InvalidArgumentException;
use RuntimeException;

/**
 * Represents a cell within a row of a Table element.
 */
class TableCell extends AbstractItem
{
    protected $fields = array(
        'backgroundImage' => null,
        'bleed' => null,
        'items' => array(),
        'minHeight' => null,
        'rtl?' => null,
        'selectAction' => null,
        'style' => null,
        'verticalContentAlignment' => null,
    );

    /**
     * Constructor
     *
     * @param array<int, ElementInterface|string|numeric>|ElementInterface|string|numeric $items The card elements to render inside the Container,
     *                                                                            Or may pass in a single item
     */
    public function __construct($items = array())
    {
        $this->type = 'TableCell';
        if (\is_array($items) === false) {
            $items = array($items);
        }
        $this->fields['items'] = self::asItems($items);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        if ($this->fields['items'] === array()) {
            throw new RuntimeException('TableCell items is empty');
        }

        $attrVersions = array(
            'backgroundImage' => 1.1,
            'bleed' => 1.2,
            'items' => 1.5,
            'minHeight' => 1.2,
            'rtl?' => 1.5,
            'selectAction' => 1.1,
            'style' => 1.0,
            'verticalContentAlignment' => 1.2,
        );

        $content = array(
            'type' => $this->type,
        );
        foreach ($attrVersions as $name => $ver) {
            if ($version >= $ver) {
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
    }

    /**
     * Return new instance with added TableCell
     *
     * @param ElementInterface|string|numeric $item Item to append
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withAddedItem($item)
    {
        return $this->withAdded('items', self::asItem($item));
    }

    /**
     * Return new instance with given backgroundImage
     *
     * @param string                        $url                 Image url
     * @param Enums::FILLMODE_x             $fillmode            fill mode
     * @param Enums::HORIZONTAL_ALIGNMENT_x $horizontalAlignment horizontal alignment
     * @param Enums::VERTICAL_ALIGNMENT_x   $verticalAlignment   Vertical alignment
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withBackgroundImage($url, $fillmode = null, $horizontalAlignment = null, $verticalAlignment = null)
    {
        if ($url !== null) {
            self::assertUrl($url);
        }
        self::assertEnumValue($fillmode, 'FILLMODE_', 'fillmode');
        self::assertEnumValue($horizontalAlignment, 'HORIZONTAL_ALIGNMENT_', 'horizontalAlignment');
        self::assertEnumValue($verticalAlignment, 'VERTICAL_ALIGNMENT_', 'verticalAlignment');
        $backgroundImage = self::normalizeContent(array(
            'fillmode' => $fillmode,
            'horizontalAlignment' => $horizontalAlignment,
            'url' => $url,
            'verticalContentAlignment' => $verticalAlignment,
        ));
        return \count($backgroundImage) > 1
            ? $this->with('backgroundImage', $backgroundImage)
            : $this->with('backgroundImage', $url);
    }

    /**
     * Return new instance with specified bleed
     *
     * Determines whether the element should bleed through its parent’s padding.
     *
     * @param bool $bleed Whether element should bleed
     *
     * @return static
     */
    public function withBleed($bleed = true)
    {
        self::assertBool($bleed, 'bleed');
        return $this->with('bleed', $bleed);
    }

    /**
     * Return new instance with specified items
     *
     * @param array<int, ElementInterface|string|numeric> $items The card elements to render inside the TableCell
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withItems(array $items)
    {
        if ($items === array()) {
            throw new InvalidArgumentException(\sprintf(
                '%s - Items must be non-empty',
                __METHOD__
            ));
        }
        return $this->with('items', self::asItems($items));
    }

    /**
     * Return new instance with specified minHeight
     *
     * @param string $minHeight Specifies the minimum height of the container in pixels, like "80px"
     *
     * @return static
     */
    public function withMinHeight($minHeight)
    {
        self::assertPx($minHeight, __METHOD__);
        return $this->with('minHeight', $minHeight);
    }

    /**
     * Return new instance
     *
     * When true content in this container should be presented right to left.
     * When ‘false’ content in this container should be presented left to right.
     * When unset layout direction will inherit from parent container or column.
     * If unset in all ancestors, the default platform behavior will apply.
     *
     * @param bool $rtl RTL?
     *
     * @return static
     */
    public function withRtl($rtl = true)
    {
        self::assertBool($rtl, 'rtl');
        return $this->with('rtl?', $rtl);
    }

    /**
     * Return new instance with specified select action
     *
     * An Action that will be invoked when the Container is tapped or
     * selected.
     *
     * Action.ShowCard is not supported.
     *
     * @param ActionInterface|null $action Action
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withSelectAction(ActionInterface $action = null)
    {
        if ($action && $action->get('type') === 'Action.ShowCard') {
            throw new InvalidArgumentException('TableCell selectAction does not support ShowCard');
        }
        return $this->with('selectAction', $action);
    }

    /**
     * Return new instance with specified container style
     *
     * @param Enums::CONTAINER_STYLE_x $style Container style
     *
     * @return static
     */
    public function withStyle($style)
    {
        self::assertEnumValue($style, 'CONTAINER_STYLE_', 'style');
        return $this->with('style', $style);
    }

    /**
     * Return new instance with specified vertical alignment
     *
     * @param Enums::VERTICAL_ALIGNMENT_x $alignment Vertical alignment
     *
     * @return static
     */
    public function withVerticalContentAlignment($alignment)
    {
        self::assertEnumValue($alignment, 'VERTICAL_ALIGNMENT_', 'alignment');
        return $this->with('verticalContentAlignment', $alignment);
    }

    /**
     * Append ElementInterface, string, or numeric to items
     *
     * @param ElementInterface|string|numeric $item Item to append
     *
     * @return ElementInterface
     *
     * @throws InvalidArgumentException
     */
    private function asItem($item)
    {
        if ($item instanceof ElementInterface) {
            return $item;
        }
        if (\is_string($item) || \is_numeric($item)) {
            return (new TextBlock($item))
                ->withWrap();
        }
        if ($item === null) {
            return (new TextBlock('null'))
                ->withFontType(Enums::FONT_TYPE_MONOSPACE)
                ->withIsSubtle();
        }
        if ($item === true) {
            return (new TextBlock('true'))
                ->withFontType(Enums::FONT_TYPE_MONOSPACE)
                ->withColor(Enums::COLOR_GOOD);
        }
        if ($item === false) {
            return (new TextBlock('false'))
                ->withFontType(Enums::FONT_TYPE_MONOSPACE)
                ->withColor(Enums::COLOR_WARNING);
        }
        if (\is_object($item) && \method_exists($item, '__toString')) {
            return (new TextBlock((string) $item))
                ->withWrap();
        }
        throw new InvalidArgumentException(\sprintf(
            'Invalid TableCell item found. Expecting ElementInterface, stringable, scalar, or null. %s provided.',
            self::getDebugType($item)
        ));
    }

    /**
     * Assert each item is instance of ElementInterface, string, or numeric
     *
     * @param array<int, ElementInterface|string|numeric> $items Items to test
     *
     * @return ElementInterface[]
     *
     * @throws InvalidArgumentException
     */
    private function asItems(array $items)
    {
        try {
            foreach ($items as $i => $item) {
                $items[$i] = self::asItem($item);
            }
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(\sprintf(
                'Invalid TableCell item type (%s) found at index %s',
                self::getDebugType($item),
                $i
            ));
        }
        return $items;
    }
}

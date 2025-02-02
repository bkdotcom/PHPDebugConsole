<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Elements;

use bdk\Teams\AbstractItem;
use bdk\Teams\Actions\ActionInterface;
use bdk\Teams\Elements\CommonTrait;
use bdk\Teams\Elements\ElementInterface;
use bdk\Teams\Enums;
use InvalidArgumentException;
use RuntimeException;
use Traversable;

/**
 * Represents a cell within a row of a Table element.
 */
class TableCell extends AbstractItem
{
    use CommonTrait;

    /**
     * Constructor
     *
     * @param iterable<ElementInterface|\Stringable|scalar|null>|ElementInterface|\Stringable|scalar|null|mixed $items The card elements to render inside the Container,
     *                                                                            Or may pass in a single item
     */
    public function __construct($items = array())
    {
        if (\is_array($items) === false && !($items instanceof Traversable)) {
            $items = [$items];
        }
        parent::__construct(array(
            'backgroundImage' => null,
            'bleed' => null,
            'items' => self::asItems($items),
            'minHeight' => null,
            'rtl?' => null,
            'selectAction' => null,
            'style' => null,
            'verticalContentAlignment' => null,
        ), 'TableCell');
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
                /** @var mixed */
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
    }

    /**
     * Return new instance with added TableCell
     *
     * @param ElementInterface|\Stringable|scalar|null $item Item to append
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withAddedItem($item)
    {
        return $this->withAdded('items', self::asItem($item));
    }

    // withBackgroundImage

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
     * @param iterable<ElementInterface|\Stringable|scalar|null> $items The card elements to render inside the TableCell
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withItems($items)
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
    public function withSelectAction($action = null)
    {
        self::assertType($action, 'bdk\Teams\Actions\ActionInterface');

        if ($action && $action->get('type') === 'Action.ShowCard') {
            throw new InvalidArgumentException('TableCell selectAction does not support ShowCard');
        }
        return $this->with('selectAction', $action);
    }

    /**
     * Return new instance with specified container style
     *
     * @param Enums::CONTAINER_STYLE_* $style Container style
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
     * @param Enums::VERTICAL_ALIGNMENT_* $alignment Vertical alignment
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
     * @param ElementInterface|\Stringable|scalar|null|mixed $item  Item to append
     * @param array-key|null                                 $index Array index
     *
     * @return ElementInterface
     *
     * @throws InvalidArgumentException
     */
    private function asItem($item, $index = null)
    {
        switch (true) {
            case $item instanceof ElementInterface:
                return $item;
            case \is_string($item) || (\is_object($item) && \method_exists($item, '__toString')):
                return (new TextBlock((string) $item))
                    ->withWrap();
            case \is_numeric($item):
                return new TextBlock($item);
            case \is_bool($item):
                return (new TextBlock(\json_encode($item)))
                    ->withFontType(Enums::FONT_TYPE_MONOSPACE)
                    ->withColor($item ? Enums::COLOR_GOOD : Enums::COLOR_WARNING);
            case $item === null:
                return (new TextBlock('null'))
                    ->withFontType(Enums::FONT_TYPE_MONOSPACE)
                    ->withIsSubtle();
        }
        throw new InvalidArgumentException(\sprintf(
            'Invalid TableCell item found%s. Expecting ElementInterface, stringable, scalar, or null. %s provided.',
            $index !== null
                ? ' at index ' . $index
                : '',
            self::getDebugType($item)
        ));
    }

    /**
     * Assert each item is instance of ElementInterface, string, or numeric
     *
     * @param iterable<mixed> $items Items to test
     *
     * @return list<ElementInterface>
     *
     * @throws InvalidArgumentException
     */
    private function asItems($items)
    {
        $itemsNew = [];
        /**
         * @var array-key $i
         * @var mixed     $item
         */
        foreach ($items as $i => $item) {
            $itemsNew[] = self::asItem($item, $i);
        }
        return $itemsNew;
    }
}

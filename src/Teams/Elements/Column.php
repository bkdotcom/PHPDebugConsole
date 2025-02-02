<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Elements;

use bdk\Teams\Actions\ActionInterface;
use bdk\Teams\Elements\CommonTrait;
use bdk\Teams\Enums;
use InvalidArgumentException;
use RuntimeException;

/**
 * Defines a container that is part of a ColumnSet
 */
class Column extends AbstractToggleableItem implements ElementInterface
{
    use CommonTrait;

    /**
     * Constructor
     *
     * @param ElementInterface[] $items he card elements to render inside the Column.
     */
    public function __construct(array $items = array())
    {
        self::assertItems($items);
        parent::__construct(array(
            'backgroundImage' => null,
            'bleed' => null,
            'fallback' => null,
            'items' => $items,
            'minHeight' => null,
            'rtl' => null,
            'selectAction' => null,
            'separator' => null,
            'spacing' => null,
            'style' => null,
            'verticalContentAlignment' => null,
            'width' => null,
        ), 'Column');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        if ($this->fields['items'] === array()) {
            throw new RuntimeException('Column items is empty');
        }

        $attrVersions = array(
            'backgroundImage' => 1.1,
            'bleed' => 1.2,
            'fallback' => 1.2,
            'items' => 1.0,
            'minHeight' => 1.2,
            'rtl' => 1.5,
            'selectAction' => 1.1,
            'separator' => 1.1,
            'spacing' => 1.0,
            'style' => 1.0,
            'verticalContentAlignment' => 1.2,
            'width' => 1.0,
        );

        $content = parent::getContent($version);
        foreach ($attrVersions as $name => $ver) {
            if ($version >= $ver) {
                /** @var mixed */
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
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
     * Describes what to do when an unknown element is encountered
     * or the requires of this or any children can't be met.
     *
     * @param Column|Enums::FALLBACK_* $fallback How to we fallback?
     *
     * @return static
     */
    public function withFallback($fallback)
    {
        self::assertFallback(
            $fallback,
            'bdk\\Teams\\Elements\\Column',
            $this->type . ' fallback should be instance of Column or one of Enum::FALLBACK_x values'
        );
        return $this->with('fallback', $fallback);
    }

    /**
     * Return new instance with specified items
     *
     * @param ElementInterface[] $items The card elements to render inside the Container
     *
     * @return static
     */
    public function withItems(array $items = array())
    {
        self::assertItems($items);
        return $this->with('items', $items);
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
        return $this->with('rtl', $rtl);
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
            throw new InvalidArgumentException('Column selectAction does not support ShowCard');
        }
        return $this->with('selectAction', $action);
    }

    /**
     * Return new instance with specified separator value
     * When true, draw a separating line between this column and the previous column.
     *
     * @param bool $separator Add separating line?
     *
     * @return static
     */
    public function withSeparator($separator = true)
    {
        self::assertBool($separator, 'separator');
        return $this->with('separator', $separator);
    }

    /**
     * Return new instance with specified spacing value
     *
     * Controls the amount of spacing between this column and the preceding column.
     *
     * @param Enums::SPACING_* $spacing Spacing
     *
     * @return static
     */
    public function withSpacing($spacing)
    {
        self::assertEnumValue($spacing, 'SPACING_', 'spacing');
        return $this->with('spacing', $spacing);
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
     * "auto", "stretch", a number representing relative width of the column in the column group,
     * or in version 1.1 and higher, a specific pixel width, like "50px"
     *
     * @param string $width desired on-screen width ending in 'px'. E.g., 50px.
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withWidth($width)
    {
        self::assertWidth($width, __METHOD__);
        return $this->with('width', $width);
    }

    /**
     * Assert each item is instance of ElementInterface
     *
     * @param ElementInterface[] $items Items to test
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *
     * @psalm-assert ElementInterface[] $items
     */
    private static function assertItems(array $items)
    {
        foreach ($items as $i => $item) {
            if ($item instanceof ElementInterface) {
                continue;
            }
            throw new InvalidArgumentException(\sprintf(
                'Invalid column item found at index %s',
                $i
            ));
        }
    }

    /**
     * Assert valid width value
     *
     * @param string|Enums::COLUMN_WIDTH_*|numeric $val    value to test
     * @param string                               $method calling method
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private static function assertWidth($val, $method)
    {
        $tests = [
            static function ($val) {
                return $val === null;
            },
            static function ($val) {
                // ver 1.1
                self::assertPx($val);
            },
            static function ($val) {
                self::assertEnumValue($val, 'COLUMN_WIDTH_', 'width');
            },
            static function ($val) {
                $isStrOrNum = \is_string($val) || \is_numeric($val);
                return $isStrOrNum && \preg_match('/^\d+(.\d+)?$/', (string) $val) === 1;
            },
        ];
        $message = $method . ' - width should be one of the Enums::COLUMN_WIDTH_x constants, number representing relative width, or pixel value';
        self::assertAnyOf($val, $tests, $message);
    }
}

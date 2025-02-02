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
 * Containers group items together.
 */
class Container extends AbstractElement
{
    use CommonTrait;

    /**
     * Constructor
     *
     * @param ElementInterface[] $items The card elements to render inside the Container
     */
    public function __construct(array $items = array())
    {
        self::assertItems($items);
        parent::__construct(array(
            'backgroundImage' => null,
            'bleed' => null,
            'items' => $items,
            'minHeight' => null,
            'rtl?' => null,
            'selectAction' => null,
            'style' => null,
            'verticalContentAlignment' => null,
        ), 'Container');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        if ($this->fields['items'] === array()) {
            throw new RuntimeException('Container items is empty');
        }

        $attrVersions = array(
            'backgroundImage' => 1.1,
            'bleed' => 1.2,
            'items' => 1.0,
            'minHeight' => 1.2,
            'rtl?' => 1.5,
            'selectAction' => 1.1,
            'style' => 1.0,
            'verticalContentAlignment' => 1.2,
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
     * Return new instance with specified items
     *
     * @param ElementInterface[] $items The card elements to render inside the Container
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
            throw new InvalidArgumentException('Container selectAction does not support ShowCard');
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
     * Assert each value is instance of ElementInterface
     *
     * @param ElementInterface[] $items values to validate
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private static function assertItems(array $items)
    {
        foreach ($items as $i => $item) {
            if ($item instanceof ElementInterface) {
                continue;
            }
            throw new InvalidArgumentException(\sprintf(
                'Invalid container item found at index %s',
                $i
            ));
        }
    }
}

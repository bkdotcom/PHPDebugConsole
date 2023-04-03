<?php

declare(strict_types=1);

namespace bdk\Teams\Elements;

use bdk\Teams\Actions\ActionInterface;
use bdk\Teams\Enums;
use InvalidArgumentException;
use RuntimeException;

/**
 * Containers group items together.
 */
class Container extends AbstractElement
{
    /**
     * Constructor
     *
     * @param ElementInterface[] $items The card elements to render inside the Container
     */
    public function __construct(array $items = array())
    {
        self::assertItems($items);
        parent::__construct();
        $this->type = 'Container';
        $this->fields = \array_merge($this->fields, array(
            'backgroundImage' => null,
            'bleed' => null,
            'items' => $items,
            'minHeight' => null,
            'rtl?' => null,
            'selectAction' => null,
            'style' => null,
            'verticalContentAlignment' => null,
        ));
    }

    /**
     * Returns content of card element
     *
     * @param float $version Card version
     *
     * @return array
     *
     * @throws RuntimeException
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
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
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
     * @param bool $bleed [description]
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
     * @param ActionInterface|null $action [description]
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withSelectAction(ActionInterface $action = null)
    {
        if ($action && $action->get('type') === 'Action.ShowCard') {
            throw new InvalidArgumentException('Container selectAction does not support ShowCard');
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
     * Assert each valis is instance of ElementInterface
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
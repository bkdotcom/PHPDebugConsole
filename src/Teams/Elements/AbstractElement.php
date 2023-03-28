<?php

declare(strict_types=1);

namespace bdk\Teams\Elements;

use bdk\Teams\Enums;

/**
 * aka Extendable.Element
 */
abstract class AbstractElement extends AbstractToggleableItem implements ElementInterface
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->fields = \array_merge($this->fields, array(
            'fallback' => null,
            'height' => null,
            'separator' => null,
            'spacing' => null,
        ));
    }

    /**
     * Get base / common properties
     *
     * @param float $version Card version
     *
     * @return array
     */
    public function getContent($version)
    {
        $attrVersions = array(
            'fallback' => 1.1,
            'height' => 1.1,
            'separator' => 1.0,
            'spacing' => 1.0,
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
     * Describes what to do when an unknown element is encountered
     * or the requires of this or any children can't be met.
     *
     * @param ElementInterface|Enums::FALLBACK_x $fallback How to we fallback?
     *
     * @return static
     */
    public function withFallback($fallback)
    {
        self::assertFallback(
            $fallback,
            'bdk\\Teams\\Elements\\ElementInterface',
            $this->type . ' fallback should be instance of ElementInterface or one of Enum::FALLBACK_x values'
        );
        return $this->with('fallback', $fallback);
    }

    /**
     * Return new instance with specified height
     *
     * @param Enums::HEIGHT_x $height Height of the element.
     *
     * @return static
     */
    public function withHeight($height)
    {
        self::assertEnumValue($height, 'HEIGHT_', 'height');
        return $this->with('height', $height);
    }

    /**
     * Return new instance with specified separator value
     * When true, draw a separating line at the top of the element.
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
     * Controls the amount of spacing between this element and the preceding element.
     *
     * @param Enums::SPACING_x $spacing Spacing
     *
     * @return static
     */
    public function withSpacing($spacing)
    {
        self::assertEnumValue($spacing, 'SPACING_', 'spacing');
        return $this->with('spacing', $spacing);
    }
}

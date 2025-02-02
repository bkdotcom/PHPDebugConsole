<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Elements;

use bdk\Teams\Enums;
use InvalidArgumentException;
use RuntimeException;

/**
 * Text block element
 *
 * @see https://adaptivecards.io/explorer/TextBlock.html
 */
class TextBlock extends AbstractElement
{
    /**
     * Constructor
     *
     * @param string|numeric $text Text to display
     */
    public function __construct($text = null)
    {
        parent::__construct(array(
            'color' => null,
            'fontType' => null,
            'horizontalAlignment' => null,
            'isSubtle' => null,
            'maxLines' => null,
            'size' => null,
            'style' => null,
            'text' => self::asString($text, true, __METHOD__),
            'weight' => null,
            'wrap' => null,
        ), 'TextBlock');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        if ($this->fields['text'] === null) {
            throw new RuntimeException('TextBlock text is required');
        }

        $attrVersions = array(
            'color' => 1.0,
            'fontType' => 1.2,
            'horizontalAlignment' => 1.0,
            'isSubtle' => 1.0,
            'maxLines' => 1.0,
            'size' => 1.0,
            'style' => 1.0,
            'text' => 1.0,
            'weight' => 1.0,
            'wrap' => 1.0,
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

    /**
     * Returns a new instance with specified text to display
     * A subset of markdown is supported (https://aka.ms/ACTextFeatures)
     *
     * @param string|numeric $text Text to display
     *
     * @return static
     */
    public function withText($text)
    {
        $text = self::asString($text, false, __METHOD__);
        return $this->with('text', $text);
    }

    /**
     * Sets color.
     *
     * @param Enums::COLOR_* $color Color enum
     *
     * @return static
     */
    public function withColor($color)
    {
        self::assertEnumValue($color, 'COLOR_', 'color');
        return $this->with('color', $color);
    }

    /**
     * Sets font type.
     *
     * @param Enums::FONT_TYPE_* $fontType Type of font to use for rendering
     *
     * @return static
     */
    public function withFontType($fontType)
    {
        self::assertEnumValue($fontType, 'FONT_TYPE_', 'fontType');
        return $this->with('fontType', $fontType);
    }

    /**
     * Return new instance with specified horizontal alignment
     *
     * Controls the horizontal text alignment.
     * When not specified, the value of horizontalAlignment
     * is inherited from the parent container.
     * If no parent container has horizontalAlignment set, it defaults to Left.
     *
     * @param Enums::HORIZONTAL_ALIGNMENT_* $alignment Horizontal alignment
     *
     * @return static
     */
    public function withHorizontalAlignment($alignment)
    {
        self::assertEnumValue($alignment, 'HORIZONTAL_ALIGNMENT_', 'alignment');
        return $this->with('horizontalAlignment', $alignment);
    }

    /**
     * Sets isSubtle flag
     *
     * @param bool $isSubtle Subtle?
     *
     * @return TextBlock
     */
    public function withIsSubtle($isSubtle = true)
    {
        self::assertBool($isSubtle, 'isSubtle');
        return $this->with('isSubtle', $isSubtle);
    }

    /**
     * Sets max lines
     *
     * @param int|null $maxLines Maximum lines
     *
     * @return TextBlock
     *
     * @throws InvalidArgumentException
     */
    public function withMaxLines($maxLines)
    {
        $isValid = \is_int($maxLines) || $maxLines === null;
        if ($isValid === false) {
            throw new InvalidArgumentException(\sprintf(
                'withMaxLines expects int or null. %s provided.',
                self::getDebugType($maxLines)
            ));
        }
        if ($maxLines < 1) {
            $maxLines = null;
        }
        return $this->with('maxLines', $maxLines);
    }

    /**
     * Sets font size
     *
     * @param Enums::FONT_SIZE_* $size Font size enum
     *
     * @return static
     */
    public function withSize($size)
    {
        self::assertEnumValue($size, 'FONT_SIZE_', 'size');
        return $this->with('size', $size);
    }

    /**
     * Sets Text block style
     *
     * @param Enums::TEXTBLOCK_STYLE_* $style Controls how a TextBlock behaves
     *
     * @return static
     */
    public function withStyle($style)
    {
        self::assertEnumValue($style, 'TEXTBLOCK_STYLE_', 'style');
        return $this->with('style', $style);
    }

    /**
     * Sets font weight.
     *
     * @param Enums::FONT_WEIGHT_* $weight Font weight enum
     *
     * @return static
     */
    public function withWeight($weight)
    {
        self::assertEnumValue($weight, 'FONT_WEIGHT_', 'weight');
        return $this->with('weight', $weight);
    }

    /**
     * Return new instance with specified wrap
     * If true, allow text to wrap. Otherwise, text is clipped.
     *
     * @param bool $wrap Wrap text?
     *
     * @return static
     */
    public function withWrap($wrap = true)
    {
        self::assertBool($wrap, 'wrap');
        return $this->with('wrap', $wrap);
    }
}

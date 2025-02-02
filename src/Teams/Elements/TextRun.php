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
use bdk\Teams\Enums;
use InvalidArgumentException;
use RuntimeException;

/**
 * TextRun element
 *
 * @since v1.2
 *
 * @see https://adaptivecards.io/explorer/TextRun.html
 */
class TextRun extends AbstractItem implements ElementInterface
{
    /**
     * Constructor
     *
     * @param string $text Text to display
     */
    public function __construct($text = null)
    {
        parent::__construct(array(
            'color' => null,
            'fontType' => null,
            'highlight' => null,
            'isSubtle' => null,
            'italic' => null,
            'selectAction' => null,
            'size' => null,
            'strikethrough' => null,
            'text' => $text,
            'underline' => null,
            'weight' => null,
        ), 'TextRun');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        if ($this->fields['text'] === null) {
            throw new RuntimeException('TextRun text is required');
        }

        $attrVersions = array(
            'color' => 1.2,
            'fontType' => 1.2,
            'highlight' => 1.2,
            'isSubtle' => 1.2,
            'italic' => 1.2,
            'selectAction' => 1.2,
            'size' => 1.2,
            'strikethrough' => 1.2,
            'text' => 1.2,
            'underline' => 1.3,
            'weight' => 1.2,
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
     * Text to display.
     * A subset of markdown is supported (https://aka.ms/ACTextFeatures)
     *
     * @param string $text Text to display
     *
     * @return static
     */
    public function withText($text)
    {
        $text = self::asString($text, false, __METHOD__);
        return $this->with('text', $text);
    }

    /**
     * Controls the color of TextRun elements.
     *
     * @param Enums::COLOR_* $color Color
     *
     * @return static
     */
    public function withColor($color)
    {
        self::assertEnumValue($color, 'COLOR_', 'color');
        return $this->with('color', $color);
    }

    /**
     * Type of font to use for rendering
     *
     * @param Enums::FONT_TYPE_* $fontType Font type
     *
     * @return static
     */
    public function withFontType($fontType)
    {
        self::assertEnumValue($fontType, 'FONT_TYPE_', 'fontType');
        return $this->with('fontType', $fontType);
    }

    /**
     * Sets highlight flag
     *
     * @param bool $highlight Highlight?
     *
     * @return static
     */
    public function withHighlight($highlight = true)
    {
        self::assertBool($highlight, 'highlight');
        return $this->with('highlight', $highlight);
    }

    /**
     * If true, displays text slightly toned down to appear less prominent.
     *
     * @param bool $isSubtle Subtle?
     *
     * @return static
     */
    public function withIsSubtle($isSubtle = true)
    {
        self::assertBool($isSubtle, 'isSubtle');
        return $this->with('isSubtle', $isSubtle);
    }

    /**
     * If true, displays the text using italic font.
     *
     * @param bool $italic Italic?
     *
     * @return static
     */
    public function withItalic($italic = true)
    {
        self::assertBool($italic, 'italic');
        return $this->with('italic', $italic);
    }

    /**
     * An Action that will be invoked when the "Image" is tapped or selected.
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
            throw new InvalidArgumentException('TextRun selectAction does not support ShowCard');
        }
        return $this->with('selectAction', $action);
    }

    /**
     * Set font size.
     *
     * @param Enums::FONT_SIZE_* $size Font size
     *
     * @return static
     */
    public function withSize($size)
    {
        self::assertEnumValue($size, 'FONT_SIZE_', 'size');
        return $this->with('size', $size);
    }

    /**
     * If true, displays the text with strikethrough.
     *
     * @param bool $strikethrough Strikethrough?
     *
     * @return static
     */
    public function withStrikethrough($strikethrough = true)
    {
        self::assertBool($strikethrough, 'strikethrough');
        return $this->with('strikethrough', $strikethrough);
    }

    /**
     * If true, displays the text with an underline.
     *
     * @param bool $underline Underline?
     *
     * @return static
     */
    public function withUnderline($underline = true)
    {
        self::assertBool($underline, 'underline');
        return $this->with('underline', $underline);
    }

    /**
     * Controls the weight of TextRun elements.
     *
     * @param Enums::FONT_WEIGHT_* $weight Font weight
     *
     * @return static
     */
    public function withWeight($weight)
    {
        self::assertEnumValue($weight, 'FONT_WEIGHT_', 'weight');
        return $this->with('weight', $weight);
    }
}

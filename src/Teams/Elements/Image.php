<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Elements;

use bdk\Teams\Actions\ActionInterface;
use bdk\Teams\Enums;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Image element
 *
 * @see https://adaptivecards.io/explorer/Image.html
 */
class Image extends AbstractElement
{
    /**
     * Constructor
     *
     * @param string|UriInterface $url Image url
     */
    public function __construct($url = null)
    {
        if ($url !== null) {
            self::assertUrl($url, true);
        }
        parent::__construct(array(
            'altText' => null,
            'backgroundColor' => null,
            'height' => null,
            'horizontalAlignment' => null,
            'selectAction' => null,
            'size' => null,
            'style' => null,
            'url' => $url ? (string) $url : null,
            'width' => null,
        ), 'Image');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        if ($this->fields['url'] === null) {
            throw new RuntimeException('Image url is required');
        }

        $attrVersions = array(
            'altText' => 1.0,
            'backgroundColor' => 1.1,
            'height' => 1.1,
            'horizontalAlignment' => 1.0,
            'selectAction' => 1.1,
            'size' => 1.0,
            'style' => 1.0,
            'url' => 1.0,
            'width' => 1.1,
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
     * Sets Url
     * The URL to the image. Supports data URI in version 1.2+
     *
     * @param string|UriInterface $url Url
     *
     * @return static
     */
    public function withUrl($url)
    {
        self::assertUrl($url, true);
        return $this->with('url', (string) $url);
    }

    /**
     * Return new instance with the provided altText value
     *
     * @param string $altText Alternate text describing the image.
     *
     * @return static
     */
    public function withAltText($altText)
    {
        $altText = self::asString($altText, true, __METHOD__);
        return $this->with('altText', $altText);
    }

    /**
     * Return a new instance with the provided backgroundColor
     *
     * Applies a background to a transparent image.
     * This property will respect the image style.
     *
     * @param string|null $backgroundColor hexadecimal color
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withBackgroundColor($backgroundColor)
    {
        $isValid = false;
        if ($backgroundColor === null) {
            $isValid = true;
        } elseif (\is_string($backgroundColor) && \preg_match('/^#[a-f0-9]{3,6}$/i', $backgroundColor) === 1) {
            $isValid = true;
        }
        if ($isValid === false) {
            throw new InvalidArgumentException('Invalid backgroundColor. Expecting hexadecimal');
        }
        return $this->with('backgroundColor', $backgroundColor);
    }

    /**
     * Return a new instance with the provided height
     *
     * If specified as a pixel value, ending in ‘px’, E.g., 50px,
     * the image will distort to fit that exact height.
     * This overrides the size property.
     *
     * @param Enums::HEIGHT_* $height "auto"|"stretch"
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withHeight($height)
    {
        $tests = [
            static function ($val) {
                self::assertPx($val, __METHOD__, 'height');
            },
            static function ($val) {
                self::assertEnumValue($val, 'HEIGHT_', 'height');
            },
        ];
        $isValid = false;
        foreach ($tests as $callable) {
            try {
                $callable($height);
                $isValid = true;
                break;
            } catch (InvalidArgumentException $e) {
                $isValid = false;
            }
        }
        if ($isValid === false) {
            throw new InvalidArgumentException(__METHOD__ . ' - height should be one of the Enums::HEIGHT_x constants or pixel value');
        }
        return $this->with('height', $height);
    }

    /**
     * Sets horizontal alignment.
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
     * Return new instance with the provided size
     *
     * Controls the approximate size of the image.
     * The physical dimensions will vary per host.
     *
     * @param Enums::IMAGE_SIZE_* $size Image size
     *
     * @return static
     */
    public function withSize($size)
    {
        self::assertEnumValue($size, 'IMAGE_SIZE_', 'size');
        return $this->with('size', $size);
    }

    /**
     * Return new instance with the provided "selectAction"
     *
     * An Action that will be invoked when the "Image" is tapped or selected.
     * "Action.ShowCard" is not supported.
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
            throw new InvalidArgumentException('Image selectAction does not support ShowCard');
        }
        return $this->with('selectAction', $action);
    }

    /**
     * Sets image style.
     *
     * @param Enums::IMAGE_STYLE_* $style Image style
     *
     * @return static
     */
    public function withStyle($style)
    {
        self::assertEnumValue($style, 'IMAGE_STYLE_', 'style');
        return $this->with('style', $style);
    }

    /**
     * The desired on-screen width of the image, ending in ‘px’. E.g., 50px.
     * This overrides the size property.
     *
     * @param string $width desired on-screen width ending in 'px'. E.g., 50px.
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withWidth($width)
    {
        self::assertPx($width, __METHOD__);
        return $this->with('width', $width);
    }
}

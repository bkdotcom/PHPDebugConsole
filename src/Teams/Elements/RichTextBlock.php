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
 * RichTextBlock element
 *
 * @since v1.2
 *
 * @see https://adaptivecards.io/explorer/RichTextBlock.html
 */
class RichTextBlock extends AbstractElement
{
    /**
     * Constructor
     *
     * @param array<int,string|TextRun> $inlines Initial inline elements
     */
    public function __construct(array $inlines = array())
    {
        \array_walk($inlines, static function ($inline) {
            self::assertInline($inline);
        });
        parent::__construct(array(
            'horizontalAlignment' => null,
            'inlines' => $inlines,
        ), 'RichTextBlock');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        if ($this->fields['inlines'] === array()) {
            throw new RuntimeException('Card element inlines empty');
        }

        $attrVersions = array(
            'horizontalAlignment' => 1.2,
            'inlines' => 1.2,
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
     * Adds text to inlines
     *
     * @param string $text Text to add
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withAddedText($text)
    {
        $text = self::asString($text, false, __METHOD__);
        return $this->withAdded('inlines', $text);
    }

    /**
     * Adds TextRun object to inlines
     *
     * @param TextRun $textRun TextRun instance
     *
     * @return static
     */
    public function withAddedTextRun(TextRun $textRun)
    {
        return $this->withAdded('inlines', $textRun);
    }

    /**
     * Sets inlines (replacing existing)
     *
     * @param array<int,string|TextRun> $inlines Inline elements
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withInlines(array $inlines)
    {
        if ($inlines === array()) {
            throw new InvalidArgumentException(\sprintf(
                '%s - Inlines must be non-empty',
                __METHOD__
            ));
        }
        \array_walk($inlines, static function ($inline) {
            self::assertInline($inline);
        });
        return $this->with('inlines', $inlines);
    }

    /**
     * Sets horizontal alignment.
     *
     * Controls the horizontal text alignment.
     * When not specified, the value of horizontalAlignment is inherited from the parent container.
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
     * Assert valid inline
     *
     * @param mixed $val Value to test
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private static function assertInline($val)
    {
        $isValid = $val instanceof TextRun || \is_string($val);
        if ($isValid) {
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            'Inline must be TextRun or string. %s provided.',
            \is_object($val) ? \get_class($val) : \gettype($val)
        ));
    }
}

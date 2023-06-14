<?php

namespace bdk\Teams\Elements;

use bdk\Teams\Enums;
use InvalidArgumentException;
use RuntimeException;

/**
 * RichTextBlock element
 *
 * @version >= 1.2
 *
 * @see https://adaptivecards.io/explorer/RichTextBlock.html
 */
class RichTextBlock extends AbstractElement
{
    /**
     * Constructor
     *
     * @param array<int, string|TextRun> $inlines Initial inline elements
     */
    public function __construct($inlines = array())
    {
        parent::__construct();

        $this->type = 'RichTextBlock';
        $this->fields = \array_merge($this->fields, array(
            'horizontalAlignment' => null,
            'inlines' => array(),
        ));

        foreach ($inlines as $inline) {
            self::assertInline($inline);
            $this->fields['inlines'][] = $inline;
        }
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
     * @param array<int, string|TextRun> $inlines Inline elements
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
        \array_map(static function ($inline) {
            self::assertInline($inline);
        }, $inlines);
        return $this->with('inlines', $inlines);
    }

    /**
     * Sets horizontal alignment.
     *
     * Controls the horizontal text alignment.
     * When not specified, the value of horizontalAlignment is inherited from the parent container.
     * If no parent container has horizontalAlignment set, it defaults to Left.
     *
     * @param Enums::HORIZONTAL_ALIGNMENT_x $alignment Horizontal alignment
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
            \gettype($val)
        ));
    }
}

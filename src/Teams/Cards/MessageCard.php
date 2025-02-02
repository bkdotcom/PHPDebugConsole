<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Cards;

use bdk\Teams\Section;
use Psr\Http\Message\UriInterface;

/**
 * MessageCard
 *
 * @see https://learn.microsoft.com/en-us/microsoftteams/platform/task-modules-and-cards/cards/cards-reference#connector-card-for-microsoft-365-groups
 * @see https://learn.microsoft.com/en-us/outlook/actionable-messages/message-card-reference
 */
class MessageCard extends AbstractCard
{
    /**
     * Constructor
     *
     * @param string $title Card title
     * @param string $text  Card text
     */
    public function __construct($title = null, $text = null)
    {
        parent::__construct(array(
            'potentialAction' => array(),
            'sections' => array(),
            'summary' => null,
            'text' => self::asString($text, true, __METHOD__),
            'themeColor' => null,
            'title' => self::asString($title, true, __METHOD__),
        ), 'MessageCard');
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage()
    {
        return self::normalizeContent(\array_merge(array(
            '@context' => 'http://schema.org/extensions',
            '@type' => $this->type,
        ), $this->fields));
    }

    /**
     * Add a new section to the card
     *
     * @param Section $section Section instance
     *
     * @return static
     */
    public function withAddedSection(Section $section)
    {
        return $this->withAdded('sections', $section);
    }

    /**
     * Adds action button to card
     *
     * "ViewAction" (only documented via example https://learn.microsoft.com/en-us/microsoftteams/platform/task-modules-and-cards/cards/cards-reference#example-of-an-connector-card-for-microsoft-365-groups)
     * "OpenUri"
     * "HttpPOST"
     * "ActionCard"
     *
     * @param string              $name Action name
     * @param string|UriInterface $url  Action Url
     *
     * @return static
     *
     * @see https://learn.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#actions
     */
    public function withAction($name, $url)
    {
        $name = self::asString($name, false, __METHOD__);
        self::assertUrl($url);
        return $this->withAdded('potentialAction', array(
            '@context' => 'http://schema.org',
            '@type' => 'ViewAction',
            'name' => $name,
            'target' => [(string) $url],
        ));
    }

    /**
     * Adds activity section to card
     *
     * @param string|null $title    Activity title
     * @param string|null $subtitle Subtitle
     * @param string|null $text     Text
     * @param string|null $image    Image url
     *
     * @return static
     */
    public function withActivity($title = null, $subtitle = null, $text = null, $image = null)
    {
        return $this->withAdded('sections', (new Section())
            ->withActivity($title, $subtitle, $text, $image));
    }

    /**
     * Return new instance with specified color
     *
     * Don't use themeColor to indicate status
     *
     * @param string $color hex color
     *
     * @return static
     */
    public function withColor($color)
    {
        return $this->with('themeColor', $color);
    }

    /**
     * Add a new section consisting of a hero image
     *
     * @param string|UriInterface $url   Image url
     * @param string              $title A short description of the image
     *                                     (typically displayed as a tooltip)
     *
     * @return static
     */
    public function withHeroImage($url, $title = null)
    {
        self::assertUrl($url);
        return $this->withAdded('sections', array(
            'heroImage' => array(
                'image' => (string) $url,
                'title' => $title,
            ),
        ));
    }

    /**
     * Adds single image to card
     *
     * @param string $imageUrl Image url
     * @param string $title    Image title
     *
     * @return static
     */
    public function withImage($imageUrl, $title = null)
    {
        return $this->withImages([$imageUrl], $title);
    }

    /**
     * Adds images section to card
     *
     * @param string[] $images Image urls
     * @param string   $title  Title
     *
     * @return static
     */
    public function withImages(array $images, $title = null)
    {
        return $this->withAdded('sections', (new Section())
            ->withTitle($title)
            ->withImages($images));
    }

    /**
     * Sets Card Summary
     *
     * @param string $summary "what the card is all about"
     *
     * @return static
     */
    public function withSummary($summary)
    {
        return $this->with('summary', $summary);
    }

    /**
     * Sets Card Text
     *
     * @param string $text Card text
     *
     * @return static
     */
    public function withText($text)
    {
        $text = self::asString($text, true, __METHOD__);
        return $this->with('text', $text);
    }

    /**
     * Sets Card Title
     *
     * @param string $title Card title
     *
     * @return static
     */
    public function withTitle($title)
    {
        $title = self::asString($title, true, __METHOD__);
        return $this->with('title', $title);
    }

    /**
     * Adds facts section to card
     *
     * @param array<string,mixed> $facts name => value array
     * @param string|null         $title title for this facts section
     *
     * @return static
     */
    public function withFacts(array $facts, $title = null)
    {
        return $this->withAdded('sections', (new Section())
            ->withTitle($title)
            ->withFacts($facts));
    }
}

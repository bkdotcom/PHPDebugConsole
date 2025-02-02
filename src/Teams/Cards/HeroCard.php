<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Cards;

use bdk\Teams\Actions\ActionInterface;
use Psr\Http\Message\UriInterface;

/**
 * Hero card
 *
 * @see https://docs.microsoft.com/en-us/microsoftteams/platform/task-modules-and-cards/cards/cards-reference#hero-card
 */
class HeroCard extends AbstractCard
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(array(
            'buttons' => array(),
            'images' => array(),
            'subtitle' => null,
            'tap' => null,
            'text' => null,
            'title' => null,
        ), 'HeroCard');
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage()
    {
        // @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        return array(
            'type' => 'message',
            'attachments' => array(
                'contentType' => 'application/vnd.microsoft.card.hero',
                'content' => self::normalizeContent($this->fields),
            ),
        );
        // @phpcs:enable
    }

    /**
     * Adds single button to card
     *
     * Set of actions applicable to the current card. Maximum 6
     *
     * @param string $type  Button type
     * @param string $title Button title
     * @param string $value Button value
     *
     * @return static
     *
     * @see https://learn.microsoft.com/en-us/microsoftteams/platform/task-modules-and-cards/cards/cards-actions?tabs=json
     */
    public function withAddedButton($type, $title, $value)
    {
        self::assertEnumValue($type, 'ACTION_TYPE_', 'type');
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        return $this->withAdded('buttons', array(
            'type' => $type,
            'title' => $title,
            'value' => $value,
        ));
    }

    /**
     * Adds single image to card
     *
     * Image(s) displayed at top of card. Aspect ratio 16:9.
     * Currently only the first image of the array will be shown in teams
     *
     * @param string|UriInterface $url Image url
     *
     * @return static
     */
    public function withAddedImage($url)
    {
        self::assertUrl($url);
        return $this->withAdded('images', array(
            'url' => (string) $url,
        ));
    }

    /**
     * Sets card tap
     *
     * @param ActionInterface $action Action to take when clicking on card
     *
     * @return static
     */
    public function withTap(ActionInterface $action)
    {
        return $this->with('tap', $action);
    }

    /**
     * Sets card text
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
     * Sets card title
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
     * Sets card subtitle
     *
     * @param string|null $subtitle Card subtitle
     *
     * @return static
     */
    public function withSubtitle($subtitle)
    {
        $subtitle = self::asString($subtitle, true, __METHOD__);
        return $this->with('subtitle', $subtitle);
    }
}

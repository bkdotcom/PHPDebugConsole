<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams;

use bdk\Teams\AbstractItem;
use bdk\Teams\Actions\ActionInterface;
use InvalidArgumentException;
use OverflowException;
use Psr\Http\Message\UriInterface;

/**
 * MessageCard Section
 *
 * @see https://learn.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#section-fields
 */
class Section extends AbstractItem
{
    /** @var array<string,mixed> */
    protected $fields = array(
        'activityImage' => null,
        'activitySubtitle' => null,
        'activityText' => null,
        'activityTitle' => null,
        'facts' => array(),
        'heroImage' => null,
        'images' => array(),
        'potentialAction' => null,
        'startGroup' => null,
        'text' => null,
        'title' => null,
    );

    /**
     * {@inheritDoc}
     */
    public function getContent($version = null)
    {
        return self::normalizeContent($this->fields);
    }

    /**
     * Return a new instance with specified activity values
     *
     * @param string              $title    Title
     * @param string              $subtitle Subtitle
     * @param string              $text     Text
     * @param string|UriInterface $image    Image url
     *
     * @return static
     */
    public function withActivity($title = null, $subtitle = null, $text = null, $image = null)
    {
        $title = self::asString($title, true, __METHOD__, 'title');
        $subtitle = self::asString($subtitle, true, __METHOD__, 'subtitle');
        $text = self::asString($text, true, __METHOD__, 'text');
        if ($image !== null) {
            self::assertUrl($image);
        }
        $new = clone $this;
        $new->fields['activityImage'] = $image ? (string) $image : null;
        $new->fields['activitySubtitle'] = $subtitle;
        $new->fields['activityText'] = $text;
        $new->fields['activityTitle'] = $title;
        return $new;
    }

    /**
     * Return new instance with facts replaced with specified
     *
     * (not to be confused with AdaptiveCard facts)
     *
     * @param array<string,mixed> $facts name => value array
     *
     * @return static
     */
    public function withFacts(array $facts = array())
    {
        $new = clone $this;
        $new->fields['facts'] = array();
        /** @var mixed $value */
        foreach ($facts as $name => $value) {
            $new->fields['facts'][] = array(
                'name' => self::asString($name, false, __METHOD__, 'fact name'),
                'value' => self::asString($value, false, __METHOD__, 'fact value'),
            );
        }
        return $new;
    }

    /**
     * Return new instance with the specified hero image
     *
     * @param string|UriInterface $url   Image url
     * @param string              $title A short description of the image
     *                                     (typically displayed as a tooltip)
     *
     * @return static
     */
    public function withHeroImage($url, $title = null)
    {
        if ($url === null) {
            return $this->with('heroImage', null);
        }
        self::assertUrl($url);
        return $this->with('heroImage', array(
            'image' => (string) $url,
            'title' => self::asString($title, true, __METHOD__),
        ));
    }

    /**
     * Return new instance with images replaced with specified
     *
     * @param array<array-key,string|array{image:string,title:string}> $images Array containing image urls and/or title/image array
     *
     * @return static
     */
    public function withImages(array $images = array())
    {
        $new = clone $this;
        $new->fields['images'] = array();
        foreach ($images as $image) {
            if (\is_array($image) === false) {
                $image = array(
                    'image' => $image,
                );
            }
            $image = \array_merge(array(
                'image' => null,
                'title' => null,
            ), $image);
            $image = \array_intersect_key($image, \array_flip(['image', 'title']));
            self::assertUrl($image['image']);
            $new->fields['images'][] = self::normalizeContent($image);
        }
        return $new;
    }

    /**
     * Return new instance
     *
     * A collection of actions that can be invoked on this section
     *
     * @param ActionInterface|ActionInterface[] $actions Action(s)
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withPotentialAction($actions)
    {
        $actions = \is_array($actions)
            ? $actions
            : [$actions];
        if (\count($actions) > 4) {
            throw new OverflowException('There can be a maximum of 4 actions (whatever their type)');
        }
        foreach ($actions as $i => $action) {
            if ($action instanceof ActionInterface) {
                continue;
            }
            throw new InvalidArgumentException(\sprintf(
                '%s: Invalid action found at index %s',
                __METHOD__,
                $i
            ));
        }
        return $this->with('potentialAction', $actions);
    }

    /**
     * Return new instance with the startGroup flag as specified
     *
     * @param bool $isStartGroup Is "startGroup" ?
     *
     * @return static
     */
    public function withStartGroup($isStartGroup = true)
    {
        self::assertBool($isStartGroup, 'isStartGroup');
        return $this->with('startGroup', $isStartGroup);
    }

    /**
     * Return new instance with the specified text
     *
     * @param string $text Text
     *
     * @return static
     */
    public function withText($text)
    {
        $text = self::asString($text, true, __METHOD__);
        return $this->with('text', $text);
    }

    /**
     * Return new instance with the specified title
     *
     * @param string|null $title Title
     *
     * @return static
     */
    public function withTitle($title)
    {
        $title = self::asString($title, true, __METHOD__);
        return $this->with('title', $title);
    }
}

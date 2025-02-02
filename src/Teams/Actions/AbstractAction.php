<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Actions;

use bdk\Teams\AbstractExtendableItem;
use bdk\Teams\Enums;
use Psr\Http\Message\UriInterface;

/**
 * Common action attributes
 */
abstract class AbstractAction extends AbstractExtendableItem implements ActionInterface
{
    /**
     * Constructor
     *
     * @param array<string,mixed> $fields Field values
     * @param string              $type   Item type
     */
    public function __construct(array $fields, $type)
    {
        $fields = \array_merge(array(
            'fallback' => null,
            'iconUrl' => null,
            'id' => null,
            'isEnabled' => null,
            'mode' => null,
            'style' => null,
            'title' => null,
            'tooltip' => null,
        ), $fields);
        parent::__construct($fields, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        $attrVersions = array(
            'fallback' => 1.2,
            'iconUrl' => 1.1,
            'id' => 1.0,
            'isEnabled' => 1.5,
            'mode' => 1.5,
            'style' => 1.2,
            'title' => 1.0,
            'tooltip' => 1.5,
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
     * Describes what to do when an unknown element is encountered or
     * the requires of this or any children can’t be met.
     *
     * @param AbstractAction|Enums::FALLBACK_* $fallback Fallback option
     *
     * @return static
     */
    public function withFallback($fallback)
    {
        self::assertFallback(
            $fallback,
            'bdk\\Teams\\Actions\\ActionInterface',
            $this->type . ' fallback should be instance of ActionInterface or one of Enum::FALLBACK_x values'
        );
        return $this->with('fallback', $fallback);
    }

    /**
     * Sets icon url
     * Optional icon to be shown on the action in conjunction with the title.
     * Supports data URI in version 1.2+
     *
     * @param string|UriInterface $iconUrl Url
     *
     * @return static
     */
    public function withIconUrl($iconUrl)
    {
        if ($iconUrl !== null) {
            self::assertUrl($iconUrl, true);
        }
        return $this->with('iconUrl', $iconUrl ? (string) $iconUrl : null);
    }

    /**
     * Return new instance with specified id
     *
     * @param string $id A unique identifier associated with this Action.
     *
     * @return static
     */
    public function withId($id)
    {
        $id = self::asString($id, true, __METHOD__);
        return $this->with('id', $id);
    }

    /**
     * Return new instance with specified value
     *
     * @param bool $isEnabled Whether the action should be enabled
     *
     * @return static
     */
    public function withIsEnabled($isEnabled = true)
    {
        self::assertBool($isEnabled, 'isEnabled');
        return $this->with('isEnabled', $isEnabled);
    }

    /**
     * Return new instance with specified mode
     *
     * @param Enums::ACTION_MODE_* $mode whether an action is displayed with a button or is moved to the overflow menu.
     *
     * @return static
     */
    public function withMode($mode)
    {
        self::assertEnumValue($mode, 'ACTION_MODE_', 'mode');
        return $this->with('mode', $mode);
    }

    /**
     * Controls the style of an Action, which influences how the action is displayed,
     *
     * @param Enums::ACTION_STYLE_* $style Style
     *
     * @return static
     */
    public function withStyle($style)
    {
        self::assertEnumValue($style, 'ACTION_STYLE_', 'style');
        return $this->with('style', $style);
    }

    /**
     * Sets title
     *
     * Label for button or link that represents this action.
     *
     * @param string $title Title
     *
     * @return static
     */
    public function withTitle($title)
    {
        $title = self::asString($title, true, __METHOD__);
        return $this->with('title', $title);
    }

    /**
     * Return new instance with specified tooltip
     *
     * @param string|null $tooltip Tooltip
     *
     * @return static
     */
    public function withTooltip($tooltip)
    {
        $tooltip = self::asString($tooltip, true, __METHOD__);
        return $this->with('tooltip', $tooltip);
    }
}

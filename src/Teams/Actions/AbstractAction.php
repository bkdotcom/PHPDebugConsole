<?php

namespace bdk\Teams\Actions;

use bdk\Teams\AbstractExtendableItem;
use bdk\Teams\Enums;

/**
 * Common action attributes
 */
abstract class AbstractAction extends AbstractExtendableItem implements ActionInterface
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->fields = \array_merge($this->fields, array(
            'fallback' => null,
            'iconUrl' => null,
            'id' => null,
            'isEnabled' => null,
            'mode' => null,
            'style' => null,
            'title' => null,
            'tooltip' => null,
        ));
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
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
    }

    /**
     * Describes what to do when an unknown element is encountered or
     * the requires of this or any children can’t be met.
     *
     * @param AbstractAction|Enums::FALLBACK_x $fallback Fallback option
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
     * @param string $iconUrl Url
     *
     * @return static
     */
    public function withIconUrl($iconUrl)
    {
        if ($iconUrl !== null) {
            self::assertUrl($iconUrl, true);
        }
        return $this->with('iconUrl', $iconUrl);
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
     * @param ACTION_MODE_x $mode whether an action is displayed with a button or is moved to the overflow menu.
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
     * @param Enums::ACTION_STYLE_x $style Style
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
     * @param string $tooltip Tooltip
     *
     * @return static
     */
    public function withTooltip($tooltip)
    {
        $tooltip = self::asString($tooltip, true, __METHOD__);
        return $this->with('tooltip', $tooltip);
    }
}

<?php

namespace bdk\Slack;

use OutOfBoundsException;

/**
 * Block elements can be used inside of section, context, input and actions layout blocks.
 *
 * @link https://api.slack.com/reference/block-kit/block-elements
 */
class BlockElementsFactory extends AbstractBlockFactory
{
    /**
     * Image Block Element
     * Works with block types: Section, Context
     *
     * @param string $url     Image url
     * @param string $altText Alternate text
     *
     * @return array<string,mixed>
     */
    public static function imageElement($url, $altText)
    {
        return array(
            'alt_text' => $altText,
            'image_url' => $url,
            'type' => 'image',
        );
    }

    /**
     * Create a button
     * Works with block types: Section, Actions
     *
     * @param string $actionId unique identifier
     * @param string $label    Text on button
     * @param string $value    button value
     * @param array  $values   button properties
     *
     * @return array<string,mixed>
     */
    public static function button($actionId, $label, $value = null, $values = array())
    {
        $block = \array_merge(array(
            'accessibility_label' => null,
            'action_id' => (string) $actionId,  // really required?
            'confirm' => null,
            'style' => 'default', // primary | danger
            'text' => self::normalizeText([
                'text' => $label,
                'type' => 'plain_text',
            ]),
            'type' => 'button',
            'url' => null,
            'value' => $value,
        ), $values);
        return self::removeNull($block);
    }

    /**
     * Create a checkbox group
     * Works with block types: Section, Actions, Input
     *
     * @param string $actionId ActionId
     * @param array  $options  Selectable options
     * @param array  $values   Attributes
     *
     * @return array<string,mixed>
     */
    public static function checkboxes($actionId, array $options, $values = array())
    {
        return self::checkboxesRadio($actionId, $options, $values, 'checkboxes');
    }

    /**
     * Create a date picker
     * Works with block types: Section, Actions, Input
     *
     * @param string $actionId Action Id
     * @param array  $values   Attributes
     *
     * @return array<string,mixed>
     */
    public static function datePicker($actionId, $values = array())
    {
        $block = \array_merge(array(
            'action_id' => (string) $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'initial_date' => null, // YYYY-MM-DD
            'placeholder' => null,
            'type' => 'datepicker',
        ), $values);
        $block = self::renameDefault($block, 'initial_date');
        $block['placeholder'] = self::normalizeText($block['placeholder']);
        return self::removeNull($block);
    }

    /**
     * Create a Date & Time picker
     * Works with block types: Actions, Input
     *
     * @param string $actionId Action id
     * @param array  $values   Attributes
     *
     * @return array<string,mixed>
     */
    public static function datetimePicker($actionId, $values = array())
    {
        $block = \array_merge(array(
            'action_id' => (string) $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'initial_date_time' => null, // unix timestamp
            'type' => 'datetimepicker',
        ), $values);
        $block = self::renameDefault($block, 'initial_date_time');
        return self::removeNull($block);
    }

    /**
     * Create an email input
     * Works with block types: Input
     *
     * @param string $actionId Action id
     * @param array  $values   Attributes
     *
     * @return array<string,mixed>
     */
    public static function email($actionId, $values = array())
    {
        $block = \array_merge(array(
            'action_id' => (string) $actionId,
            'dispatch_action_config' => null,
            'focus_on_load' => false,
            'initial_value' => null,
            'placeholder' => null,
            'type' => 'email_text_input',
        ), $values);
        $block = self::renameDefault($block);
        $block['placeholder'] = self::normalizeText($block['placeholder']);
        return self::removeNull($block);
    }

    /**
     * Only works from Modal input block
     *
     * @param array $values Number input properties
     *
     * @return array<string,mixed>
     */
    public static function number($values = array())
    {
        $block = \array_merge(array(
            'action_id' => null,
            'dispatch_action_config' => null,
            'focus_on_load' => false,
            'initial_value' => null,
            'is_decimal_allowed' => false,
            'max_value' => null,
            'min_value' => null,
            'placeholder' => null,
            'type' => 'number_input',
        ), $values);
        $block = self::renameDefault($block);
        $block['placeholder'] = self::normalizeText($block['placeholder']);
        return self::removeNull($block);
    }

    /**
     * Overflow menu element
     *
     * @param string $actionId Unique identifier
     * @param array  $options  Up to 5 options
     * @param array  $values   Overflow element properties
     *
     * @return array<string,mixed>
     */
    public static function overflow($actionId, array $options, $values = array())
    {
        $block = \array_merge(array(
            'action_id' => $actionId,
            'confirm' => null,
            'options' => self::normalizeOptions($options, 5, 'overflow'),
            'type' => 'overflow',
        ), $values);
        return self::removeNull($block);
    }

    /**
     * Create radio-button group
     * Works with block types: Section Actions Input
     *
     * @param string $actionId Action Id
     * @param array  $options  Selectable options
     * @param array  $values   Attributes
     *
     * @return array<string,mixed>
     */
    public static function radio($actionId, array $options, $values = array())
    {
        return self::checkboxesRadio($actionId, $options, $values, 'radio_buttons');
    }

    /**
     * Create a select menu
     *
     * @param string $actionId Action id
     * @param array  $options  Selectable options
     * @param bool   $multiple (false) Allow multi-select?
     * @param array  $values   Attributes
     *
     * @return array<string,mixed>
     */
    public static function select($actionId, array $options = array(), $multiple = false, $values = array())
    {
        $block = \array_merge(array(
            'action_id' => (string) $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'initial_option' => null, // use with static_slect
            'initial_options' => null, // use with multi_static_select
            'max_selected_items' => null,   // min = 1
            'options' => $options,
            'option_groups' => null, // max 100 groups
            'placeholder' => null,
            'type' => $multiple ? 'multi_static_select' : 'static_select',
        ), $values);
        $block = self::renameDefault($block);
        if ($block['initial_options'] !== null) {
            $block['initial_options'] = self::normalizeOptions((array) $block['initial_options'], 100, $block['type']);
        }
        if ($block['option_groups']) {
            $block['options'] = null;
        } elseif ($block['options']) {
            $block['options'] = self::normalizeOptions($options, 100, $block['type']); // max 100 options
        }
        $block['placeholder'] = self::normalizeText($block['placeholder']);
        return self::removeNull($block);
    }

    /**
     * Plain text element
     * Works with block types: Input
     *
     * @param string $actionId Unique identifier
     * @param array  $values   Input properties
     *
     * @return array<string,mixed>
     */
    public static function textInput($actionId, $values = array())
    {
        $block = \array_merge(array(
            'action_id' => $actionId,
            'dispatch_action_config' => null,
            'focus_on_load' => false,
            'initial_value' => null,
            'max_length' => null,
            'min_length' => null,
            'multiline' => false,
            'placeholder' => null,
            'type' => 'plain_text_input',
        ), $values);
        $block = self::renameDefault($block);
        $block['placeholder'] = self::normalizeText($block['placeholder']);
        return self::removeNull($block);
    }

    /**
     * Time picker element
     * Works with block types: Section Actions Input
     *
     * @param string $actionId Unique identifier
     * @param array  $values   Input properties
     *
     * @return array<string,mixed>
     */
    public static function timePicker($actionId, $values = array())
    {
        $block = \array_merge(array(
            'action_id' => $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'initial_time' => null, // HH:mm   (24-hour format)
            'placeholder' => null,
            'type' => 'timepicker',
        ), $values);
        $block = self::renameDefault($block, 'initial_time');
        $block['placeholder'] = self::normalizeText($block['placeholder']);
        return self::removeNull($block);
    }

    /**
     * URL input element
     * Works with block types: Input
     *
     * @param string $actionId Unique identifier
     * @param array  $values   Input properties
     *
     * @return array<string,mixed>
     */
    public static function url($actionId, $values = array())
    {
        $block = \array_merge(array(
            'action_id' => $actionId,
            'dispatch_action_config' => null,
            'focus_on_load' => false,
            'initial_value' => null,
            'placeholder' => null,
            'type' => 'url_text_input',
        ), $values);
        $block = self::renameDefault($block);
        $block['placeholder'] = self::normalizeText($block['placeholder']);
        return self::removeNull($block);
    }

    /**
     * Create a checkbox or radio  group
     *
     * @param string $actionId ActionId
     * @param array  $options  Selectable options
     * @param array  $values   Attributes
     * @param string $what     "checkboxes" or "radio_buttons"
     *
     * @return array<string,mixed>
     */
    private static function checkboxesRadio($actionId, array $options, array $values, $what)
    {
        $initialOptionKey = $what === 'checkboxes'
            ? 'initial_options'
            : 'initial_option';
        $block = \array_merge(array(
            $initialOptionKey => null,
            'action_id' => (string) $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'options' => self::normalizeOptions($options, 10, $what),
            'type' => $what,
        ), $values);
        $block = self::renameDefault($block);
        return self::removeNull($block);
    }

    /**
     * Normalize checkbox, radio, or select options
     *
     * @param array  $options checkbox, radio, overflow, or select block element
     * @param int    $max     Maximum number of allowed options
     * @param string $where   Name of element options belong to
     *
     * @return array
     *
     * @throws OutOfBoundsException
     */
    private static function normalizeOptions(array $options, $max, $where)
    {
        foreach ($options as $k => $option) {
            $options[$k] = self::normalizeOption($option, $k);
        }
        $count = \count($options);
        if ($count > $max) {
            throw new OutOfBoundsException(\sprintf('A maximum of %d options are allowed in %s element. %d provided', $max, $where, $count));
        }
        return \array_values($options);
    }

    /**
     * Normaliee a single option "object"
     *
     * Overflow, select, and multi-select menus can only use plain_text
     * Radio buttons and checkboxes can use mrkdwn text objects.
     *
     * @param array|string    $option option text/value
     * @param int|string|null $key    The option's array key...
     *                                   if a string, it will used as the option's text (label)
     *
     * @return array<string,mixed>
     */
    private static function normalizeOption($option, $key = null)
    {
        if (\is_array($option) === false) {
            $option = array(
                'text' => $option,
                'value' => $option,
            );
        }
        if (\is_string($key)) {
            $option['text'] = $key;
        }
        $isMrkdwn = isset($option['type']) && $option['type'] === 'mrkdwn';
        $option['text'] = self::normalizeText($option['text'], $isMrkdwn);
        $option = \array_intersect_key($option, array(
            'description' => null,
            'text' => null,
            'url' => null, // only for overflow,
            'value' => null,
        ));
        return self::removeNull($option);
    }

    /**
     * Rename 'default' to block-specific name
     *
     * @param array  $block    block values
     * @param string $renameTo block type's "initial_value" name
     *
     * @return array
     */
    private static function renameDefault(array $block, $renameTo = 'initial_value')
    {
        if (\in_array($block['type'], array('checkboxes', 'multi_static_select'), true)) {
            $renameTo = 'initial_options';
        } elseif (\in_array($block['type'], array('radio_buttons', 'static_select'), true)) {
            $renameTo = 'initial_option';
        }
        if (isset($block['default'])) {
            $block[$renameTo] = $block['default'];
            unset($block['default']);
        }
        return $block;
    }
}

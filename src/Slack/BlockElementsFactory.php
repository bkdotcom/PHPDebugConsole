<?php

/**
 * @package   bdk\slack
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Slack;

use Closure;
use UnexpectedValueException;

/**
 * Block elements can be used inside of section, context, input and actions layout blocks.
 *
 * @link https://api.slack.com/reference/block-kit/block-elements
 *
 * @psalm-api
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
        $default = array(
            'accessibility_label' => null,
            'action_id' => (string) $actionId,  // really required?
            'confirm' => null,
            'style' => 'default', // primary | danger
            'text' => self::normalizeText([
                'text' => $label,
                'type' => 'plain_text',
            ], 'button text'),
            'type' => 'button',
            'url' => null,
            'value' => $value,
        );
        return self::initBlock($default, $values);
    }

    /**
     * Create a checkbox group
     * Works with block types: Section, Actions, Input
     *
     * @param string              $actionId ActionId
     * @param array               $options  Selectable options
     * @param array<string,mixed> $values   Attributes
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
        $default = array(
            'action_id' => (string) $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'initial_date' => null, // YYYY-MM-DD
            'placeholder' => null,
            'type' => 'datepicker',
        );
        return self::initBlock($default, $values);
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
        $default = array(
            'action_id' => (string) $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'initial_date_time' => null, // unix timestamp
            'type' => 'datetimepicker',
        );
        return self::initBlock($default, $values);
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
        $default = array(
            'action_id' => (string) $actionId,
            'dispatch_action_config' => null,
            'focus_on_load' => false,
            'initial_value' => null,
            'placeholder' => null,
            'type' => 'email_text_input',
        );
        return self::initBlock($default, $values);
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
        $default = array(
            'action_id' => null,
            'dispatch_action_config' => null,
            'focus_on_load' => false,
            'initial_value' => null,
            'is_decimal_allowed' => false,
            'max_value' => null,
            'min_value' => null,
            'placeholder' => null,
            'type' => 'number_input',
        );
        return self::initBlock($default, $values);
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
        $default = array(
            'action_id' => $actionId,
            'confirm' => null,
            'options' => self::normalizeOptions($options, 5, 'overflow'),
            'type' => 'overflow',
        );
        return self::initBlock($default, $values);
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
     *
     * @throws UnexpectedValueException
     */
    public static function select($actionId, array $options = array(), $multiple = false, $values = array())
    {
        $default = array(
            'action_id' => (string) $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'initial_option' => null, // use with static_select
            'initial_options' => null, // use with multi_static_select
            'max_selected_items' => null, // min = 1
            'options' => $options,
            'option_groups' => null, // max 100 groups
            'placeholder' => null,
            'type' => $multiple ? 'multi_static_select' : 'static_select',
        );
        $closure =
        /**
         * @param array{type:string,...<string,mixed>} $block
         */
        static function (array $block) {
            if ($block['initial_options'] !== null) {
                $block['initial_options'] = self::normalizeOptions((array) $block['initial_options'], 100, $block['type']);
            }
            if ($block['option_groups'] !== null) {
                $block['options'] = null;
            } elseif (\is_array($block['options'])) {
                $block['options'] = self::normalizeOptions($block['options'], 100, $block['type']); // max 100 options
            }
            return $block;
        };
        return self::initBlock($default, $values, $closure);
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
        $default = array(
            'action_id' => $actionId,
            'dispatch_action_config' => null,
            'focus_on_load' => false,
            'initial_value' => null,
            'max_length' => null,
            'min_length' => null,
            'multiline' => false,
            'placeholder' => null,
            'type' => 'plain_text_input',
        );
        return self::initBlock($default, $values);
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
        $default = array(
            'action_id' => $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'initial_time' => null, // HH:mm   (24-hour format)
            'placeholder' => null,
            'type' => 'timepicker',
        );
        return self::initBlock($default, $values);
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
        $default = array(
            'action_id' => $actionId,
            'dispatch_action_config' => null,
            'focus_on_load' => false,
            'initial_value' => null,
            'placeholder' => null,
            'type' => 'url_text_input',
        );
        return self::initBlock($default, $values);
    }

    /**
     * Create a checkbox or radio  group
     *
     * @param string $actionId ActionId
     * @param array  $options  Selectable options
     * @param array  $values   Attributes
     * @param string $type     "checkboxes" or "radio_buttons"
     *
     * @return array<string,mixed>
     */
    protected static function checkboxesRadio($actionId, array $options, array $values, $type)
    {
        $initialOptionKey = $type === 'checkboxes'
            ? 'initial_options'
            : 'initial_option';
        $default = array(
            $initialOptionKey => null,
            'action_id' => (string) $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'options' => self::normalizeOptions($options, 10, $type),
            'type' => $type,
        );
        return self::initBlock($default, $values);
    }

    /**
     * Merge default and user values
     *
     * @param array{type?:string,...<string,mixed>} $default Block's default values
     * @param array                                 $values  User supplied values
     * @param Closure|null                          $closure called before removing null values
     *
     * @return array{type?:string,...<string,mixed>}
     *
     * @throws UnexpectedValueException
     */
    protected static function initBlock($default, $values, $closure = null)
    {
        $block = \array_merge($default, $values);
        if (isset($default['type'])) {
            // don't allow user to override
            $block['type'] = $default['type'];
            $block = self::renameDefault($block);
        }
        /** @psalm-var array{type:string, ...<string,mixed>} psalm bug - should infer, but doesn't */
        $block = \array_intersect_key($block, $default);
        if (isset($block['placeholder'])) {
            $block['placeholder'] = self::normalizeText($block['placeholder'], 'placeholder');
        }
        if ($closure) {
            /** @psalm-var array{type:string, ...<string,mixed>} */
            $block = $closure($block);
        }
        /** @psalm-var array{type:string, ...<string,mixed>} */
        return self::removeNull($block);
    }
}

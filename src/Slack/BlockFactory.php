<?php

namespace bdk\Slack;

use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Block elements can be used inside of section, context, input and actions layout blocks.
 *
 * @link https://api.slack.com/reference/block-kit/blocks
 * @link https://api.slack.com/reference/block-kit/block-elements
 * @link https://api.slack.com/reference/messaging/attachments
 */
class BlockFactory
{
    const COLOR_DANGER = 'danger'; // red
    const COLOR_DEFAULT = '#dddddd';
    const COLOR_GOOD = 'good'; // green
    const COLOR_WARNING = 'warning'; // yellow

    /**
     * Actions block
     *
     * @param array $elements Element definitions
     * @param array $values   Actions block fields
     *
     * @return array
     */
    public static function actions(array $elements, $values = array())
    {
        $default = array(
            'block_id' => null,         // max: 255 chars
            'elements' => $elements,    // max: 25
            'type' => 'actions',
        );
        $block = \array_merge($default, $values);
        $block = \array_intersect_key($block, $default);
        self::assertElements($block['elements'], 'actions');
        return self::removeNull($block);
    }

    /**
     * Context block
     *
     * @param array $elements context element blocks (text or image)
     * @param array $values   context block fields
     *
     * @return array<string,mixed>
     */
    public static function context(array $elements, $values = array())
    {
        $default = array(
            'block_id' => null, // max: 255 chars
            'elements' => $elements, // max: 10
            'type' => 'context',
        );
        $block = \array_merge($default, $values);
        $block = \array_intersect_key($block, $default);
        $block['elements'] = \array_map(static function ($element) {
            return \is_array($element)
                ? $element
                : self::normalizeText($element);
        }, $elements);
        self::assertElements($block['elements'], 'context');
        return self::removeNull($block);
    }

    /**
     * Divider block
     *
     * @return array<string,mixed>
     */
    public static function divider()
    {
        return array(
            'type' => 'divider',
            // 'block_id'
        );
    }

    /**
     * Header layout block
     *
     * @param string $text   Plain text
     * @param array  $values Header block fields
     *
     * @return array<string,mixed>
     */
    public static function header($text, $values = array())
    {
        $default = array(
            'block_id' => null,     // max: 255 chars
            'text' => array(
                'text' => $text,    // max: 150 chars
                'type' => 'plain_text',
            ),
            'type' => 'header',
        );
        $block = \array_merge($default, $values);
        $block = \array_intersect_key($block, $default);
        $block['text'] = self::normalizeText($block['text']);
        return self::removeNull($block);
    }

    /**
     * Image layout block
     *
     * @param string $url     Image url
     * @param string $altText Plain-text string
     * @param string $title   (optional) plain-text title
     * @param array  $values  image block fields
     *
     * @return array<string,mixed>
     */
    public static function image($url, $altText, $title = null, $values = array())
    {
        $default = array(
            'alt_text' => $altText, // max: 2000 chars
            'block_id' => null,     // max: 255 chars
            'image_url' => $url,    // max: 3000 chars
            'title' => $title,      // max: 2000 chars
            'type' => 'image',
        );
        $block = \array_merge($default, $values);
        $block = \array_intersect_key($block, $default);
        $block['title'] = self::normalizeText($block['title']);
        return self::removeNull($block);
    }

    /**
     * Input layout block
     *
     * @param string $label   Plain-text label
     * @param array  $element Input block
     * @param array  $values  Input layout block fields
     *
     * @return array<string,mixed>
     *
     * @throws InvalidArgumentException
     */
    public static function input($label, $element, $values = array())
    {
        $default = array(
            'block_id' => null,     // max: 255 chars
            'dispatch_action' => false,
            'element' => $element,
            'hint' => null,         // obj. max: 2000 chars
            'label' => array(
                'text' => $label,   // max: 2000 chars
                'type' => 'plain_text',
            ),
            'optional' => false,
            'type' => 'input',
        );
        $block = \array_merge($default, $values);
        $block = \array_intersect_key($block, $default);
        $block['label'] = self::normalizeText($block['label']);
        $block['hint'] = self::normalizeText($block['hint']);
        self::assertInputElement($block['element']);
        return self::removeNull($block);
    }

    /**
     * Section block
     *
     * @param string|array $text      Text for the block
     * @param array        $fields    array of up to 10 text objects
     * @param array        $accessory optional block element
     * @param array        $values    Section block fields
     *
     * @return array<string,mixed>
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    public static function section($text, $fields = array(), $accessory = null, $values = array())
    {
        $default = array(
            'accessory' => $accessory,
            'block_id' => null, // max: 255 chars
            'fields' => $fields, // max: 10,  each text's max: 2000 chars
            'text' => $text, // optional text obj... defaults to mrkdown
                                // max: 3000 chars
            'type' => 'section',
        );
        $block = \array_merge($default, $values);
        $block = \array_intersect_key($block, $default);
        $block['text'] = self::normalizeText($block['text'], true);

        $block['fields'] = \array_map(static function ($field) {
            return self::normalizeText($field, true);
        }, $block['fields']);
        if (empty($block['fields'])) {
            unset($block['fields']);
        } elseif (\count($block['fields']) > 10) {
            throw new OutOfBoundsException(\sprintf('A maximum of 10 fields are allowed in section block. %d provided', \count($block['fields'])));
        }

        if (isset($block['accessory'])) {
            self::assertAccessory($block['accessory']);
        }
        return self::removeNull($block);
    }

    /**
     * Video block
     *
     * @param string $url     The URL to be embedded. Must match any existing unfurl domains within the app and point to a HTTPS URL.
     * @param string $title   Video title in plain text format. Must be less than 200 characters.
     * @param string $altText A tooltip for the video. Required for accessibility
     * @param array  $values  Video properties
     *
     * @return array<string,mixed>
     */
    public static function video($url, $title, $altText, $values = array())
    {
        $default = array(
            'alt_text' => $altText,
            'author_name' => null,  // max: 49 chars
            'block_id' => null,     // max: 255 chass
            'description' => null,
            'provider_icon_url' => null,
            'provider_name' => null,
            'thumbnail_url' => null,
            'title' => $title, // max: 199 chars
            'title_url' => null, // must be https
            'type' => 'video',
            'video_url' => $url, // must be https
        );
        $block = \array_merge($default, $values);
        $block = \array_intersect_key($block, $default);
        $block['description'] = self::normalizeText($block['description']);
        return self::removeNull($block);
    }

    /**
     * Secondary message attachment
     *
     * @param string $text   The main body text of the attachment.
     * @param array  $blocks layout blocks
     * @param array  $values attachment field values
     *
     * @return array<string,mixed>
     *
     * @link https://api.slack.com/reference/messaging/attachments
     */
    public static function attachment($text, $blocks = array(), $values = array())
    {
        /*
            everything other than blocks and color are "legacy"
            Legacy fields are optional if you're including blocks as above.
            If you aren't, one of either fallback or text are required:
        */
        $default = array(
            'author_icon' => null,
            'author_link' => null,
            'author_name' => null,
            'blocks' => $blocks, // An array of layout blocks in the same format as described here https://api.slack.com/reference/block-kit/blocks
            'color' => self::COLOR_DEFAULT,
            'fallback' => null, // A plain text summary of the attachment used in clients that don't show formatted text
            'fields' => array(), // title / value / short = false    (all optional)
            'footer' => null, // text max: 300 chars
            'footer_icon' => null,  // Will only work if author_name is present.
            'image_url' => null,
            'mrkdwn_in' => null, // An array of field names that should be formattd with markdown
            'pretext' => null, // Text that appears above the message attachment block.
            'text' => $text,
            'thumb_url' => null, // A valid URL to an image file that will be displayed as a thumbnail on the right side of a message attachment.
            'title' => null, // Large title text near the top of the attachment.
            'title_link' => null,
            'ts' => null,
        );
        $attachment = \array_merge($default, $values);
        $attachment = \array_intersect_key($attachment, $default);
        foreach ($attachment['fields'] as $i => $field) {
            if (\is_array($field) === false) {
                $field = array('value' => $field);
            }
            $field = \array_merge(array(
                'short' => false, // Indicates whether the field object is short enough to be displayed side-by-side with other field objects.
                'title' => null, // Shown as a bold heading displayed in the field object.
                                 // It cannot contain markup and will be escaped for you.
                'value' => null, // The text value displayed in the field object.
                                 // It can be formatted as plain text, or with mrkdwn by using the mrkdwn_in option above.
            ), $field);
            $field = self::removeNull($field);
            if (\count($field) === 1) {
                // just 'short'
                unset($attachment['fields'][$i]);
                continue;
            }
            $attachment['fields'][$i] = $field;
        }
        $attachment['fields'] = \count($attachment['fields']) > 0
            ? \array_values($attachment['fields'])
            : null;
        return self::removeNull($attachment);
    }

    /*
        Block elements
    */

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
     * @param string $options  Selectable options
     * @param array  $values   Attributes
     *
     * @return array<string,mixed>
     */
    public static function checkboxes($actionId, $options, $values = array())
    {
        $block = \array_merge(array(
            'action_id' => (string) $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'initial_options' => null,
            'options' => self::normalizeOptions($options, 10, 'checkboxes'),
            'type' => 'checkboxes',
        ), $values);
        $block = self::renameDefault($block, 'initial_options');
        return self::removeNull($block);
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
     * @param string $options  Selectable options
     * @param array  $values   Attributes
     *
     * @return array<string,mixed>
     */
    public static function radio($actionId, $options, $values = array())
    {
        $block = \array_merge(array(
            'action_id' => (string) $actionId,
            'confirm' => null,
            'focus_on_load' => false,
            'initial_option' => null,
            'options' => self::normalizeOptions($options, 10, 'radio_buttons'),
            'type' => 'radio_buttons',
        ), $values);
        $block = self::renameDefault($block, 'initial_option');
        return self::removeNull($block);
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
     * Assert valid section accessory
     *
     * @param array<string,mixed> $accessory Accessory array definition
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private static function assertAccessory(array $accessory)
    {
        $validAccessoryTypes = array(
            'image',
            'button',
            'checkboxes',
            'datepicker',
            'multi_static_select',
            'overflow',
            'radio_buttons',
            'static_select',
            'timePicker,',
        );
        if (\is_array($accessory) === false || isset($accessory['type']) === false) {
            throw new InvalidArgumentException('Invalid accessory provided for section block');
        }
        if (\in_array($accessory['type'], $validAccessoryTypes, true) === false) {
            throw new InvalidArgumentException(\sprintf('%s is an invalid type for accessory', $accessory['type']));
        }
    }

    /**
     * Assert valid actions/context elements
     *
     * @param array  $elements Actions/Context elements
     * @param string $where    'actions' or 'context'
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    private static function assertElements(array $elements, $where = 'actions')
    {
        $validElementTypes = array(
            'actions' => array(
                'button',
                'checkboxes',
                'datepicker',
                'datetimepicker',
                'multi_static_select', 'static_select',
                'overflow',
                'radio_buttons',
                'timepicker',
            ),
            'context' => array(
                'image',
                'mrkdwn',
                'plain_text',
            ),
        );
        \array_walk($elements, static function ($element, $index) use ($validElementTypes, $where) {
            if (\is_array($element) === false || isset($element['type']) === false) {
                throw new InvalidArgumentException(\sprintf('Invalid element (index %s) provided for %s block', $index, $where));
            }
            if (\in_array($element['type'], $validElementTypes[$where], true) === false) {
                throw new InvalidArgumentException(\sprintf('%s (index %s) is an invalid %s element', $element['type'], $index, $where));
            }
        });
        $max = $where === 'actions'
            ? 25
            : 10;
        $elementCount = \count($elements);
        if ($elementCount === 0) {
            throw new OutOfBoundsException(\sprintf('At least one element is require for %s block', $where));
        } elseif ($elementCount > $max) {
            throw new OutOfBoundsException(\sprintf('A maximum of %d elements are allowed in %s block. %d provided', $max, $where, $elementCount));
        }
    }

    /**
     * Assert valid input block element
     *
     * @param array<string,mixed> $element Input block element
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private static function assertInputElement($element)
    {
        $validElementTypes = array(
            'checkboxes',
            'datepicker',
            'datetimepicker',
            'email_text_input',
            'multi_static_select', 'static_select',
            'number_input',
            'plain_text_input',
            'radio_buttons',
            'timePicker,',
            'url_text_input',
        );
        if (\is_array($element) === false || isset($element['type']) === false) {
            throw new InvalidArgumentException('Invalid element provided for input block');
        }
        if (\in_array($element['type'], $validElementTypes, true) === false) {
            throw new InvalidArgumentException(\sprintf('%s is an invalid input type for input block element', $element['type']));
        }
    }

    /**
     * Remove null values from array
     *
     * @param array $values Input array
     *
     * @return array
     */
    private static function removeNull(array $values)
    {
        if (isset($values['focus_on_load']) && $values['focus_on_load'] === false) {
            unset($values['focus_on_load']);
        }
        return \array_filter($values, static function ($value) {
            return $value !== null;
        });
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
     * @param array|string    $option option text/value
     * @param int|string|null $key    The option's array key...
     *                                   if a string, it will used as the option's text (label)
     *
     * @return array<string,mixed>
     */
    private static function normalizeOption($option, $key = null)
    {
        // Overflow, select, and multi-select menus can only use plain_text
        // radio buttons and checkboxes can use mrkdwn text objects.
        $default = array(
            'description' => null,
            'text' => null,
            'url' => null, // only for overflow,
            'value' => null,
        );
        if (\is_array($option) === false) {
            $option = array(
                'text' => $option,
                'value' => $option,
            );
        }
        if (\is_string($key)) {
            $option['text'] = $key;
        }
        if (isset($option['text']) && \is_array($option['text']) === false) {
            $option['text'] = array(
                'text' => $option['text'],
            );
        }
        if (isset($option['type'])) {
            $option['text']['type'] = $option['type'];
        }
        $option['text'] = self::normalizeText($option['text']);
        $option = \array_intersect_key($option, $default);
        return self::removeNull($option);
    }

    /**
     * Normalize text object
     *
     * @param string|array|null $text     Text object value
     * @param bool              $isMrkdwn Is text formatted with markdown?
     *
     * @return array<string,string>|null
     */
    private static function normalizeText($text, $isMrkdwn = false)
    {
        if (\is_array($text) === false) {
            $text = array(
                'text' => $text,
            );
        }
        $default = array(
            'emoji' => true, // only applies to plain_text
            'text' => '',
            'type' => $isMrkdwn ? 'mrkdwn' : 'plain_text',
            'verbatim' => false, // only applies to mrkdwn
        );
        $text = \array_merge($default, $text);
        $text = \array_intersect_key($text, $default);
        $text['text'] = (string) $text['text'];
        if ($text['text'] === '') {
            return null;
        }
        if ($text['type'] !== 'plain_text' || $text['emoji'] === true) {
            unset($text['emoji']);
        }
        if ($text['type'] !== 'mrkdwn' || $text['verbatim'] === false) {
            unset($text['verbatim']);
        }
        return $text;
    }

    /**
     * Rename 'default' to block-specific name
     *
     * @param array  $block    block values
     * @param string $renameTo block type's "initial_value" name
     *
     * @return array
     */
    private static function renameDefault($block, $renameTo = 'initial_value')
    {
        if ($block['type'] === 'multi_static_select') {
            $renameTo = 'initial_options';
        } elseif ($block['type'] === 'static_select') {
            $renameTo = 'initial_option';
        }
        if (isset($block['default'])) {
            $block[$renameTo] = $block['default'];
            unset($block['default']);
        }
        return $block;
    }
}

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
class BlockFactory extends BlockElementsFactory
{
    const COLOR_DANGER = 'danger'; // red
    const COLOR_DEFAULT = '#dddddd';
    const COLOR_GOOD = 'good'; // green
    const COLOR_WARNING = 'warning'; // yellow

    protected static $defaults = array(
        'actions' => array(
            'block_id' => null,     // max: 255 chars
            'elements' => array(),  // max: 25
            'type' => 'actions',
        ),
        'attachment' => array(
            'author_icon' => null,
            'author_link' => null,
            'author_name' => null,
            'blocks' => array(), // An array of layout blocks in the same format as described here https://api.slack.com/reference/block-kit/blocks
            'color' => self::COLOR_DEFAULT,
            'fallback' => null, // A plain text summary of the attachment used in clients that don't show formatted text
            'fields' => array(), // title / value / short = false    (all optional)
            'footer' => null, // text max: 300 chars
            'footer_icon' => null,  // Will only work if author_name is present.
            'image_url' => null,
            'mrkdwn_in' => null, // An array of field names that should be formattd with markdown
            'pretext' => null, // Text that appears above the message attachment block.
            'text' => null,
            'thumb_url' => null, // A valid URL to an image file that will be displayed as a thumbnail on the right side of a message attachment.
            'title' => null, // Large title text near the top of the attachment.
            'title_link' => null,
            'ts' => null,
        ),
        'context' => array(
            'block_id' => null,     // max: 255 chars
            'elements' => array(),  // max: 10
            'type' => 'context',
        ),
        'header' => array(
            'block_id' => null,     // max: 255 chars
            'text' => array(
                'text' => null,     // max: 150 chars
                'type' => 'plain_text',
            ),
            'type' => 'header',
        ),
        'image' => array(
            'alt_text' => null,     // max: 2000 chars
            'block_id' => null,     // max: 255 chars
            'image_url' => null,    // max: 3000 chars
            'title' => null,        // max: 2000 chars
            'type' => 'image',
        ),
        'input' => array(
            'block_id' => null,     // max: 255 chars
            'dispatch_action' => false,
            'element' => null,
            'hint' => null,         // obj. max: 2000 chars
            'label' => array(
                'text' => '',       // max: 2000 chars
                'type' => 'plain_text',
            ),
            'optional' => false,
            'type' => 'input',
        ),
        'section' => array(
            'accessory' => null,
            'block_id' => null,     // max: 255 chars
            'fields' => array(),    // max: 10,  each text's max: 2000 chars
            'text' => null,         // optional text obj... defaults to mrkdown
                                    //   max: 3000 chars
            'type' => 'section',
        ),
        'video' => array(
            'alt_text' => null,
            'author_name' => null,  // max: 49 chars
            'block_id' => null,     // max: 255 chass
            'description' => null,
            'provider_icon_url' => null,
            'provider_name' => null,
            'thumbnail_url' => null,
            'title' => null, // max: 199 chars
            'title_url' => null, // must be https
            'type' => 'video',
            'video_url' => null, // must be https
        ),
    );

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
        $block = \array_merge(self::$defaults['actions'], array(
            'elements' => $elements,
        ), $values);
        $block = \array_intersect_key($block, self::$defaults['actions']);
        self::assertElements($block['elements'], 'actions', 25);
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
        $block = \array_merge(self::$defaults['context'], array(
            'elements' => $elements,
        ), $values);
        $block = \array_intersect_key($block, self::$defaults['context']);
        $block['elements'] = \array_map(static function ($element) {
            return \is_array($element)
                ? $element
                : self::normalizeText($element);
        }, $elements);
        self::assertElements($block['elements'], 'context', 10);
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
        $block = \array_replace_recursive(self::$defaults['header'], array(
            'text' => array(
                'text' => $text,
            ),
        ), $values);
        $block = \array_intersect_key($block, self::$defaults['header']);
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
        $block = \array_merge(self::$defaults['image'], array(
            'alt_text' => $altText,
            'image_url' => $url,
            'title' => $title,
        ), $values);
        $block = \array_intersect_key($block, self::$defaults['image']);
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
        $block = \array_replace_recursive(self::$defaults['input'], array(
            'element' => $element,
            'label' => array(
                'text' => $label,
            ),
        ), $values);
        $block = \array_intersect_key($block, self::$defaults['input']);
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
        $block = \array_merge(self::$defaults['section'], array(
            'accessory' => $accessory,
            'fields' => $fields,
            'text' => $text,
        ), $values);
        $block = \array_intersect_key($block, self::$defaults['section']);
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
     * @param string $altText A tooltip for the video. Required for accessibility
     * @param string $title   Video title in plain text format. Must be less than 200 characters.
     * @param array  $values  Video properties
     *
     * @return array<string,mixed>
     */
    public static function video($url, $altText, $title, $values = array())
    {
        $block = \array_merge(self::$defaults['video'], array(
            'alt_text' => $altText,
            'title' => $title,
            'video_url' => $url,
        ), $values);
        $block = \array_intersect_key($block, self::$defaults['video']);
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
        $attachment = \array_merge(self::$defaults['attachment'], array(
            'blocks' => $blocks,
            'text' => $text,
        ), $values);
        $attachment = \array_intersect_key($attachment, self::$defaults['attachment']);
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
}

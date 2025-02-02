<?php

/**
 * @package   bdk\slack
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Slack;

use InvalidArgumentException;
use OverflowException;
use UnexpectedValueException;

/**
 * Block elements can be used inside of section, context, input and actions layout blocks.
 *
 * @link https://api.slack.com/reference/block-kit/blocks
 * @link https://api.slack.com/reference/block-kit/block-elements
 * @link https://api.slack.com/reference/messaging/attachments
 *
 * @psalm-api
 */
class BlockFactory extends BlockElementsFactory
{
    const COLOR_DANGER = 'danger'; // red
    const COLOR_DEFAULT = '#dddddd';
    const COLOR_GOOD = 'good'; // green
    const COLOR_WARNING = 'warning'; // yellow

    /**
     * @var array<string,array<string,mixed>>
     */
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
            'mrkdwn_in' => null, // An array of field names that should be formatted with markdown
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
            'block_id' => null,     // max: 255 chars
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
     * @return array<string,mixed>
     */
    public static function actions(array $elements, $values = array())
    {
        $default = \array_merge(self::$defaults['actions'], array(
            'elements' => $elements,  // max: 25
        ));
        return self::initBlock($default, $values, static function (array $block) {
            self::assertElements($block['elements'], 'actions', 25);
            return $block;
        });
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
        $default = \array_merge(self::$defaults['context'], array(
            'elements' => $elements,  // max: 10
        ));
        return self::initBlock($default, $values, static function (array $block) {
            if (\is_array($block['elements']) === false) {
                throw new UnexpectedValueException(\sprintf(
                    'context block:  elements must be array.  %s provided.',
                    self::getDebugType($block['elements'])
                ));
            }
            $block['elements'] = \array_map(static function ($element) {
                return \is_array($element)
                    ? $element
                    : self::normalizeText($element, 'element');
            }, $block['elements']);
            self::assertElements($block['elements'], 'context', 10);
            return $block;
        });
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
        $default = \array_merge(self::$defaults['header'], array(
            'text' => array(
                'text' => $text,     // max: 150 chars
                'type' => 'plain_text',
            ),
        ));
        return self::initBlock($default, $values, static function (array $block) {
            $block['text'] = self::normalizeText($block['text']);
            return $block;
        });
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
        $default = \array_merge(self::$defaults['image'], array(
            'alt_text' => $altText, // max: 2000 chars
            'image_url' => $url,    // max: 3000 chars
            'title' => $title,      // max: 2000 chars
        ));
        return self::initBlock($default, $values, static function (array $block) {
            $block['title'] = self::normalizeText($block['title'], 'title');
            return $block;
        });
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
        $default = \array_merge(self::$defaults['input'], array(
            'element' => $element,
            'label' => array(
                'text' => $label,   // max: 2000 chars
                'type' => 'plain_text',
            ),
        ));
        return self::initBlock($default, $values, static function (array $block) {
            $block['label'] = self::normalizeText($block['label'], 'label');
            $block['hint'] = self::normalizeText($block['hint'], 'label');
            self::assertInputElement($block['element']);
            return $block;
        });
    }

    /**
     * Section block
     *
     * @param string|array $text      Text for the block
     * @param array|null   $fields    array of up to 10 text objects
     * @param array        $accessory optional block element
     * @param array        $values    Section block fields
     *
     * @return array<string,mixed>
     *
     * @throws InvalidArgumentException
     * @throws OverflowException
     * @throws UnexpectedValueException
     */
    public static function section($text, $fields = array(), $accessory = null, $values = array())
    {
        $default = \array_merge(self::$defaults['section'], array(
            'accessory' => $accessory,
            'fields' => $fields,    // max: 10,  each text's max: 2000 chars
            'text' => $text,        // optional text obj... defaults to mrkdown
                                    //   max: 3000 chars
        ));
        return self::initBlock($default, $values, static function (array $block) {
            /** @psalm-var array|null $block['fields'] */
            self::assertFields($block['fields'], 'section');
            $block['fields'] = \array_map(static function ($field) {
                return self::normalizeText($field, 'field', true);
            }, $block['fields'] ?: array());
            if (empty($block['fields'])) {
                unset($block['fields']);
            } elseif (\count($block['fields']) > 10) {
                throw new OverflowException(\sprintf(
                    'section block:  A maximum of 10 fields are allowed.  %d provided.',
                    \count($block['fields'])
                ));
            }
            $block['text'] = self::normalizeText($block['text'], 'text', true);
            if (isset($block['accessory'])) {
                self::assertAccessory($block['accessory']);
            }
            return $block;
        });
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
        $default = \array_merge(self::$defaults['video'], array(
            'alt_text' => $altText,
            'title' => $title,   // max: 199 chars
            'video_url' => $url, // must be https
        ));
        return self::initBlock($default, $values, static function (array $block) {
            $block['description'] = self::normalizeText($block['description'], 'description');
            return $block;
        });
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
     *
     * @throws UnexpectedValueException
     */
    public static function attachment($text, $blocks = array(), $values = array())
    {
        /*
            everything other than blocks and color are "legacy"
            Legacy fields are optional if you're including blocks as above.
            If you aren't, one of either fallback or text are required:
        */
        $default = \array_merge(self::$defaults['attachment'], array(
            'blocks' => $blocks, // An array of layout blocks in the same format as described here https://api.slack.com/reference/block-kit/blocks
            'text' => $text,
        ));
        return self::initBlock($default, $values, static function (array $attachment) {
            self::assertFields($attachment['fields'], 'attachment');
            $attachment['fields'] = self::attachmentFields($attachment['fields']) ?: null; // fields is optional
            return $attachment;
        });
    }

    /**
     * Prepare attachment fields
     *
     * @param array|null $fields Attachment fields
     *
     * @return list<array<string,mixed>>
     */
    private static function attachmentFields($fields)
    {
        $fieldsNew = array();
        $defaultField = array(
            'short' => false, // Indicates whether the field object is short enough to be displayed side-by-side with other field objects.
            'title' => null, // Shown as a bold heading displayed in the field object.
                             // It cannot contain markup and will be escaped for you.
            'value' => null, // The text value displayed in the field object.
                             // It can be formatted as plain text, or with mrkdwn by using the mrkdwn_in option above.
        );
        /** @psalm-var mixed $field */
        foreach ((array) $fields as $field) {
            if (\is_array($field) === false) {
                $field = array('value' => $field);
            }
            $field = \array_merge($defaultField, $field);
            /** @psalm-var array<string, mixed> psalm bug - should infer, but doesn't */
            $field = \array_intersect_key($field, $defaultField);
            $field = self::removeNull($field);
            if (\count($field) > 1) {
                // more than just 'short'
                $fieldsNew[] = $field;
            }
        }
        return $fieldsNew;
    }
}

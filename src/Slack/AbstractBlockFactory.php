<?php

/**
 * @package   bdk\slack
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Slack;

use OverflowException;
use UnexpectedValueException;

/**
 * Utilities/Helpers/Assertions for blocks
 */
abstract class AbstractBlockFactory
{
    use AssertionTrait;

    /** @var array<string,list<string>> */
    protected static $validElementTypes = array(
        'actions' => [
            'button',
            'checkboxes',
            'datepicker',
            'datetimepicker',
            'multi_static_select', 'static_select',
            'overflow',
            'radio_buttons',
            'timepicker',
        ],
        'context' => [
            'image',
            'mrkdwn',
            'plain_text',
        ],
    );

    /**
     * Normalize checkbox, radio, or select options
     *
     * @param array  $options checkbox, radio, overflow, or select block element
     * @param int    $max     Maximum number of allowed options
     * @param string $where   Name of element options belong to
     *
     * @return array
     *
     * @throws OverflowException
     * @throws UnexpectedValueException
     */
    protected static function normalizeOptions(array $options, $max, $where)
    {
        /** @psalm-var mixed $option */
        foreach ($options as $k => $option) {
            $options[$k] = self::normalizeOption($option, $k);
        }
        $count = \count($options);
        if ($count > $max) {
            throw new OverflowException(\sprintf('A maximum of %d options are allowed in %s element. %d provided.', $max, $where, $count));
        }
        return \array_values($options);
    }

    /**
     * Normalize a single option "object"
     *
     * Overflow, select, and multi-select menus can only use plain_text
     * Radio buttons and checkboxes can use mrkdwn text objects.
     *
     * @param mixed           $option option text/value
     * @param int|string|null $key    The option's array key...
     *                                   if a string, it will used as the option's text (label)
     *
     * @return array<string,mixed>
     *
     * @throws UnexpectedValueException
     */
    private static function normalizeOption($option, $key = null)
    {
        self::assertValidText($option, 'Option');
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
        $option['text'] = self::normalizeText($option['text'], 'text', $isMrkdwn);
        $option = \array_intersect_key($option, array(
            'description' => null,
            'text' => null,
            'url' => null, // only for overflow,
            'value' => null,
        ));
        return self::removeNull($option);
    }

    /**
     * Normalize text object
     *
     * @param mixed  $text     Text object value
     * @param string $what     What are we normalizing (for feedback)
     * @param bool   $isMrkdwn Is text formatted with markdown?
     *
     * @return array{emoji?:bool,text:string,type:'mrkdwn'|'plain_text',verbatim?:bool}|null
     *
     * @throws UnexpectedValueException
     */
    protected static function normalizeText($text, $what = 'text', $isMrkdwn = false)
    {
        self::assertValidText($text, $what);
        $default = array(
            'emoji' => true, // only applies to plain_text
            'text' => '',
            'type' => $isMrkdwn ? 'mrkdwn' : 'plain_text',
            'verbatim' => false, // only applies to mrkdwn
        );
        if (\is_array($text) === false) {
            $text = array('text' => $text);
        }
        $text = \array_merge($default, $text);
        $text = \array_intersect_key($text, $default);
        $text['text'] = (string) $text['text'];
        if ($text['text'] === '') {
            return null;
        }
        if ($text['type'] !== 'plain_text') {
            unset($text['emoji']);
        } elseif ($text['type'] !== 'mrkdwn') {
            unset($text['verbatim']);
        }
        /** @psalm-var array{emoji?:bool, text:string, type:'mrkdwn'|'plain_text', verbatim?:bool} psalm bug - should infer */
        return \array_diff_assoc($text, array(
            'emoji' => true,
            'verbatim' => false,
        ));
    }

    /**
     * Remove null values from array
     *
     * @param array<string,mixed> $values Input array
     *
     * @return array<string,mixed>
     */
    protected static function removeNull(array $values)
    {
        if (isset($values['focus_on_load']) && $values['focus_on_load'] === false) {
            unset($values['focus_on_load']);
        }
        $values = \array_filter($values, static function ($value) {
            return $value !== null;
        });
        \ksort($values);
        return $values;
    }

    /**
     * Rename 'default' to block-specific name
     *
     * @param array{type:string,...} $block block values
     *
     * @return array{type:string,...}
     */
    protected static function renameDefault(array $block)
    {
        $typeToDefault = array(
            'checkboxes' =>  'initial_options',
            'datepicker' => 'initial_date',
            'datetimepicker' => 'initial_date_time',
            'multi_static_select' => 'initial_options',
            'radio_buttons' => 'initial_option',
            'static_select' => 'initial_option',
            'timepicker' => 'initial_time',
        );
        $renameTo = isset($typeToDefault[$block['type']])
            ? $typeToDefault[$block['type']]
            : 'initial_value';
        if (isset($block['default'])) {
            /** @psalm-var mixed */
            $block[$renameTo] = $block['default'];
            unset($block['default']);
        }
        return $block;
    }
}

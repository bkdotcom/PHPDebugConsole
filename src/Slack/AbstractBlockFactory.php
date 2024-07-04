<?php

namespace bdk\Slack;

use InvalidArgumentException;
use LengthException;
use OverflowException;
use UnexpectedValueException;

/**
 * Utilities/Helpers/Assertions for blocks
 */
abstract class AbstractBlockFactory
{
    /** @var array<string,list<string>> */
    protected static $validElementTypes = array(
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

    /**
     * Assert valid section accessory
     *
     * @param mixed $accessory Accessory array definition
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *
     * @psalm-assert array{type:string} $accessory
     */
    protected static function assertAccessory($accessory)
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
        self::assertArrayWithType($accessory, 'accessory');
        if (\in_array($accessory['type'], $validAccessoryTypes, true) === false) {
            throw new InvalidArgumentException(\sprintf('Invalid accessory.  %s is an invalid type.', $accessory['type']));
        }
    }

    /**
     * Assert value is an array with a type value that is a string
     *
     * @param mixed           $value Value to assert
     * @param string          $what  thing we're testing (ie accessory or element)
     * @param string|int|null $index if in array, the index for feedback
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *
     * @psalm-assert array{type: string} $value
     */
    protected static function assertArrayWithType($value, $what, $index = null)
    {
        $prefix = $index !== null
            ? 'Invalid :what` (index :index).'
            : 'Invalid :what.';
        $replace = array(
            ':debugType' => self::getDebugType($value),
            ':index' => $index,
            ':what' => $what,
        );
        if (\is_array($value) === false) {
            throw new InvalidArgumentException(\strtr($prefix . '  :debugType provided.', $replace));
        }
        if (isset($value['type']) === false) {
            throw new InvalidArgumentException(\strtr($prefix . '  type not set.', $replace));
        }
        if (\is_string($value['type']) === false) {
            $replace[':debugType'] = self::getDebugType($value['type']);
            throw new InvalidArgumentException(\strtr($prefix . '  type must be a string.  :debugType provided.', $replace));
        }
    }

    /**
     * Assert valid actions/context elements
     *
     * @param mixed               $elements Value to test
     * @param 'actions'|'context' $where    'actions' or 'context'
     * @param int                 $max      Maximum number of elements allowed
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws LengthException
     * @throws OverflowException
     *
     * @psalm-assert array{type:string}[] $elements
     */
    protected static function assertElements($elements, $where = 'actions', $max = 10)
    {
        if (\is_array($elements) === false) {
            throw new InvalidArgumentException(\sprintf(
                '%s block:  elements must be array.  %s provided.',
                $where,
                self::getDebugType($elements)
            ));
        }
        \array_walk($elements, static function ($element, $index) use ($where) {
            $index = (string) $index;
            self::assertArrayWithType($element, 'element', $index);
            if (\in_array($element['type'], self::$validElementTypes[$where], true) === false) {
                throw new InvalidArgumentException(\sprintf(
                    '%s block:  Invalid element (index %s).  %s is an invalid type.',
                    $where,
                    $index,
                    $element['type']
                ));
            }
        });
        $elementCount = \count($elements);
        if ($elementCount === 0) {
            throw new LengthException(\sprintf('%s block:  At least one element is required.', $where));
        } elseif ($elementCount > $max) {
            throw new OverflowException(\sprintf('%s block:  A maximum of %d elements are allowed.  %d provided.', $where, $max, $elementCount));
        }
    }

    /**
     * Assert array or null
     *
     * @param mixed  $value Value to test
     * @param string $what  block type we're testing (ie section or attachment)
     *
     * @return void
     *
     * @throws UnexpectedValueException
     *
     * @psalm-assert array|null $fields
     */
    protected static function assertFields($value, $what)
    {
        if (\is_array($value) || $value === null) {
            return;
        }
        throw new UnexpectedValueException(\sprintf(
            '%s block:  fields must be array or null.  %s provided.',
            $what,
            self::getDebugType($value)
        ));
    }

    /**
     * Assert valid input block element
     *
     * @param mixed $element Input block element
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *
     * @psalm-assert array{type:string} $element
     */
    protected static function assertInputElement($element)
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
        self::assertArrayWithType($element, 'input block');
        if (\in_array($element['type'], $validElementTypes, true) === false) {
            throw new InvalidArgumentException(\sprintf('invalid input block.  %s is an invalid type.', $element['type']));
        }
    }

    /**
     * Can value be used for text value?
     *
     * @param mixed  $value Value to test
     * @param string $where Used in feedback
     *
     * @return void
     *
     * @throws UnexpectedValueException
     *
     * @psalm-assert string|numeric|null|array{text:string|numeric|null, ...<string,mixed>} $value
     */
    private static function assertValidText($value, $where)
    {
        if (\is_array($value)) {
            $value = isset($value['text'])
                ? $value['text']
                : null;
        }
        $isValid = \is_string($value) || \is_numeric($value) || $value === null;
        if ($isValid === false) {
            throw new UnexpectedValueException(\sprintf(
                '%s should be string, numeric, null, or array containing valid text value.  %s provided.',
                $where,
                self::getDebugType($value)
            ));
        }
    }

    /**
     * Gets the type name of a variable in a way that is suitable for debugging
     *
     * @param mixed $value The value being type checked
     *
     * @return string
     */
    protected static function getDebugType($value)
    {
        return \is_object($value)
            ? \get_class($value)
            : \gettype($value);
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

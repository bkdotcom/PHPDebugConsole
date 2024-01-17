<?php

namespace bdk\Slack;

use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Utilties/Helpers/Assertions for blocks
 */
abstract class AbstractBlockFactory
{
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
     * @param array<string,mixed> $accessory Accessory array definition
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected static function assertAccessory(array $accessory)
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
     * @param int    $max      Maximum number of elements allowed
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    protected static function assertElements(array $elements, $where = 'actions', $max = 10)
    {
        \array_walk($elements, static function ($element, $index) use ($where) {
            if (\is_array($element) === false || isset($element['type']) === false) {
                throw new InvalidArgumentException(\sprintf('Invalid element (index %s) provided for %s block', $index, $where));
            }
            if (\in_array($element['type'], self::$validElementTypes[$where], true) === false) {
                throw new InvalidArgumentException(\sprintf('%s (index %s) is an invalid %s element', $element['type'], $index, $where));
            }
        });
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
        if (\is_array($element) === false || isset($element['type']) === false) {
            throw new InvalidArgumentException('Invalid element provided for input block');
        }
        if (\in_array($element['type'], $validElementTypes, true) === false) {
            throw new InvalidArgumentException(\sprintf('%s is an invalid input type for input block element', $element['type']));
        }
    }

    /**
     * Normalize text object
     *
     * @param string|array|null $text     Text object value
     * @param bool              $isMrkdwn Is text formatted with markdown?
     *
     * @return array<string,string>|null
     */
    protected static function normalizeText($text, $isMrkdwn = false)
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
        $text = \array_intersect_key(\array_merge($default, $text), $default);
        $text['text'] = (string) $text['text'];
        if ($text['type'] !== 'plain_text') {
            unset($text['emoji']);
        }
        if ($text['type'] !== 'mrkdwn') {
            unset($text['verbatim']);
        }
        return $text['text'] === ''
            ? null
            : \array_diff_assoc($text, array(
                'emoji' => true,
                'verbatim' => false,
            ));
    }

    /**
     * Remove null values from array
     *
     * @param array $values Input array
     *
     * @return array
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
}

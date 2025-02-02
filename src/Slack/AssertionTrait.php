<?php

/**
 * @package   bdk\slack
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Slack;

use InvalidArgumentException;
use LengthException;
use OverflowException;
use UnexpectedValueException;

/**
 * Assertion methods
 */
trait AssertionTrait
{
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
        $validAccessoryTypes = [
            'image',
            'button',
            'checkboxes',
            'datepicker',
            'multi_static_select',
            'overflow',
            'radio_buttons',
            'static_select',
            'timePicker,',
        ];
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
     * Assert attachment count < maximum
     *
     * @param int $count Attachment count
     *
     * @return void
     *
     * @throws OverflowException
     */
    protected static function assertAttachmentCount($count)
    {
        if ($count > 20) {
            // according to slack message guidelines:
            // https://api.slack.com/reference/messaging/payload
            throw new OverflowException('A maximum of 20 message attachments are allowed.');
        }
    }

    /**
     * Assert data
     *
     * @param array<string,mixed> $values      Data values
     * @param array<string,mixed> $dataDefault Default values
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected static function assertData(array $values, array $dataDefault)
    {
        $unknownData = \array_diff_key($values, $dataDefault, \array_flip(['icon']));
        if ($unknownData) {
            throw new InvalidArgumentException('SlackMessage: Unknown values: ' . \implode(', ', \array_keys($unknownData)));
        }
        $values = \array_merge(array(
            'attachments' => array(),
            'blocks' => array(),
        ), $values);
        if (\is_array($values['attachments']) === false) {
            throw new InvalidArgumentException(\sprintf(
                'SlackMessage: attachments should be array or null,  %s provided.',
                self::getDebugType($values['attachments'])
            ));
        }
        if (\is_array($values['blocks']) === false) {
            throw new InvalidArgumentException(\sprintf(
                'SlackMessage: blocks should be array or null,  %s provided.',
                self::getDebugType($values['blocks'])
            ));
        }
        self::assertAttachmentCount(\count($values['attachments']));
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
            if (\in_array($element['type'], AbstractBlockFactory::$validElementTypes[$where], true) === false) {
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
        $validElementTypes = [
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
        ];
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
    protected static function assertValidText($value, $where)
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
            : \strtolower(\gettype($value));
    }
}

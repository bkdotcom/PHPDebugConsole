<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams;

use bdk\Teams\CardUtilityTrait;
use bdk\Teams\ItemInterface;
use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;

/**
 * The base object off of which Elements and Actions are built
 */
class AbstractItem implements ItemInterface
{
    use CardUtilityTrait;

    /** @var string Element type */
    protected $type;

    /** @var array<string,mixed> */
    protected $fields = array();

    /**
     * Constructor
     *
     * @param array<string,mixed> $fields Field values
     * @param string              $type   Item type
     */
    public function __construct(array $fields = array(), $type = 'Unknown')
    {
        $this->fields = \array_merge($this->fields, $fields);
        $this->type = $type;
    }

    /**
     * Assert that a value is of a certain type
     *
     * Utility / internal method... public for testing
     *
     * Support extreme range of PHP versions : 5.4 - 8.4 (and beyond)
     * `MyObj $obj = null` has been deprecated in PHP 8.4
     * must now be `?MyObj $obj = null` (which is a php 7.1 feature)
     * Workaround - remove type-hint when we allow null (not ideal) and call assertType
     * When we drop support for php < 7.1, we can remove this method and do proper type-hinting
     *
     * @param mixed  $value     Value to test
     * @param string $type      "array", "callable", "object", or className
     * @param string $paramName (optional) parameter name
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public static function assertType($value, $type, $paramName = null)
    {
        if (self::assertTypeCheck($value, $type)) {
            return;
        }
        $frame = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $params = array(
            '{actual}' => self::getDebugType($value),
            '{expect}' => $type,
            '{method}' => isset($frame['class'])
                ? $frame['class'] . '::' . $frame['function'] . '()'
                : $frame['function'] . '()',
            '{param}' => '$' . $paramName,
        );
        $msg = $paramName
            ? '{method}: {param} expects {expect}.  {actual} provided'
            : '{method} expects {expect}.  {actual} provided';
        throw new InvalidArgumentException(\strtr($msg, $params));
    }

    /**
     * Return attribute/property value
     *
     * @param string $name Attribute name
     *
     * @return mixed
     *
     * @throws OutOfBoundsException
     */
    public function get($name)
    {
        if ($name === 'type') {
            return $this->type;
        }
        if (\array_key_exists($name, $this->fields)) {
            return $this->fields[$name];
        }
        throw new OutOfBoundsException(\sprintf(
            '%s does not have %s property',
            $this->type,
            $name
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        return array(
            'type' => $this->type,
        );
    }

    /**
     * Test if value is of a certain type
     *
     * @param mixed  $value Value to test
     * @param string $type  php type(s) to check
     *
     * @return bool
     */
    private static function assertTypeCheck($value, $type)
    {
        $types = ['array', 'bool', 'callable', 'float', 'int', 'null', 'numeric', 'object', 'string'];
        foreach (\explode('|', $type) as $type) {
            $method = 'is_' . $type;
            $isType = \in_array($type, $types, true)
                ? $method($value)
                : \is_a($value, $type);
            if ($isType) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return new instance with updated property value
     *
     * @param string $name  attribute/property name
     * @param mixed  $value new value
     *
     * @return static
     */
    protected function with($name, $value)
    {
        $new = clone $this;
        $new->fields[$name] = $value;
        return $new;
    }

    /**
     * Return new instance with value appended to array
     *
     * @param string $name  Name of array attribute
     * @param mixed  $value Value to append
     *
     * @return static
     *
     * @throws LogicException
     */
    protected function withAdded($name, $value)
    {
        $new = clone $this;
        if (\is_array($new->fields[$name]) === false) {
            throw new LogicException(\sprintf(
                '%s :  unable to add additional %s',
                $this->type,
                $name
            ));
        }
        $new->fields[$name][] = $value;
        return $new;
    }
}

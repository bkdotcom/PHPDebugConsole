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
     * Assert that a value is of a certain type
     *
     * Support extreme range of PHP versions : 5.4 - 8.4 (and beyond)
     * `MyObj $obj = null` has been deprecated in PHP 8.4
     * must now be `?MyObj $obj = null` (which is a php 7.1 feature)
     * Workaround - remove type-hint when we allow null (not ideal) and call assertType
     * When we drop support for php < 7.1, we can remove this method and do proper type-hinting
     *
     * @param mixed  $value     Value to test
     * @param string $type      "array", "callable", "object", or className
     * @param bool   $allowNull (true) allow null?
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected static function assertType($value, $type, $allowNull = true)
    {
        if ($allowNull && $value === null) {
            return;
        }
        if (self::assertTypeCheck($value, $type)) {
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            'Expected %s%s, got %s',
            $type,
            $allowNull ? ' (or null)' : '',
            self::getDebugType($value)
        ));
    }

    /**
     * Test if value is of a certain type
     *
     * @param mixed  $value Value to test
     * @param string $type  "array", "callable", "object", or className
     *
     * @return bool
     */
    private static function assertTypeCheck($value, $type)
    {
        // For teams we don't need 'array', 'callable', or 'object' tests
        switch ($type) {
            case 'array':
                return \is_array($value);
            case 'callable':
                return \is_callable($value);
            case 'object':
                return \is_object($value);
            default:
                return \is_a($value, $type);
        }
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

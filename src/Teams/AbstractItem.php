<?php

namespace bdk\Teams;

use bdk\Teams\CardUtilityTrait;
use bdk\Teams\ItemInterface;
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

    /** @var array<string, mixed> */
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

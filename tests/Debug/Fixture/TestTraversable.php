<?php

namespace bdk\Test\Debug\Fixture;

/**
 * I implement Traversable!
 */
class TestTraversable implements \IteratorAggregate
{
    private $data = array();

    /**
     * Constructor
     *
     * @param array $data data that will be traversable
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Implements IteratorAggregate
     *
     * @return ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }
}

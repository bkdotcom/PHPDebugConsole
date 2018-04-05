<?php

namespace bdk\DebugTest;

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
     * Implements Traversable
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }
}

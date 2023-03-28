<?php

namespace bdk\Test\CurlHttpMessage\Fixture;

class JsonSerializable implements \JsonSerializable
{
    private $data = array();

    public function __construct(array $data = array())
    {
        $this->data = $data;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->data;
    }
}

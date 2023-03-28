<?php

namespace bdk\Test\Teams\Fixture;

class Stringable
{
    private $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function __toString()
    {
        return $this->message;
    }
}

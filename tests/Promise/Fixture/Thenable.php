<?php

namespace bdk\Test\Promise\Fixture;

use bdk\Promise;

class Thenable
{
    private $nextPromise = null;

    public function __construct()
    {
        $this->nextPromise = new Promise();
    }

    public function then($resolve = null, $reject = null)
    {
        return $this->nextPromise->then($resolve, $reject);
    }

    public function resolve($value)
    {
        $this->nextPromise->resolve($value);
    }
}

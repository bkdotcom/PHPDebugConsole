<?php

namespace bdk\Test\Promise\Fixture;

use bdk\Promise;

class ExtendsPromise extends Promise
{
    private $nextPromise = null;

    public function __construct()
    {
        $this->nextPromise = new Promise();
    }

    public function then($res = null, $rej = null)
    {
        return $this->nextPromise->then($res, $rej);
    }

    public function otherwise(callable $onRejected)
    {
        return $this->then(null, $onRejected);
    }

    public function resolve($value)
    {
        $this->nextPromise->resolve($value);
    }

    public function reject($reason)
    {
        $this->nextPromise->reject($reason);
    }

    public function wait($unwrap = true)
    {
    }

    public function cancel()
    {
    }

    public function getState()
    {
        return $this->nextPromise->getState();
    }
}

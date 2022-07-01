<?php

namespace bdk\Test\Container\Fixture;

class Invokable
{
    public function __invoke($value = null)
    {
        $service = new Service();
        $service->value = $value;

        return $service;
    }

    public function __call($method, $args)
    {
    }

    public static function __callStatic($method, $args)
    {
    }
}

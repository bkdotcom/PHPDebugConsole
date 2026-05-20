<?php

namespace bdk\Test\Proxy\Fixture;

use RuntimeException;

class Widget implements WidgetInterface
{
    public $value = 'default';

    private $values = array();

    public function __construct($values = array())
    {
        $this->values = $values;
    }

    public function __get($name)
    {
        return isset($this->values[$name])
            ? $this->values[$name]
            : null;
    }

    public function __set($name, $value)
    {
        $this->values[$name] = $value;
    }

    public function test($param)
    {
        return $this->encode($param);
    }

    public function getInstance()
    {
        return $this;
    }

    public static function factory()
    {
        return new self();
    }

    public function broken()
    {
        throw new RuntimeException('Not working currently.');
    }

    protected function apiMethod()
    {
    }

    private function encode($value)
    {
        return \json_encode($value);
    }
}

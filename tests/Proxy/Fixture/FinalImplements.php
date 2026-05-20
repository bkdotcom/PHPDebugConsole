<?php

namespace bdk\Test\Proxy\Fixture;

use bdk\Test\Proxy\Fixture\WidgetInterface;

final class FinalImplements implements WidgetInterface
{
    private $values = array();

    public function __construct($values = array())
    {
        $this->values = $values;
    }

    public function test($param = null)
    {
        return $param;
    }
}

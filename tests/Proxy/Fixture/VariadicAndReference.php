<?php

namespace bdk\Test\Proxy\Fixture;

class VariadicAndReference
{
    public static function byRef(&$paramByRef)
    {
        $paramByRef = 'modified ' . \json_encode($paramByRef);
    }

    public function variadic(...$params)
    {
        return $params;
    }

    public function variadicByRef(&...$params)
    {
        foreach ($params as &$param) {
            $param = 'modified ' . \json_encode($param);
        }
        return $params;
    }
}

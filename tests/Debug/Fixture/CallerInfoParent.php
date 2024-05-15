<?php

namespace bdk\Test\Debug\Fixture;

class CallerInfoParent
{
    function extendMe()
    {
        \bdk\Debug::group();
        \bdk\Debug::groupEnd();
    }

    function inherited()
    {
        \bdk\Debug::group();
        \bdk\Debug::groupEnd();
    }

    public static function staticParent()
    {
        \bdk\Debug::group();
        \bdk\Debug::groupEnd();
    }

    public function sensitiveParam(
        #[\SensitiveParameter]
        $secret,
        $sauce
    ) {
        \bdk\Debug::group();
        \bdk\Debug::groupEnd();
    }
}

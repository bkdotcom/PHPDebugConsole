<?php

namespace bdk\Test\Debug\Fixture;

class CallerInfoParent
{
    function extendMe()
    {
        \bdk\Debug::_group();
        \bdk\Debug::_groupEnd();
    }

    function inherited()
    {
        \bdk\Debug::_group();
        \bdk\Debug::_groupEnd();
    }

    public static function staticParent()
    {
        \bdk\Debug::_group();
        \bdk\Debug::_groupEnd();
    }

    public function sensitiveParam(
        #[\SensitiveParameter]
        $secret,
        $sauce
    ) {
        \bdk\Debug::_group();
        \bdk\Debug::_groupEnd();
    }
}

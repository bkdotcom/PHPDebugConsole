<?php

namespace bdk\Test\Debug\Fixture;

class CallerInfoChild extends CallerInfoParent
{
    function extendMe()
    {
        \bdk\Debug::_group();
        parent::extendMe();
        \bdk\Debug::_groupEnd();
    }
}

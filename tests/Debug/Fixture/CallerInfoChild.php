<?php

namespace bdk\Test\Debug\Fixture;

class CallerInfoChild extends CallerInfoParent
{
    function extendMe()
    {
        \bdk\Debug::group();
        parent::extendMe();
        \bdk\Debug::groupEnd();
    }
}

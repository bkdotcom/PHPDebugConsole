<?php

namespace bdk\Test\Debug\Fixture;

class CallerInfoChild extends CallerInfoParent
{
    public function extendMe()
    {
        \bdk\Debug::group();
        parent::extendMe();
        \bdk\Debug::groupEnd();
    }
}

<?php

namespace bdk\Test\Backtrace\Fixture\SkipMe;

use bdk\Backtrace\Xdebug;

class Thing
{
    public function a()
    {
        $this->b();
    }

    public function b()
    {
        $this->c();
    }

    public function c()
    {
        $GLOBALS['xdebug_trace'] = Xdebug::getFunctionStack();
        $GLOBALS['debug_backtrace'] = \debug_backtrace();
    }
}

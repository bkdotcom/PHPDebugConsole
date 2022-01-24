<?php

namespace bdk\Test\Debug\Fixture;

use bdk\Debug;

class ScopeTest
{
    public function callsDebug()
    {
        // abstraction's 'scopeClass' value should be bdk\Test\Debug\Fixture\ScopeTest
        Debug::_log($this);
    }
}

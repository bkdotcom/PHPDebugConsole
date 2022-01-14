<?php

namespace bdk\DebugTests\Fixture;

use bdk\Debug;

class ScopeTest
{
    public function callsDebug()
    {
        // abstraction's 'scopeClass' value should be bdk\DebugTests\Fixture\ScopeTest
        Debug::_log($this);
    }
}

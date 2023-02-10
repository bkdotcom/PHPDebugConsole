<?php

namespace bdk\Test\Debug\Fixture;

use bdk\Debug;

class ObjectScope
{
    public function callsDebug()
    {
        // abstraction's 'scopeClass' value should be bdk\Test\Debug\Fixture\ScopeTest
        Debug::_log($this);
    }
}

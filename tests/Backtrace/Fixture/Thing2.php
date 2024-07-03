<?php

namespace bdk\Test\Backtrace\Fixture;

use bdk\Backtrace\Xdebug;
use bdk\Test\Backtrace\Fixture\SkipMe\Thing;

class Thing2 extends Thing
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

<?php

namespace bdk\Test\Backtrace\Fixture;

use bdk\Backtrace\Xdebug;

class Magic
{
    public $trace;

    public function __call($method, $args)
	{
        $GLOBALS['xdebug_trace'] = Xdebug::getFunctionStack();
        $GLOBALS['debug_backtrace'] = \debug_backtrace();
        $this->trace = \bdk\Backtrace::get();
        return $args;
	}

	public function __get($name)
	{
		$GLOBALS['xdebug_stack'] = Xdebug::getFunctionStack();
		return $name;
	}

	private function secret()
	{
        $GLOBALS['xdebug_trace'] = Xdebug::getFunctionStack();
        $GLOBALS['debug_backtrace'] = \debug_backtrace();
	}
}

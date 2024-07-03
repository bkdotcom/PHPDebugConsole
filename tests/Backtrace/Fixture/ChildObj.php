<?php

namespace bdk\Test\Backtrace\Fixture;

class ChildObj extends ParentObj
{
	public function extendMe()
	{
		self::$callerInfoStack[] = \bdk\Backtrace::getCallerInfo();
		parent::extendMe();
	}

	public static function extendMeStatic()
	{
		self::$callerInfoStack[] = \bdk\Backtrace::getCallerInfo();
		parent::extendMeStatic();
	}
}

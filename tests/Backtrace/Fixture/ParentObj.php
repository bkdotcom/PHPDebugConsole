<?php

namespace bdk\Test\Backtrace\Fixture;

class ParentObj
{
    public static $callerInfoStack = array();

    public function extendMe()
    {
        self::$callerInfoStack[] = \bdk\Backtrace::getCallerInfo();
        $this->someMethod();
    }

    public static function extendMeStatic()
    {
        self::$callerInfoStack[] = \bdk\Backtrace::getCallerInfo();
        self::someMethod();
    }

    public function inherited()
    {
        self::$callerInfoStack[] = \bdk\Backtrace::getCallerInfo();
        $this->someMethod();
    }

    public static function inheritedStatic()
    {
        self::$callerInfoStack[] = \bdk\Backtrace::getCallerInfo();
        self::someMethod();
    }

    protected function someMethod()
    {
        self::$callerInfoStack[] = \bdk\Backtrace::getCallerInfo();
    }
}

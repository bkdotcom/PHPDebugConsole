<?php

namespace bdk\Test\Debug\Mock;

class Backtrace
{
    private static $return = array();

    public static function addInternalClass($classes, $level = 0)
    {
    }

    public static function get()
    {
        return self::$return;
    }

    public static function getCallerInfo()
    {
        return self::$return;
    }

    public static function getFileLines($file, $start = 1, $length = null)
    {
        return \bdk\Backtrace::getFileLines($file, $start, $length);
    }

    public static function setReturn($return)
    {
        self::$return = $return;
    }
}

<?php

namespace bdk\Test\Debug\Mock;

class Backtrace
{
    private static $return = array();

    public static function get()
    {
        return self::$return;
    }

    public static function getCallerInfo()
    {
        return self::$return;
    }

    public static function setReturn($return)
    {
        self::$return = $return;
    }
}

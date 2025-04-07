<?php

namespace bdk\Test\Debug\Mock;

class Backtrace extends \bdk\Backtrace
{
    private static $return = array();

    public static function addInternalClass($classes, $level = 0)
    {
    }

    public static function get($options = 0, $limit = 0, $exception = null)
    {
        return self::$return;
    }

    public static function getCallerInfo($offset = 0, $options = 0)
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

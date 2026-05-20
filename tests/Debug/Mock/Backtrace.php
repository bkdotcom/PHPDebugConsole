<?php

namespace bdk\Test\Debug\Mock;

class Backtrace extends \bdk\Backtrace
{
    private static $returnVals = array(
        'get' => array(),
        'getCallerInfo' => array(),
    );

    public static function addInternalClass($classes, $level = 0)
    {
    }

    public static function get($options = 0, $limit = 0, $exception = null)
    {
        return self::$returnVals['get'];
    }

    public static function getCallerInfo($offset = 0, $options = 0)
    {
        return \array_merge(array(
            'class' => null,         // where the method is defined
            'classCalled' => null,   // parent::method()... this will be the parent class
            'classContext' => null,  // child->method()
            'evalLine' => null,
            'file' => null,
            'function' => null,
            'line' => null,
            'type' => null,
        ), self::$returnVals['getCallerInfo']);
    }

    public static function getFileLines($file, $start = 1, $length = null)
    {
        return \bdk\Backtrace::getFileLines($file, $start, $length);
    }

    public static function setReturn($return, $method = 'get')
    {
        self::$returnVals[$method] = $return;
    }
}

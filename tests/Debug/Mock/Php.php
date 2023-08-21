<?php

namespace bdk\Test\Debug\Mock;

use bdk\Debug\Utility\Php as PhpBase;

class Php extends PhpBase
{
    public static $memoryLimit = null;
    public static $iniFiles = array();

    /**
     * {@inheritDoc}
     */
    public static function getIniFiles()
    {
        return empty(self::$iniFiles) === false
            ? self::$iniFiles
            : parent::getIniFiles();
    }

    /**
     * {@inheritDoc}
     */
    public static function memoryLimit()
    {
        return self::$memoryLimit !== null
            ? self::$memoryLimit
            : parent::memoryLimit();
    }
}

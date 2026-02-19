<?php

namespace bdk\Test\Debug\Mock;

use bdk\Debug\Utility as UtilityBase;

class Utility extends UtilityBase
{
    public static $gitBranch = null;

    public static $filePaths = [];

    /**
     * {@inheritDoc}
     */
    public static function gitBranch($dir = null)
    {
        return self::$gitBranch !== null
            ? self::$gitBranch
            : parent::gitBranch();
    }

    /**
     * {@inheritDoc}
     */
    public static function isFile($val)
    {
        return parent::isFile($val) || \in_array($val, self::$filePaths, true);
    }
}

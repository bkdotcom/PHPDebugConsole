<?php

namespace bdk\Test\Debug\Mock;

use bdk\Debug\Utility as UtilityBase;

class Utility extends UtilityBase
{
    public static $gitBranch = null;

    /**
     * {@inheritDoc}
     */
    public static function gitBranch($dir = null)
    {
        return self::$gitBranch !== null
            ? self::$gitBranch
            : parent::gitBranch();
    }
}

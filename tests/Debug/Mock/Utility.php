<?php

namespace bdk\Test\Debug\Mock;

use bdk\Debug\Utility as UtilityBase;

class Utility extends UtilityBase
{
    public static $gitBranch = null;

    /**
     * Get current git branch
     *
     * @return string|null
     */
    public static function gitBranch()
    {
        return self::$gitBranch;
    }
}

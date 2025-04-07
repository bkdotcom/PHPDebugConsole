<?php

namespace bdk\Test\Container\Fixture;

use stdClass as stdClassAlias;

class ResolvableConstructorPhpDoc
{
    public $dependency1;
    public $dependency2;
    public $dependency3;
    public $int;

    /**
     * @param array|stdClassAlias                           $dependency1 find in use statement
     * @param UnresolvableConstructor|ResolvableConstructor $dependency2 no use statement, find in current namespace
     * @param \bdk\Test\Container\Fixture\Service           $dependency3 fully qualified path
     * @param int                                           $int         we'll use default
     */
    public function __construct($dependency1, $dependency2, $dependency3 = null, $int = 42)
    {
        $this->dependency1 = $dependency1;
        $this->dependency2 = $dependency2;
        $this->dependency3 = $dependency3;
        $this->int = $int;
    }
}

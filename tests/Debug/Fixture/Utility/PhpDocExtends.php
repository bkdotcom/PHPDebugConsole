<?php

namespace bdk\Test\Debug\Fixture\Utility;

use bdk\Test\Debug\Fixture\Utility\PhpDocImplements;

/**
 * {@inheritDoc}
 */
class PhpDocExtends extends PhpDocImplements
{
    /**
     * {@inheritDoc}
     */
    const SOME_CONSTANT = 'never change';

    /**
     * {@inheritDoc}
     */
    public $someProperty = 'St. James Place';

    /**
     * {@inheritDoc}
     */
    public function someMethod()
    {
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function someMethod2()
    {
    }

    /**
     * PhpDocExtends summary
     *
     * PhpDocExtends desc / {@inheritDoc}
     */
    public function someMethod3()
    {
    }
}

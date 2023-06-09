<?php

namespace bdk\Test\Debug\Fixture\Utility;

use bdk\Test\Debug\Fixture\SomeInterface;

/**
 * {@inheritDoc}
 */
class PhpDocImplements implements SomeInterface
{
    /**
     * PhpDocImplements summary
     */
    const SOME_CONSTANT = 'never change';

    /**
     * $someProperty summary
     *
     * @var string $someProperty desc
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
     * @return \Ding\Dang
     */
    public function someMethod2()
    {
    }

    /**
     * PhpDocImplements summary
     *
     * PhpDocImplements desc
     */
    public function someMethod3()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function someMethod4()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function someMethod5()
    {
    }
}

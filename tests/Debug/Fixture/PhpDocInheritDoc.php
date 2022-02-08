<?php

namespace bdk\Test\Debug\Fixture;

/**
 * {@inheritDoc}
 */
class PhpDocInheritDoc implements SomeInterface
{
    /** @var string constant description */
    const SOME_CONSTANT = 'never change';

    /** @var string property description */
    public $someProperty = 'St. James Place';

    /**
     * {@inheritDoc}
     */
    public function someMethod()
    {
    }
}

<?php

namespace bdk\Test\Debug\Fixture;

\define('WORD', 'swordfish');

/**
 * Test
 *
 * {@inheritDoc}
 *
 * \@notatag make sure this isn't interpreted as a tag
 */
class Test2 extends Test2Base
{
    /**
     * {@inheritDoc}
     */
    protected $magicReadProp = 'not null';
}

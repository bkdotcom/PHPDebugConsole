<?php

namespace bdk\Test\ErrorHandler\Fixture;

use bdk\ErrorHandler\AbstractComponent;

class ExtendsComponent extends AbstractComponent
{

    protected $cfg = array(
        'doMagic' => true,
    );

    protected $readOnly = array(
        'foo'
    );

    protected $foo = 'bar';
}

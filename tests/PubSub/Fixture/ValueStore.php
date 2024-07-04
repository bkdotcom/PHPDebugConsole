<?php

namespace bdk\Test\PubSub\Fixture;

use bdk\PubSub\ValueStore as ValueStoreBase;

class ValueStore extends ValueStoreBase
{
    public $onSetArgs = array();

    protected function getFoo()
    {
        return 'bar';
    }

    protected function isGroovy()
    {
        return true;
    }

    protected function onSet($values = array())
    {
        $this->onSetArgs[] = $values;
    }
}

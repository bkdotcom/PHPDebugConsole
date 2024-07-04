<?php

namespace bdk\Test\PubSub\Fixture;

use bdk\PubSub\Event;

class Subscriber
{
    public $preFooInvoked = 0;
    public $postFooInvoked = 0;

    public $name;

    /*
        Subscribe methods
    */

    public function preFoo(Event $e)
    {
        $this->preFooInvoked++;
    }

    public function postFoo(Event $e)
    {
        $this->postFooInvoked++;

        $e->stopPropagation();
    }

    public function test(Event $e)
    {
    }
}

<?php

namespace bdk\Test\PubSub\Fixture;

use bdk\PubSub\SubscriberInterface;

class SubscriberInterfaceWithPriorities implements SubscriberInterface
{
    public function getSubscriptions()
    {
        return array(
            'pre.foo' => array('preFoo', 10),
            'post.foo' => array('postFoo'),
        );
    }
}

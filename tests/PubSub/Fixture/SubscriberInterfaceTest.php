<?php

namespace bdk\Test\PubSub\Fixture;

use bdk\PubSub\SubscriberInterface;

class SubscriberInterfaceTest implements SubscriberInterface
{
    public $getSubscriptionsReturn = array(
        'pre.foo' => 'preFoo',
        'post.foo' => 'postFoo',
    );

    public function getSubscriptions()
    {
        return $this->getSubscriptionsReturn;
    }
}

<?php

namespace bdk\Test\Debug\Fixture;

use bdk\PubSub\SubscriberInterface;

class Subscriber implements SubscriberInterface
{
    protected $subscriptions = array();

    public function __construct(array $subscriptions = array())
    {
        $this->setSubscriptions($subscriptions);
    }

    /**
     * {@inheritDoc}
     *
     * @return [type] [description]
     */
    public function getSubscriptions()
    {
        return $this->subscriptions;
    }

    public function setSubscriptions(array $subscriptions = array())
    {
        $this->subscriptions = $subscriptions;
    }
}

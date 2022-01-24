<?php

namespace bdk\Test\Debug\Mock;

use bdk\WampPublisher as BaseWampPublisher;

class WampPublisher extends BaseWampPublisher
{
    public $connected = true;
    public $messages = array();

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Publish to topic
     *
     * @param string $topic   topic
     * @param array  $args    arguments
     * @param array  $options options
     *
     * @return void
     */
    public function publish($topic, $args = array(), $options = array())
    {
        $this->messages[] = array(
            'topic' => $topic,
            'args' => $args,
            'options' => $options,
        );
    }
}

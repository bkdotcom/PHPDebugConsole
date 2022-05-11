<?php

/**
 * This file is part of bdk\PubSub
 *
 * @package   bdk\PubSub
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v2.4
 * @link      http://www.github.com/bkdotcom/PubSub
 */

namespace bdk\PubSub;

interface SubscriberInterface
{
    /**
     * Return a list of event subscribers
     *
     * Returns an array of event names this plugin subscribes to.
     *
     * The array keys are event names and the value can be:
     *
     *  string:  method name to call (priority defaults to 0)
     *  array(string methodName, int priority)
     *  array of methodName and/or array(methodName, priority)
     *
     * @return array The event names to subscribe to
     */
    public function getSubscriptions();
}

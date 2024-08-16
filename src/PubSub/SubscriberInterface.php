<?php

/**
 * This file is part of bdk\PubSub
 *
 * @package   bdk\PubSub
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v2.4
 * @link      http://www.github.com/bkdotcom/PubSub
 */

namespace bdk\PubSub;

interface SubscriberInterface
{
    /**
     * Return event subscribers
     *
     * The array keys are event names and the value can be:
     *
     *  _method_: priority defaults to 0, onlyOnce defaults to false
     *  array: (required) _method_, (optional) int priority, (optional) bool onlyOnce)
     *  array: any combination of the above
     *
     *  _method_ = string|Callable name of public method or `Closure`
     *
     * @return array<string,string|array>
     */
    public function getSubscriptions();
}

<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Framework\Laravel;

use bdk\Debug\Framework\Laravel\ServiceProvider;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as BaseEventServiceProvider;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * EventServiceProvider
 */
class EventServiceProvider extends BaseEventServiceProvider
{
    protected $listen = [
        KernelEvents::REQUEST => [
            array(ServiceProvider::class, 'onRequest'),
        ],
    ];
}

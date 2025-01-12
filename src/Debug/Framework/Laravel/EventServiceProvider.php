<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
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
            [ServiceProvider::class, 'onRequest'],
        ],
    ];
}

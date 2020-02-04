<?php

namespace bdk\Debug;

use Composer\Script\Event;

/**
 * Composer scripts
 *
 * @see https://getcomposer.org/doc/articles/scripts.md
 */
class ComposerScripts
{

    /**
     * Require slevomat/coding-standard if dev mode & PHP >= 7.1
     *
     * @param Event $event Composer event instance
     *
     * @return void
     */
    public static function postInstall(Event $event)
    {
        if ($event->isDevMode() && \version_compare(PHP_VERSION, '7.1', '>=')) {
            \exec('composer require slevomat/coding-standard --dev');
        }
    }
}

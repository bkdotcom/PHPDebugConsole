<?php

/**
 * This file is used to test that bdk\PubSub\Manager::EVENT_PHP_SHUTDOWN is emitted
 */

use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;

require __DIR__ . '/../../vendor/autoload.php';

$eventManager = new EventManager();
$eventManager->subscribe(EventManager::EVENT_PHP_SHUTDOWN, static function (Event $event, $eventName, $manager) {
    echo \sprintf(
        'shutdown: %s %s %s' . "\n",
        \is_object($event) ? \get_class($event) : \gettype($event),
        $eventName,
        \is_object($manager) ? \get_class($manager) : \gettype($manager)
    );
});

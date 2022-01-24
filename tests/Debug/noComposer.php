<?php

/**
 * This file is used to test that we're able to bootstrap Debug sans Composer
 */

require __DIR__ . '/../../src/Debug/Autoloader.php';
$autoloader = new \bdk\Debug\Autoloader();
$autoloader->register();

require __DIR__ . '/../../src/Debug/Debug.php';

$debug = new \bdk\Debug(array(
    'collect' => true,
    'output' => false,
));

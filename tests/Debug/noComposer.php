<?php

/**
 * This file is used to test that we're able to bootstrap Debug sans Composer
 */

require __DIR__ . '/../../src/Debug/Autoloader.php';
$autoloader = new \bdk\Debug\Autoloader();
$autoloader->register();
$autoloader->addPsr4('bdk\\HttpMessage\\', __DIR__ . '/../../vendor/bdk/http-message/src/HttpMessage');
$autoloader->addPsr4('Psr\\Http\\Message\\', __DIR__ . '/../../vendor/psr/http-message/src');

$debug = new \bdk\Debug(array(
    'collect' => true,
    'output' => false,
));

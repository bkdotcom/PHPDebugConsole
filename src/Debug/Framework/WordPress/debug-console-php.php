<?php

/**
 * Plugin Name: Debug Console for PHP
 * Plugin URI: https://bradkent.com/php/debug
 * Description: Display query, cache, and other helpful debugging information.  Provides new logging / debugging / inspecting / error-notification functionality
 * Text Domain: debug-console-php
 * Author: Brad Kent
 * Author URI: https://bradkent.com
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Version: 3.5
 * Requires PHP: 7.0
 */

if (\defined('ABSPATH') === false) {
    exit;
}

require __DIR__ . '/vendor/bdk/Debug/Autoloader.php';
$autoloader = new \bdk\Debug\Autoloader();
$autoloader->addPsr4('bdk\\Debug\\Framework\\WordPress\\', __DIR__ . '/src');
$autoloader->register();

new \bdk\Debug\Framework\WordPress\Plugin(__FILE__);

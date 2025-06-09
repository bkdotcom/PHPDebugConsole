<?php

/**
 * Plugin Name: PhpDebugConsole
 * Plugin URI: https://bradkent.com/php/debug
 * Description: Display query, cache, and other helpful debugging information.  Provides new logging / debugging / inspecting / error-notification functionality
 * Author: Brad Kent
 * Author URI: https://bradkent.com
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Requires PHP: 5.4
 */

// determine where we are - PHPDebugConsole root or FrameWork/WordPress
 $path = \realpath(__DIR__);
 $pathBase = \strpos($path, 'Framework' . DIRECTORY_SEPARATOR . 'WordPress')
    ? $path . '/../../../..'
    : $path;

require $pathBase . '/src/Debug/Autoloader.php';
$autoloader = new \bdk\Debug\Autoloader();
$autoloader->addPsr4('Psr\\Http\\Message\\', $pathBase . '/vendor/psr/http-message/src');
$autoloader->addPsr4('bdk\\HttpMessage\\', $pathBase . '/vendor/bdk/http-message/src/HttpMessage');
$autoloader->addClass('SqlFormatter', $pathBase . '/vendor/jdorn/sql-formatter/lib/SqlFormatter.php');
$autoloader->register();

$storedOptions = \get_option(\bdk\Debug\Framework\WordPress\Settings::GROUP_NAME) ?: array(
    'emailTo' => \get_option('admin_email'),
    'i18n' => array(
        'localeFirstChoice' => \get_locale(),
    ),
    'logEnvInfo' => array(
        'session' => false,
    ),
);

$config = \bdk\Debug\Utility\ArrayUtil::mergeDeep(
    array(
        'collect' => true,  // start with collection on as we bootstrap
        'emailFunc' => 'wp_mail',
        'plugins' => array(
            'routeDiscord' => array(
                'class' => 'bdk\Debug\Route\Discord',
                'enabled' => false,
            ),
            'routeSlack' => array(
                'class' => 'bdk\Debug\Route\Slack',
                'enabled' => false,
            ),
            'routeTeams' => array(
                'class' => 'bdk\Debug\Route\Teams',
                'enabled' => false,
            ),
            'wordpress' => array( 'class' => 'bdk\Debug\Framework\WordPress\WordPress' ),
            'wordpressCache' => array( 'class' => 'bdk\Debug\Framework\WordPress\ObjectCache' ),
            'wordpressDb' => array( 'class' => 'bdk\Debug\Framework\WordPress\WpDb' ),
            'wordpressDeprecated' => array( 'class' => 'bdk\Debug\Framework\WordPress\Deprecated' ),
            'wordpressHooks' => array( 'class' => 'bdk\Debug\Framework\WordPress\Hooks' ),
            'wordpressHttp' => array( 'class' => 'bdk\Debug\Framework\WordPress\WpHttp' ),
            'wordpressSettings' => array(
                'class' => 'bdk\Debug\Framework\WordPress\Settings',
                'pluginFile' => \plugin_basename(__FILE__),
            ),
        ),
    ),
    $storedOptions
);

foreach (['routeDiscord', 'routeSlack', 'routeTeams'] as $name) {
    $enabled = !empty($config['plugins'][$name]['enabled']);
    if ($enabled === false) {
        unset($config['plugins'][$name]);
    }
}

$debug = new \bdk\Debug($config);

\add_action('init', static function () use ($debug) {
    if (\current_user_can('manage_options')) {
        // we're logged in as admin
        $debug->setCfg(array(
            'collect' => true,
            'output' => true,
        ));
    } elseif ($debug->getCfg('output') === false) {
        // debug query param / cookie invalid  ('key' config option)
        // turn off collection
        $debug->setCfg('collect', false);
    }
    if ($debug->getCfg('output') && \class_exists('bdk\WampPublisher')) {
        // not currently configurable, but WampPublisher is installed,
        //   this is what you want
        $debug->setCfg('route', $debug->routeWamp);
    }
});

\add_action('shutdown', static function () use ($debug) {
    echo $debug->output();
}, 0);

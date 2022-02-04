<?php

// backward compatibility
$classMap = array(
    // PHP 5.3 doesn't like leading backslash
    'PHPUnit_Framework_AssertionFailedError' => 'PHPUnit\Framework\AssertionFailedError',
    'PHPUnit_Framework_Constraint_IsType' => 'PHPUnit\Framework\Constraint\IsType',
    'PHPUnit_Framework_Exception' => 'PHPUnit\Framework\Exception',
    'PHPUnit_Framework_TestCase' => 'PHPUnit\Framework\TestCase',
    'PHPUnit_Framework_TestSuite' => 'PHPUnit\Framework\TestSuite',
);
foreach ($classMap as $old => $new) {
    if (!class_exists($new)) {
        class_alias($old, $new);
    }
}

require __DIR__ . '/../vendor/autoload.php';

define('TEST_DIR', __DIR__);

ini_set('xdebug.var_display_max_depth', 10);
ini_set('xdebug.var_display_max_data', '-1');

$modifyTests = new \bdk\Test\ModifyTests();
$modifyTests->modify(__DIR__);

/*
    We also initialize via DebugTestFramework::setUp()
    however, testProviders are called before setup (I believe)
    provider may also initialiez debug if instance does not exist...
    ... we want to make sure we initialize with route=>'html'
*/
\bdk\Debug::getInstance(array(
    'container' => array(
        'allowOverride' => true,
    ),
    'logResponse' => false,
    'objectsExclude' => array('PHPUnit_Framework_TestSuite', 'PHPUnit\Framework\TestSuite'),
    'enableProfiling' => true,
    'route' => 'html',
    'serviceProvider' => array(
        'routeWamp' => function ($container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Route\Wamp($debug, new \bdk\Test\Debug\Mock\WampPublisher());
        },
    ),
    'onBootstrap' => function ($event) {
        $debug = $event->getSubject();
        $wamp = $debug->getRoute('wamp');
        $debug->addPlugin($wamp);
        $debug->eventManager->subscribe(\bdk\PubSub\Manager::EVENT_PHP_SHUTDOWN, function () {
            $files = \glob(TEST_DIR . '/../tmp/log/*.json');
            foreach ($files as $filePath) {
                \unlink($filePath);
            }
            $files = array(
                __DIR__ . '/../tmp/logo_clone.png',
            );
            foreach ($files as $file) {
                if (\is_file($file)) {
                    \unlink($file);
                }
            }
        });
    }
));

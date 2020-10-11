<?php

// backward compatibility
$classMap = array(
    // PHP 5.3 doesn't like leading backslash
    'PHPUnit_Framework_Exception' => 'PHPUnit\Framework\Exception',
    'PHPUnit_Framework_TestCase' => 'PHPUnit\Framework\TestCase',
    'PHPUnit_Framework_TestSuite' => 'PHPUnit\Framework\TestSuite',
    'PHPUnit_Framework_Constraint_IsType' => 'PHPUnit\Framework\Constraint\IsType',
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

/*
    We also initialize via DebugTestFramework::setUp()
    however, testProviders are called before setup (I belive)
    provider may also initialiez debug if instance does not exist...
    ... we want to make sure we initialize with route=>'html'
*/
\bdk\Debug::getInstance(array(
    'logResponse' => false,
    'objectsExclude' => array('PHPUnit_Framework_TestSuite', 'PHPUnit\Framework\TestSuite'),
    'enableProfiling' => true,
    'route' => 'html',
    'services' => array(
        'routeWamp' => function ($debug) {
            return new \bdk\Debug\Route\Wamp($debug, new \bdk\DebugTests\Mock\WampPublisher());
        },
    ),
    'onBootstrap' => function ($event) {
        $debug = $event->getSubject();
        $wamp = $debug->getRoute('wamp');
        $debug->addPlugin($wamp);
        // $debug->errorHandler->unregister();
    }
));

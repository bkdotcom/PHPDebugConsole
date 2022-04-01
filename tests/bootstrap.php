<?php

namespace bdk\Debug;

$GLOBALS['collectedHeaders'] = array();
$GLOBALS['headersSent'] = array(); // set to ['file', line] for true

/**
 * Overwrite php's header method
 *
 * @param string $header Header value
 *
 * @return void
 */
function header($header, $replace = true)
{
    $GLOBALS['collectedHeaders'][] = array($header, $replace);
}

function headers_list()
{
    $headersByName = array();
    foreach ($GLOBALS['collectedHeaders'] as $pair) {
        list($header, $replace) = $pair;
        $name = \explode(': ', $header, 2)[0];
        if ($replace || !isset($headersByName[$name])) {
            $headersByName[$name] = array($header);
            continue;
        }
        $headersByName[$name][] = $header;
    }
    return \call_user_func_array('array_merge', \array_values($headersByName));
}

function headers_sent(&$file = null, &$line = null)
{
    if ($GLOBALS['headersSent']) {
        list($file, $line) = $GLOBALS['headersSent'];
        return true;
    }
    return false;
}

namespace bdk\Test;

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
    if (\class_exists($new) === false) {
        \class_alias($old, $new);
    }
}

require __DIR__ . '/../vendor/autoload.php';

define('TEST_DIR', __DIR__);

ini_set('xdebug.var_display_max_depth', 3);
ini_set('xdebug.var_display_max_data', '-1');
/*
ini_set('xdebug.dump_globals', false);
ini_Set('xdebug.cli_color', 1);
ini_set('xdebug.max_stack_frames', 1);
ini_set('xdebug.show_error_trace', 0);
ini_set('xdebug.show_exception_trace', 0);
*/

$modifyTests = new \bdk\Test\ModifyTests();
$modifyTests->modify(__DIR__);

/*
    We also initialize via DebugTestFramework::setUp()
    however, testProviders are called before setup (I believe)
    provider may also initialiez debug if instance does not exist...
    ... we want to make sure we initialize with route=>'html'
*/
$debug = \bdk\Debug::getInstance(array(
    'collect' => true,
    'container' => array(
        'allowOverride' => true,
    ),
    'emailFrom' => 'ttesterman@test.com',
    'enableProfiling' => true,
    'exitCheck' => false,
    'fullyQualifyPhpDocType' => true,
    'logEnvInfo' => false,
    'logRequestInfo' => false,
    'logResponse' => false,
    'objectsExclude' => array('PHPUnit_Framework_TestSuite', 'PHPUnit\Framework\TestSuite'),
    'route' => 'html',
    'errorHandler' => array(
        'onEUserError' => 'normal',
    ),
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

$debug->eventManager->subscribe(\bdk\Debug::EVENT_STREAM_WRAP, function (\bdk\PubSub\Event $event) {
    if (\strpos($event['filepath'], 'PHPDebugConsole/tests') === false) {
        $event->stopPropagation();
        // return;
    }
    // echo 'wrapping ' . $event['filepath'] . "\n";
});

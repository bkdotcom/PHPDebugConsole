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
    if (\class_exists($new) === false) {
        \class_alias($old, $new);
    }
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrapFunctionReplace.php';

\define('TEST_DIR', __DIR__);

\ini_set('xdebug.var_display_max_depth', 3);
\ini_set('xdebug.var_display_max_data', '-1');
/*
ini_set('xdebug.dump_globals', false);
ini_Set('xdebug.cli_color', 1);
ini_set('xdebug.max_stack_frames', 1);
ini_set('xdebug.show_error_trace', 0);
ini_set('xdebug.show_exception_trace', 0);
*/

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
    'emailFrom' => 'testFrom@test.com',
    'enableProfiling' => true,
    'errorStatsFile' => __DIR__ . '/../tmp/error_stats.json',
    'exitCheck' => false,
    'fullyQualifyPhpDocType' => true,
    'logEnvInfo' => false,
    'logRequestInfo' => false,
    'logResponse' => false,
    'objectsExclude' => array('PHPUnit_Framework_TestSuite', 'PHPUnit\Framework\TestSuite'),
    'route' => 'html',
    'errorHandler' => array(
        // 'onEUserError' => 'continue',
    ),
    'serviceProvider' => array(
        'php' => static function () {
            return new \bdk\Test\Debug\Mock\Php();
        },
        'routeWamp' => static function ($container) {
            $debug = $container['debug'];
            return new \bdk\Debug\Route\Wamp($debug, new \bdk\Test\Debug\Mock\WampPublisher());
        },
        'utility' => static function () {
            // overrides gitBranch method
            return new \bdk\Test\Debug\Mock\Utility();
        },
    ),
    'onBootstrap' => static function ($event) {
        $debug = $event->getSubject();
        $wamp = $debug->getRoute('wamp');
        $debug->addPlugin($wamp);
    },
));

$debug->eventManager->subscribe(\bdk\PubSub\Manager::EVENT_PHP_SHUTDOWN, static function () {
    httpdStop();
    $files = \array_merge(
        \glob(TEST_DIR . '/../tmp/log/*.json'),
        \glob(TEST_DIR . '/../tmp/*')
    );
    foreach ($files as $file) {
        if (\is_file($file)) {
            \unlink($file);
        }
    }
}, 0 - PHP_INT_MAX);

httpdStart();

$modifyTests = new \bdk\DevUtil\ModifyTests();
$modifyTests->modify(__DIR__);

/**
 * Start PHP's dev httpd
 *
 * @return void
 */
function httpdStart()
{
    // php 7.0 seems to e borked.
    // unable to specify -t docroot  and -f frontController.php
    //     frontController ignored
    $dirWas = \getcwd();
    \chdir('tests/docroot');
    $cmd = 'php -S 127.0.0.1:8080 frontController.php';
    // $cmd .= '; pid=$!; echo $pid;';  // ' wait $pid; code=$?; echo $code; exit $code';
    // $cmd = '{ (' . $cmd . ') <&3 3<&- 3>/dev/null & } 3<&0';
    // $cmd .= '; pid=$!; echo "sad" $pid >&3; wait $pid; code=$?; echo $code >&3; exit $code';
    $descriptorSpec = array(
        0 => ['pipe', 'r'],  // stdin is a pipe that the child will read from
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    );
    $pipes = array();
    $GLOBALS['httpdResource'] = \proc_open($cmd, $descriptorSpec, $pipes);
    \fclose($pipes[0]);
    \stream_set_blocking($pipes[1], false);
    \stream_set_blocking($pipes[2], false);
    \usleep(250000); // wait .25 sec for server to get going
    echo \stream_get_contents($pipes[2]) . "\n";
    \chdir($dirWas);
}

/**
 * Stop PHP's dev httpd
 *
 * @return void
 */
function httpdStop()
{
    \proc_terminate($GLOBALS['httpdResource']);
}

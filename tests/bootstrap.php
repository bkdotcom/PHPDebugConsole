<?php

namespace bdk\Test;

require __DIR__ . '/CurlHttpMessage/bootstrap.php';

namespace bdk\Debug;

$GLOBALS['collectedHeaders'] = array();
$GLOBALS['headersSent'] = array(); // set to ['file', line] for true
$GLOBALS['sessionMock'] = array(
    'name' => false,
    'status' => PHP_SESSION_NONE,
);


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
    $values = \array_values($headersByName);
    return $values
        ? \call_user_func_array('array_merge', $values)
        : array();
}

function headers_sent(&$file = null, &$line = null)
{
    if ($GLOBALS['headersSent']) {
        list($file, $line) = $GLOBALS['headersSent'];
        return true;
    }
    return false;
}

namespace bdk\Debug\Plugin;

function session_name($name = null)
{
    $prev = $GLOBALS['sessionMock']['name'];
    if ($name) {
        $GLOBALS['sessionMock']['name'] = $name;
    }
    return $prev;
}

function session_start()
{
    $GLOBALS['sessionMock']['status'] = PHP_SESSION_ACTIVE;
    return true;
}

function session_status()
{
    return $GLOBALS['sessionMock']['status'];
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

$httpdProcess = \bdk\Test\startHttpd();

$debug->eventManager->subscribe(\bdk\PubSub\Manager::EVENT_PHP_SHUTDOWN, function () use ($httpdProcess) {
    \proc_terminate($httpdProcess);
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

$debug->eventManager->subscribe(\bdk\Debug::EVENT_STREAM_WRAP, static function (\bdk\PubSub\Event $event) {
    $filepath = $event['filepath'];
    $isTest = \strpos($filepath, 'PHPDebugConsole/tests') !== false;
    if ($isTest === false) {
        $event->stopPropagation();
    }
    if ($isTest === false || PHP_VERSION_ID >= 70100 || \preg_match('/\b(Mock|Fixture)\b/', $filepath) === 1) {
        return;
    }
    // remove void return type from method definitions if php < 7.1
    $event['content'] = \preg_replace(
        '/(function \S+\s*\([^)]*\))\s*:\s*void/',
        '$1',
        $event['content'],
        -1 // no limit
    );
}, PHP_INT_MAX);

function startHttpd()
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
    $resource = \proc_open($cmd, $descriptorSpec, $pipes);
    \fclose($pipes[0]);
    \stream_set_blocking($pipes[1], false);
    \stream_set_blocking($pipes[2], false);
    \usleep(250000); // wait .25 sec for server to get going
    // echo '1: ' . \stream_get_contents($pipes[1]) . "\n";
    echo \stream_get_contents($pipes[2]) . "\n";
    \chdir($dirWas);
    return $resource;
}

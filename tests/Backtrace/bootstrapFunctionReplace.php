<?php

namespace bdk\Backtrace;

$GLOBALS['functionReturn'] = array(
    'error_get_last' => null,
    'extension_loaded' => null,
    'phpversion' => null,
);

function error_get_last()
{
    return isset($GLOBALS['functionReturn']['error_get_last'])
        ? $GLOBALS['functionReturn']['error_get_last']
        : \error_get_last();
}

function extension_loaded($extensionName)
{
    return isset($GLOBALS['functionReturn']['extension_loaded'])
        ? $GLOBALS['functionReturn']['extension_loaded']
        : \extension_loaded($extensionName);
}

function phpversion($extensionName)
{
    return isset($GLOBALS['functionReturn']['phpversion'])
        ? $GLOBALS['functionReturn']['phpversion']
        : \phpversion($extensionName);
}

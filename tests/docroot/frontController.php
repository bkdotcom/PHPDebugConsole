<?php

/**
 * php -S 127.0.0.1:8080 frontController.php
 *
 * don't specify docroot from command line... php 7.0 borks
 */

require __DIR__ . '/../../vendor/autoload.php';

$serverRequest = \bdk\HttpMessage\ServerRequest::fromGlobals();
$serverParams = $serverRequest->getServerParams();
$requestUri = $serverRequest->getUri();

if (\class_exists('bdk\\Debug')) {
    $debug = new \bdk\Debug(array(
        'collect' => true,
        'output' => true,
        'route' => 'wamp',
    ));
}

\chdir($serverParams['DOCUMENT_ROOT']);

\header_remove('X-Powered-By');
if (PHP_VERSION_ID < 70000) {
    \header('Date: ' . \gmdate('D, d M Y H:i:s \G\M\T'));  // Thu, 21 Dec 2000 16:01:07 +0200
}

$path = \ltrim($requestUri->getPath(), '/');
$realpath = \realpath($path);

if ($realpath && \is_dir($realpath)) {
    foreach (['index.php', 'index.html'] as $file) {
        $filepath = \realpath($realpath . DIRECTORY_SEPARATOR . $file);
        if ($filepath) {
            $realpath = $filepath;
            break;
        }
    }
}
if ($realpath && \is_file($realpath)) {
    if (
        \substr(\basename($realpath), 0, 1) === '.'
        || $realpath === __FILE__
    ) {
        // disallowed file
        notFound();
        return;
    }
    if (\strtolower(\substr($realpath, -4)) === '.php') {
        include $realpath;
        return;
    }
    // serve from filesystem
    return false;
}

/*
    Path was not found in webroot
*/

$extensions = array(
    'php' => 'text/html; charset=UTF-8',
    'html' => 'text/html; charset=UTF-8',
    'json' => 'application/json',
    'xml' => 'application/xml',
);
foreach ($extensions as $ext => $contentType) {
    $realpath = \realpath($path . '.' . $ext);
    if ($realpath === false) {
        continue;
    }
    \header('Content-Type: ' . $contentType);
    include $realpath;
    return;
}

notFound();

function notFound()
{
    \header('HTTP/1.1 404 Not Found');
    echo '<h1>404 Not Found</h1>';
    // echo '<pre>' . htmlspecialchars(var_export($_SERVER, true)) . '</pre>';
}

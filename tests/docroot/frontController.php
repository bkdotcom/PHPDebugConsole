<?php

use \bdk\HttpMessage\ServerRequest;

/**
 * php -S 127.0.0.1:8080 frontController.php
 *
 * don't specify docroot from command line... php 7.0 borks
 */

require __DIR__ . '/../../vendor/autoload.php';

if (!\defined('STDERR')) {
    \define('STDERR', \fopen('php://stderr', 'wb'));
}

$serverRequest = ServerRequest::fromGlobals();
$serverParams = $serverRequest->getServerParams();
$requestUri = $serverRequest->getUri();

if (\class_exists('bdk\\Debug')) {
    $debug = new \bdk\Debug(array(
        'collect' => true,
        'output' => true,
        'route' => 'wamp',
    ));
    $debug->eventManager->subscribe(\bdk\ErrorHandler::EVENT_ERROR, static function (\bdk\ErrorHandler\Error $error) use ($requestUri) {
        $logFile = __DIR__ . '/../../tmp/httpd_error_log.txt';
        $logEntry = \sprintf(
            '[%s] %s',
            $requestUri,
            $error['typeStr'] . ': ' . $error['file'] . '(' . $error['line'] . ') ' . $error['message']
        );
        // \fwrite(STDERR, "\e[38;5;250m" . $logEntry . "\e[0m" . "\n");
        \file_put_contents($logFile, $logEntry . "\n", FILE_APPEND);
    });
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
        \ob_start();
        include $realpath;
        $body = compressResponse($serverRequest, \ob_get_clean());
        header('Content-Length: ' . \strlen($body));
        echo $body;
        return;
    }
    // serve from filesystem
    return false;
}

/*
    Path was not found in webroot
*/

$extensions = array(
    'html' => 'text/html; charset=UTF-8',
    'json' => 'application/json',
    'php' => 'text/html; charset=UTF-8',
    'xml' => 'application/xml',
);
foreach ($extensions as $ext => $contentType) {
    $realpath = \realpath($path . '.' . $ext);
    if ($realpath === false) {
        continue;
    }
    \header('Content-Type: ' . $contentType);
    \ob_start();
    include $realpath;
    $body = compressResponse($serverRequest, \ob_get_clean());
    header('Content-Length: ' . \strlen($body));
    echo $body;
    return;
}

notFound();

/**
 * Handle 404 Not Found
 *
 * @return void
 */
function notFound()
{
    \header('HTTP/1.1 404 Not Found');
    echo '<h1>404 Not Found</h1>';
}

function compressResponse(ServerRequest $serverRequest, $responseBody)
{
    $acceptEncoding = $serverRequest->getHeaderLine('Accept-Encoding');
    $acceptEncodings = \explode(', ', \strtolower($acceptEncoding));
    foreach ($acceptEncodings as $encoding) {
        if ($encoding === 'deflate') {
            \header('Content-Encoding: deflate');
            return \gzcompress($responseBody);
        } elseif ($encoding === 'gzip') {
            \header('Content-Encoding: gzip');
            return \gzencode($responseBody);
        }
    }
    return $responseBody;
}

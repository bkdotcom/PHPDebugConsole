<?php

use bdk\HttpMessage\Response;
use Psr\Http\Message\RequestInterface;

$queryParams = $serverRequest->getQueryParams();
$uriNew = null;
$echoVals = array(
    // 'method' => $serverRequest->getMethod(),
    'queryParams' => $queryParams,
    'headers' => buildRequestHeadersString($serverRequest),
    'cookieParams' => $serverRequest->getCookieParams(),
    'body' => (string) $serverRequest->getBody(),
);

if (isset($queryParams['redirect'])) {
    /*
        redirect may be a integer or an array
    */
    $queryParamsNew = array();
    if (\is_numeric($queryParams['redirect'])) {
        $queryParamsNew['redirect'] = $queryParams['redirect'] - 1;
        if ($queryParamsNew['redirect'] < 0) {
            $queryParamsNew['redirect'] = 0;
        }
    } elseif (\is_array($queryParams['redirect']) && !empty($queryParams['redirect'])) {
        /*
        [
            [new params]
            [new params]
        ]
        */
        $queryParamsNew = \array_shift($queryParams['redirect']);
        $queryParamsNew['redirect'] = $queryParams['redirect'];
    }
    if (empty($queryParamsNew['redirect'])) {
        unset($queryParamsNew['redirect']);
    }
    $uriNew = $serverRequest->getUri()
        ->withQuery(\http_build_query($queryParamsNew));

    \header('Location: ' . $uriNew);
}
if (isset($queryParams['cookies'])) {
    foreach ($queryParams['cookies'] as $name => $value) {
        \setcookie(
            $name,
            $value,
            \time() + 60 * 60,
            '/'
            // string $domain = "",
            // bool $secure = false,
            // bool $httponly = false
        );
    }
}
if (isset($queryParams['headers'])) {
    foreach ($queryParams['headers'] as $header) {
        \header($header);
    }
}
if (isset($queryParams['sleepMSec'])) {
    // sleepMSec  milliseconds
    \usleep($queryParams['sleepMSec'] * 1000);
}
if (isset($queryParams['statusCode']) && $uriNew === null) {
    \header(\sprintf('HTTP/1.1 %s %s', $queryParams['statusCode'], Response::codePhrase($queryParams['statusCode'])));
}

\header('Content-Type: application/json; charset="utf-8"');

echo \json_encode($echoVals, JSON_PRETTY_PRINT);

function buildRequestHeadersString(RequestInterface $message)
{
    $result = \trim($message->getMethod()
        . ' ' . $message->getRequestTarget())
        . ' HTTP/' . $message->getProtocolVersion() . "\r\n";
    foreach ($message->getHeaders() as $name => $values) {
        $result .= $name . ': ' . \implode(', ', $values) . "\r\n";
    }
    return \rtrim($result);
}

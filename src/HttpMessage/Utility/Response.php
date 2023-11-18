<?php

namespace bdk\HttpMessage\Utility;

use Psr\Http\Message\ResponseInterface;

/**
 * Response Utilities
 */
class Response
{
    /**
     * Output response headers and body
     *
     * @param ResponseInterface $response Response instance
     *
     * @return void
     *
     * @phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
     */
    public static function emit(ResponseInterface $response)
    {
        $httpLine = \sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
        header($httpLine, true, $response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }
        $stream = $response->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        while (!$stream->eof()) {
            echo $stream->read(1024 * 8);
        }
    }
}

<?php

namespace bdk\Test\HttpMessage\Utility;

use bdk\HttpMessage\Response;
use bdk\HttpMessage\Stream;
use bdk\HttpMessage\Utility\ContentType;
use bdk\HttpMessage\Utility\Response as ResponseUtils;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\HttpMessage\Utility\Response
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class ResponseTest extends TestCase
{
    public function testEmit()
    {
        // self::assertSame($expect, UriUtils::isCrossOrigin(new Uri($uri1), new Uri($uri2)));
        $body = \json_encode(array(
            'foo' => 'bar',
        ));
        $response = new Response(418);
        $response = $response->withBody(new Stream($body));
        $response = $response->withHeader('Content-Type', ContentType::JSON);
        \ob_start();
        ResponseUtils::emit($response);
        $output = \ob_get_clean();

        self::assertSame(array(
            array('HTTP/1.1 418 I\'m a teapot', true, 418),
            array('Content-Type: ' . ContentType::JSON, false),
        ), $GLOBALS['collectedHeaders']);
        self::assertSame($body, $output);
    }
}

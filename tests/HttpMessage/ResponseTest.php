<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Message;
use bdk\HttpMessage\Response;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\HttpMessage\AssertionTrait
 * @covers \bdk\HttpMessage\Response
 */
class ResponseTest extends TestCase
{
    use DataProviderTrait;
    use ExpectExceptionTrait;
    use FactoryTrait;

    public function testConstruct()
    {
        $response = new Response();
        $this->assertTrue($response instanceof Response);
        $this->assertTrue($response instanceof Message);
    }

    public function testCodePhrase()
    {
        $this->assertSame('I\'m a teapot', Response::codePhrase('418'));
    }

    /**
     * @param mixed  $code         status code to test
     * @param mixed  $phrase       phrase to test
     * @param string $phraseExpect expected phrase
     *
     * @dataProvider statusPhrases
     */
    public function testStatusPhrases($code, $phrase, $phraseExpect)
    {
        $response = $this->createResponse($code, $phrase);
        $response->withStatus($code, $phrase);
        $this->assertSame((int) $code, $response->getStatusCode());
        $this->assertSame($phraseExpect, $response->getReasonPhrase());
    }

    /*
        Exceptions
    */

    /**
     * @param mixed $statusCode status code to test
     *
     * @dataProvider invalidStatusCodes
     */
    public function testRejectInvalidStatusCode($statusCode)
    {
        $this->expectException('InvalidArgumentException');
        $response = $this->createResponse();
        $response->withStatus($statusCode, 'Custom reason phrase');
    }

    /**
     * @param mixed $reasonPhrase reason phrase to test
     *
     * @dataProvider invalidReasonPhrases
     */
    public function testRejectInvalidReasonPhrase($reasonPhrase)
    {
        $this->expectException('InvalidArgumentException');
        $response = $this->createResponse();
        $response->withStatus(200, $reasonPhrase);
    }
}

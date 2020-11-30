<?php

namespace bdk\DebugTests\Psr7lite;

use bdk\Debug\Psr7lite\Message;
use bdk\Debug\Psr7lite\Response;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class ResponseTest extends TestCase
{
    use \bdk\DebugTests\PolyFill\ExpectExceptionTrait;

    public function testConstruct()
    {
        $response = new Response();

        $this->assertTrue($response instanceof Response);
        $this->assertTrue($response instanceof Message);

        $newResponse = $response->withStatus(555, 'Custom reason phrase');

        $this->assertSame(555, $newResponse->getStatusCode());
        $this->assertSame('Custom reason phrase', $newResponse->getReasonPhrase());

        $new2Response = $newResponse->withStatus(500);

        $this->assertSame(500, $new2Response->getStatusCode());
        $this->assertSame('Internal Server Error', $new2Response->getReasonPhrase());
    }

    /*
        Exceptions
    */

    public function testExceptionStatusInvalidRange()
    {
        $this->expectException('InvalidArgumentException');
        // Exception => Status code should be in a range of 100-599, but 600 provided.
        new Response(600);
    }

    public function testExceptionStatusInvalidType()
    {
        $this->expectException('InvalidArgumentException');
        $response = new Response();
        // Exception => Status code should be an integer value, but bool provided.
        $response->withStatus(false, 'Custom reason phrase');
    }

    public function testExceptionReasonPhraseInvalidType()
    {
        $this->expectException('InvalidArgumentException');
        $response = new Response();
        // Exception => Reason phrase must be a string, but integer provided.
        $response->withStatus(200, 12345678);
    }

    public function testExceptionReasonPhraseProhibitedCharacter()
    {
        $this->expectException('InvalidArgumentException');
        $response = new Response();
        // Exception => Reason phrase contains "\r" that is considered as a prohibited character.
        $response->withStatus(200, 'Custom reason phrase\n\rThe next line');
    }
}

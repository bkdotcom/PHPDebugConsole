<?php

use bdk\Debug;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MockMiddleware implements MiddlewareInterface
{
    /**
     * @var ChromeLogger
     */
    private $debug;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Process an incoming server request and return response,
     *
     * @param ServerRequestInterface  $request [description]
     * @param RequestHandlerInterface $handler [description]
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->debug->log('running mock middleware');
        $msg = 'Hello';
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $msg);
        fseek($stream, 0);
        $stream = new Stream($stream, []);
        return (new Response())->withBody($stream);
    }
}

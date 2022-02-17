<?php

namespace bdk\Test\Debug\Mock;

use bdk\Debug;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\Stream;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Middleware implements MiddlewareInterface
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
     *
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->debug->log('running mock middleware');
        if ($request->getAttribute('throwException')) {
            throw new Exception('something went wrong');
        }
        $msg = 'Hello';
        $stream = \fopen('php://temp', 'r+');
        \fwrite($stream, $msg);
        \fseek($stream, 0);
        $stream = new Stream($stream, []);
        return (new Response())->withBody($stream);
    }
}

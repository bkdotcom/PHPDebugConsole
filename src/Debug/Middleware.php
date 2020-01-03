<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use bdk\Debug;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Add this to the *top* of your middleware (PSR-15) stack
 * This will delegate to the rest of the middleware stack unconditionally,
 *   then decorates the Response with Debug output/headers.
 */
class Middleware implements MiddlewareInterface
{

    /**
     * @var Debug
     */
    private $debug;

    /**
     * Constructor
     *
     * @param Debug $debug optional debug instance (will use singleton if not provided)
     */
    public function __construct(Debug $debug = null)
    {
        $this->debug = $debug ?: Debug::getInstance();
    }

    /**
     * Process an incoming server request and return response,
     *
     * @param ServerRequestInterface  $request Request
     * @param RequestHandlerInterface $handler "Next" request Handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $this->debug->writeToResponse($response);
    }
}

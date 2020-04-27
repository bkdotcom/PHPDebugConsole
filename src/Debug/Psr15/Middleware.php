<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Psr15;

use bdk\Debug;
use Exception;
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
    private $options = array();

    /**
     * Constructor
     *
     * @param array $options middleware options
     * @param Debug $debug   optional debug instance (will use singleton if not provided)
     */
    public function __construct($options = array(), Debug $debug = null)
    {
        $this->debug = $debug ?: Debug::getInstance();
        $this->options = \array_merge(array(
            'catchException' => false,
            'onCaughtException' => null,   // callable / should return ResponseInterface
        ), $options);
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
        $response = $this->getResponse($request, $handler);
        $this->debug->eventManager->publish('debug.middleware', $this->debug, array(
            'request' => $request,
            'response' => $response,
        ));
        return $this->debug->writeToResponse($response);
    }

    /**
     * Get response
     *
     * @param ServerRequestInterface  $request Request
     * @param RequestHandlerInterface $handler "Next" request Handler
     *
     * @return ResponseInterface|null
     */
    private function getResponse(ServerRequestInterface $request, RequestHandlerInterface $handler)
    {
        if ($this->options['catchException'] === false) {
            /*
                Don't catch exceptions : let outer middleware or uncaught-exception-handler deal with exception
            */
            return $handler->handle($request);
        }
        /*
            We've opted to catch exception here before letting outer middleware catch
        */
        try {
            $response = $handler->handle($request);
        } catch (Exception $e) {
            /*
                $response is now null
                errorHandler may retrigger exception
                    if there's a prev handler AND
                    if error event's continueToPrevHandler value = true (default)
                if so:
                    we're done
                otherwise
                    process() needs to return a ResponseInterface
                        This can be accomplished via
                        • onCaughtException callable
                        • debug.middleware event subscriber (check if empty response / return)
            */
            $this->debug->errorHandler->handleException($e);
            $response = null;
            if (\is_callable($this->options['onCaughtException'])) {
                $response = \call_user_func($this->options['onCaughtException'], $e, $request);
            }
        }
        return $response;
    }
}
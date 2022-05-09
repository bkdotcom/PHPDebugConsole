<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Psr15;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
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
class Middleware extends AbstractComponent implements MiddlewareInterface
{
    /**
     * @var Debug
     */
    private $debug;

    /**
     * Constructor
     *
     * @param Debug $debug (optional) Debug instance (will use singleton if not provided)
     * @param array $cfg   config/options
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(Debug $debug = null, $cfg = array())
    {
        $this->debug = $debug ?: Debug::getInstance();
        $this->cfg = \array_merge(array(
            'catchException' => false,
            'onCaughtException' => null,   // callable / should return ResponseInterface
        ), $cfg);
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
        $this->debug->eventManager->publish(Debug::EVENT_MIDDLEWARE, $this->debug, array(
            'request' => $request,
            'response' => $response,
        ));
        /** @var ResponseInterface */
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
        if ($this->cfg['catchException'] === false) {
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
                        • Debug::EVENT_MIDDLEWARE event subscriber (check if empty response / return)
            */
            $this->debug->errorHandler->handleException($e);
            $response = null;
            if (\is_callable($this->cfg['onCaughtException'])) {
                $response = \call_user_func($this->cfg['onCaughtException'], $e, $request);
            }
        }
        return $response;
    }
}

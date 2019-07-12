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

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\PubSub\Event;
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PHPDebugConsole Middleware for Guzzle
 */
class GuzzleMiddleware
{

    private $debug;
    private $icon = 'fa fa-exchange';

    /**
     * Constructor
     *
     * @param Debug $debug (optional) Specify PHPDebugConsole instance
     *                       if not passed, will create Guzzle channnel on singleton instance
     *                       if root channel is specified, will create a Guzzle channel
     */
    public function __construct(Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('Guzzle', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Guzzle', array('channelIcon' => $this->icon));
        }
        $this->debug = $debug;
    }

    /**
     * @param callable $nextHandler next handler in stack
     *
     * @return callable
     */
    public function __invoke(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
        return array($this, 'onRequest');
    }

    /**
     * Log Request Begin
     *
     * @param RequestInterface $request Guzzle request
     * @param array            $options opts
     *
     * @return PromiseInterface
     */
    public function onRequest(RequestInterface $request, array $options)
    {
        $this->debug->groupCollapsed(
            'Guzzle',
            $request->getMethod(),
            (string) $request->getUri(),
            $this->debug->meta('icon', $this->icon)
        );
        $this->debug->log('request headers', $this->buildHeadersString($request));
        $func = $this->nextHandler;
        return $func($request, $options)->then(
            array($this, 'onFulfilled'),
            array($this, 'onRejected')
        );
    }

    /**
     * Fulfilled Request handler
     *
     * @param ResponseInterface $response Guzzle response
     *
     * @return ResponseInterface
     */
    public function onFulfilled(ResponseInterface $response)
    {
        $this->debug->log('response headers', $this->buildHeadersString($response));
        $this->debug->groupEnd();
        return $response;
    }

    /**
     * Rejected Request handler
     *
     * @param mixed $reason Reject reason
     *
     * @return PromiseInterface
     */
    public function onRejected($reason)
    {
        $response = null;
        if ($reason instanceof Exception) {
            $this->debug->warn($reason->getCode(), $reason->getMessage());
        }
        if ($reason instanceof RequestException) {
            $response = $reason->getResponse();
        }
        if ($response) {
            $this->debug->log('response headers', $this->buildHeadersString($response));
        }
        $this->debug->groupEnd();
        return \GuzzleHttp\Promise\rejection_for($reason);
    }

    /**
     * Build request/response header string
     *
     * @param MessageInterface $message Request or Response
     *
     * @return string
     */
    private function buildHeadersString(MessageInterface $message)
    {
        $result = '';
        if ($message instanceof RequestInterface) {
            $result = \trim($message->getMethod()
                . ' '.$message->getRequestTarget())
                . ' HTTP/' . $message->getProtocolVersion() . "\r\n";
        } else {
            $result = 'HTTP/'
                .' '.$message->getProtocolVersion()
                .' '.$message->getStatusCode()
                .' '.$message->getReasonPhrase()
                ."\r\n";
        }
        foreach ($message->getHeaders() as $name => $values) {
            $result .= $name . ': ' . \implode(', ', $values) . "\r\n";
        }
        return \rtrim($result);
    }
}

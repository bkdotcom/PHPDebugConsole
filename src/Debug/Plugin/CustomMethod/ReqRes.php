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

namespace bdk\Debug\Plugin\CustomMethod;

use bdk\Debug;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\HttpMessage\HttpFoundationBridge;
use bdk\HttpMessage\Response;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface; // PSR-7
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

/**
 * Add request/response related methods to debug
 */
class ReqRes implements SubscriberInterface
{
    use CustomMethodTrait;

    private $serverParams = array();

    protected $methods = array(
        'getHeaders',
        'getInterface',
        'getResponseCode',
        'getResponseHeader',
        'getResponseHeaders',
        'getServerParam',
        'isCli',
        'requestId',
        'writeToResponse',
    );

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CONFIG => array('onConfig', PHP_INT_MAX),
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
        );
    }

    /**
     * Get and clear debug headers that need to be output
     *
     * @return array headerName => value array
     */
    public function getHeaders()
    {
        $headers = $this->debug->data->get('headers');
        $this->debug->data->set('headers', array());
        return $headers;
    }

    /**
     * Returns cli, cron, ajax, or http
     *
     * @return string cli | "cli cron" | http | "http ajax"
     */
    public function getInterface()
    {
        /*
            notes:
                $_SERVER['argv'] could be populated with query string if register_argc_argv = On
                don't use request->getMethod()... Psr7 implementation likely defaults to GET
                we used to check for `defined('STDIN')`,
                    but it's not unit test friendly
                we used to check for getServerParam['REQUEST_METHOD'] === null
                    not particularly psr7 friendly
        */
        if ($this->getServerParam('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest') {
            return 'http ajax';
        }
        $argv = $this->getServerParam('argv');
        $isCliOrCron = $argv && \implode('+', $argv) !== $this->getServerParam('QUERY_STRING');
        if (!$isCliOrCron) {
            return 'http';
        }
        // TERM is a linux/unix thing
        return $this->getServerParam('TERM') !== null || $this->getServerParam('PATH') !== null
            ? 'cli'
            : 'cli cron';
    }

    /**
     * Get HTTP response code
     *
     * Status code pulled from PSR-7 response interface (if `Debug::writeToResponse()` is being used)
     * otherwise, code pulled via `http_response_code()`
     *
     * @return int Status code
     */
    public function getResponseCode()
    {
        $response = $this->debug->response;
        return $response
            ? $response->getStatusCode()
            : \http_response_code();
    }

    /**
     * Return the response header value(s) for specified header
     *
     * Header value is pulled from PSR-7 response interface (if `Debug::writeToResponse()` is being used)
     * otherwise, value is pulled from emitted headers via `headers_list()`
     *
     * @param string      $header    ('Content-Type') header to return
     * @param null|string $delimiter (', ') if string, then join the header values
     *                                 if null, return array
     *
     * @return array|string
     */
    public function getResponseHeader($header = 'Content-Type', $delimiter = ', ')
    {
        $header = \strtolower($header);
        $headers = \array_change_key_case($this->getResponseHeaders());
        $values = isset($headers[$header])
            ? $headers[$header]
            : array();
        return \is_string($delimiter)
            ? \implode($delimiter, $values)
            : $values;
    }

    /**
     * Return all header values
     *
     * Header values are pulled from PSR-7 response interface (if `Debug::writeToResponse()` is being used)
     * otherwise, values are pulled from emitted headers via `headers_list()`
     *
     * @param bool $asString return as a single string/block of headers?
     *
     * @return array|string
     *
     * @psalm-return ($asString is false ? array : string)
     */
    public function getResponseHeaders($asString = false)
    {
        $response = $this->debug->response;
        $headers = $response
            ? $response->getHeaders()
            : $this->debug->utility->getEmittedHeaders();
        if (!$asString) {
            return $headers;
        }
        $protocol = $this->getServerParam('SERVER_PROTOCOL') ?: 'HTTP/1.0';
        $code = $this->getResponseCode();
        $phrase = Response::codePhrase($code);
        $headersAll = array(
            $protocol . ' ' . $code . ' ' . $phrase,
        );
        foreach ($headers as $k => $vals) {
            foreach ($vals as $val) {
                $headersAll[] = $k . ': ' . $val;
            }
        }
        return \join("\n", $headersAll);
    }

    /**
     * Get $_SERVER param/value
     * Gets serverParams from serverRequest interface
     *
     * @param string $name    $_SERVER key/name
     * @param mixed  $default default value
     *
     * @return mixed
     */
    public function getServerParam($name, $default = null)
    {
        if (!$this->serverParams) {
            $this->serverParams = $this->debug->serverRequest->getServerParams();
        }
        return \array_key_exists($name, $this->serverParams)
            ? $this->serverParams[$name]
            : $default;
    }

    /**
     * Is this a Command Line Interface request?
     *
     * @return bool
     */
    public function isCli()
    {
        return \strpos($this->getInterface(), 'cli') === 0;
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $configs = $event->getValues();
        if (isset($configs['debug']) && \array_key_exists('serviceProvider', $configs['debug'])) {
            $this->serverParams = array();
        }
    }

    /**
     * Generate a unique request id
     *
     * @return string
     */
    public function requestId()
    {
        $unique = \md5(\uniqid((string) \rand(), true));
        return \hash(
            'crc32b',
            $this->getServerParam('REMOTE_ADDR', 'terminal')
                . ($this->getServerParam('REQUEST_TIME_FLOAT') ?: $unique)
                . $this->getServerParam('REMOTE_PORT', '')
        );
    }

    /**
     * Appends debug output (if applicable) and/or adds headers (if applicable)
     *
     * You should call this at the end of the request/response cycle in your PSR-7 project,
     * e.g. immediately before emitting the Response.
     *
     * @param ResponseInterface|HttpFoundationResponse $response PSR-7 or HttpFoundation response
     *
     * @return ResponseInterface|HttpFoundationResponse
     *
     * @throws InvalidArgumentException
     */
    public function writeToResponse($response)
    {
        if ($response instanceof ResponseInterface) {
            return $this->writeToResponseInterface($response);
        }
        if ($response instanceof HttpFoundationResponse) {
            return $this->writeToHttpFoundationResponse($response);
        }
        throw new InvalidArgumentException(\sprintf(
            'writeToResponse expects ResponseInterface or HttpFoundationResponse, but %s provided',
            \is_object($response) ? \get_class($response) : \gettype($response)
        ));
    }

    /**
     * Write output to HttpFoundationResponse
     *
     * @param HttpFoundationResponse $response HttpFoundationResponse interface
     *
     * @return HttpFoundationResponse
     */
    private function writeToHttpFoundationResponse(HttpFoundationResponse $response)
    {
        $this->debug->setCfg('outputHeaders', false);
        $content = $response->getContent();
        $pos = \strripos($content, '</body>');
        if ($pos !== false) {
            $content = \substr($content, 0, $pos)
                . $this->debug->output()
                . \substr($content, $pos);
            $response->setContent($content);
            // reset the content length
            $response->headers->remove('Content-Length');
        }
        $headers = $this->getHeaders();
        foreach ($headers as $nameVal) {
            $response->headers->set($nameVal[0], $nameVal[1]);
        }
        // update container
        $this->debug->onCfgServiceProvider(array(
            'response' => HttpFoundationBridge::createResponse($response),
        ));
        return $response;
    }

    /**
     * Write output to PSR-7 ResponseInterface
     *
     * @param ResponseInterface $response ResponseInterface instance
     *
     * @return ResponseInterface
     */
    private function writeToResponseInterface(ResponseInterface $response)
    {
        $this->debug->setCfg('outputHeaders', false);
        $debugOutput = $this->debug->output();
        if ($debugOutput) {
            $stream = $response->getBody();
            $stream->seek(0, SEEK_END);
            $stream->write($debugOutput);
            $stream->rewind();
        }
        $headers = $this->getHeaders();
        foreach ($headers as $nameVal) {
            $response = $response->withHeader($nameVal[0], $nameVal[1]);
        }
        $this->debug->onCfgServiceProvider(array(
            'response' => $response,
        ));
        return $response;
    }
}

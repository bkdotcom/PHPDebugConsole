<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\HttpMessage\Utility\HttpFoundationBridge;
use bdk\HttpMessage\Utility\Response as ResponseUtil;
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

    /** @var string[] */
    protected $methods = [
        'getHeaders',
        'getInterface',
        'getResponseCode',
        'getResponseHeader',
        'getResponseHeaders',
        'getServerParam',
        'isCli',
        'requestId',
        'writeToResponse',
    ];

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
        );
    }

    /**
     * Get and clear debug headers that need to be output
     *
     * @return array headerName => value array
     *
     * @since 2.3
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
     * @param bool $usePsr7 (true) Use ServerRequest attached to Debug instance?
     *
     * @return string cli | "cli cron" | http | "http ajax"
     */
    public function getInterface($usePsr7 = true)
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
        $serverParamsDefault = array(
            'argv' => null,
            'HTTP_X_REQUESTED_WITH' => null,
            'PATH' => null,
            'QUERY_STRING' => null,
            'TERM' => null,
        );
        $serverParams = $usePsr7
            ? $this->debug->serverRequest->getServerParams()
            : $_SERVER;
        $serverParams = \array_merge($serverParamsDefault, $serverParams);
        if ($serverParams['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            return 'http ajax';
        }
        $isCliOrCron = $serverParams['argv'] && \implode('+', $serverParams['argv']) !== $serverParams['QUERY_STRING'];
        if ($isCliOrCron === false) {
            return 'http';
        }
        // TERM is a linux/unix thing
        return $serverParams['TERM'] !== null || $serverParams['PATH'] !== null
            ? 'cli'
            : 'cli cron';
    }

    /**
     * Get HTTP response code
     *
     * Status code pulled from PSR-7 response interface (if `Debug::writeToResponse()` is being used)
     * otherwise, code pulled via `http_response_code()`
     *
     * @return int|bool Status code
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
        $headers = static::mergeDefaultHeaders($headers);
        if (!$asString) {
            return $headers;
        }
        $protocol = $this->getServerParam('SERVER_PROTOCOL') ?: 'HTTP/1.0';
        $code = $this->getResponseCode();
        $headersAll = [
            $protocol . ' ' . $code . ' ' . ResponseUtil::codePhrase($code),
        ];
        foreach ($headers as $k => $vals) {
            foreach ($vals as $val) {
                $headersAll[] = $k . ': ' . $val;
            }
        }
        return \join("\n", $headersAll);
    }

    /**
     * Get $_SERVER param/value
     *
     * Gets server param from serverRequest interface
     *
     * @param string $name    $_SERVER key/name
     * @param mixed  $default default value
     *
     * @return mixed
     */
    public function getServerParam($name, $default = null)
    {
        return $this->debug->serverRequest->getServerParam($name, $default);
    }

    /**
     * Is this a Command Line Interface request?
     *
     * @param bool $usePsr7 (true) Use ServerRequest attached to Debug instance?
     *
     * @return bool
     */
    public function isCli($usePsr7 = true)
    {
        return \strpos($this->getInterface($usePsr7), 'cli') === 0;
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
            $this->debug->php->getDebugType($response)
        ));
    }

    /**
     * Add header values that php sends by default
     *
     * @param array $headers Header values
     *
     * @return array
     */
    private static function mergeDefaultHeaders(array $headers)
    {
        $contentTypeDefault = \ini_get('default_mimetype');
        $charset = \ini_get('default_charset');
        $headersDefault = array();
        if ($contentTypeDefault) {
            // By default, PHP will output a Content-Type header if this ini value is non-empty
            $contentTypeDefault = $contentTypeDefault . '; charset=' . $charset;
            $contentTypeDefault = \preg_replace('/; charset=$/', '', $contentTypeDefault);
            $headersDefault['Content-Type'] = [
                $contentTypeDefault,
            ];
        }
        $keysLower = \array_map('strtolower', \array_keys($headers));
        foreach ($headersDefault as $k => $v) {
            if (\in_array(\strtolower($k), $keysLower, true) === false) {
                $headers[$k] = $v;
            }
        }
        return $headers;
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

<?php

/**
 * @package   bdk\curlhttpmessage
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\CurlHttpMessage;

use bdk\CurlHttpMessage\CurlReqRes;
use bdk\CurlHttpMessage\Exception\BadResponseException;
use bdk\CurlHttpMessage\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Convert PSR-7 Request to Curl options
 */
class CurlReqResOptions
{
    /** @var RequestInterface */
    private $request;

    /** @var array<int,mixed> */
    private $curlOptions = array();

    /** @var int */
    private $maxBodySize;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->maxBodySize = 1024 * 1024;
    }

    /**
     * Get cURL options that can be passed to curl_setopt_array
     *
     * @param CurlReqRes $curlReqRes CurlReqRes instance
     *
     * @return array<int,mixed>
     */
    public function getCurlOptions(CurlReqRes $curlReqRes)
    {
        $this->request = $curlReqRes->getRequest();
        $this->curlOptions = $curlReqRes->getOptions()['curl'];
        $this->setOptionsRequest();
        $this->setOptionsResponse($curlReqRes);
        $curlOptions = $this->curlOptions;
        $this->curlOptions = array();
        return $curlOptions;
    }

    /**
     * Does given http method allow message body?
     *
     * @param string $method HTTP method
     *
     * @return bool
     */
    private function methodMayHaveBody($method = null)
    {
        $methodsNoRequestBody = [
            'GET',
            'HEAD',
            'TRACE',
        ];
        $method = $method ?: $this->request->getMethod();
        return \in_array($method, $methodsNoRequestBody, true) === false;
    }

    /**
     * Create cURL request options
     *
     * @throws RequestException Invalid request
     * @throws RuntimeException Unable to read request body
     *
     * @return void
     */
    protected function setOptionsRequest()
    {
        $options = array(
            CURLOPT_FOLLOWLOCATION => false, // handled via middleware
            CURLOPT_URL => (string) $this->request->getUri()->withFragment(''),
        );

        $this->setOptionsHttpVersion();
        $this->setOptionsBody();
        $this->setOptionsHttpHeader();

        if ($this->request->getUri()->getUserInfo()) {
            $options[CURLOPT_USERPWD] = $this->request->getUri()->getUserInfo();
        }

        $this->curlOptions = \array_replace($this->curlOptions, $options);
    }

    /**
     * Add cURL options related to the request body
     *
     * @return void
     */
    protected function setOptionsBody()
    {
        /*
        HTTP methods that cannot have payload:
          - GET   => cURL will automatically change method to PUT or POST if we
                   set CURLOPT_UPLOAD or CURLOPT_POSTFIELDS.
          - HEAD  => cURL treats HEAD as GET request with a same restrictions.
          - TRACE => According to RFC7231: a client MUST NOT send a message body
                    in a TRACE request.
        */

        $method = $this->request->getMethod();
        if ($method === 'HEAD') {
            $this->curlOptions[CURLOPT_NOBODY] = true;
        } elseif ($method !== 'GET') {
            $this->curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if ($this->methodMayHaveBody($method) && $this->request->getBody()->getSize() > 0) {
            $this->setOptionsBodyContent();
        }
    }

    /**
     * Set options related to request body
     *
     * @return void
     */
    private function setOptionsBodyContent()
    {
        $body     = $this->request->getBody();
        $bodySize = $body->getSize();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        if ($bodySize !== null && $bodySize <= $this->maxBodySize) {
            $this->curlOptions[CURLOPT_POSTFIELDS] = (string) $body;
            return;
        }

        $this->curlOptions[CURLOPT_UPLOAD] = true;

        if ($bodySize !== null) {
            $this->curlOptions[CURLOPT_INFILESIZE] = $bodySize;
        }

        $this->curlOptions[CURLOPT_READFUNCTION] = static function ($curl, $handle, $len) use ($body) {
            [$curl, $handle]; // suppress unused warning
            return $body->read($len);
        };
    }

    /**
     * Set CURLOPT_HTTPHEADER
     *
     * @return void
     */
    private function setOptionsHttpHeader()
    {
        $requestHeaders = $this->request->getHeaders();

        if ($this->request->hasHeader('Content-Length') === false && $this->methodMayHaveBody()) {
            // ensure we send Content-Length header
            $requestHeaders['Content-Length'] = [];
        }

        $headers = $this->requestHeadersToHeaders($requestHeaders);

        // Remove the Accept header if one was not set
        if ($this->request->hasHeader('Accept') === false) {
            $headers[] = 'Accept:';
        }

        // Although cURL does not support 'Expect-Continue', it adds the 'Expect'
        // header by default, so we need to force 'Expect' to empty.
        $headers[] = 'Expect:';

        $this->curlOptions[CURLOPT_HTTPHEADER] = $headers;
    }

    /**
     * Convert request interface headers to list of headers
     *
     * @param array<string,string[]>[] $requestHeaders RequestInterface headers
     *
     * @return string[]
     */
    private function requestHeadersToHeaders(array $requestHeaders)
    {
        $headers = [];
        \array_walk($requestHeaders, function ($values, $name) use (&$headers) {
            $nameLower = \strtolower($name);

            // cURL does not support 'Expect-Continue', skip all 'EXPECT' headers
            if ($nameLower === 'expect') {
                return;
            }

            if ($nameLower === 'accept-encoding') {
                $this->requestHeadersAcceptEncoding($values);
                return;
            }

            if ($nameLower === 'content-length') {
                if (\array_key_exists(CURLOPT_POSTFIELDS, $this->curlOptions)) {
                    $values = [\strlen($this->curlOptions[CURLOPT_POSTFIELDS])];
                } elseif (\array_key_exists(CURLOPT_READFUNCTION, $this->curlOptions) === false) {
                    // Force content length to '0' if body is empty
                    $values = [0];
                }
            }

            foreach ($values as $value) {
                $headers[] = $name . ': ' . $value;
            }
        });
        return $headers;
    }

    /**
     * Handle Accept-Encoding header values
     *
     * @param string[] $values Accept-Encoding header values
     *
     * @return void
     */
    private function requestHeadersAcceptEncoding(array $values)
    {
        $values = \implode(', ', $values);
        if (\defined('CURLOPT_ACCEPT_ENCODING')) {
            // available as of cURL 7.21.6.
            $this->curlOptions[CURLOPT_ACCEPT_ENCODING] = $values;
        } elseif (\defined('CURLOPT_ENCODING')) {
            // CURLOPT_ENCODING - Available as of cURL 7.10 and deprecated as of cURL 7.21.6.
            $this->curlOptions[CURLOPT_ENCODING] = $values;
        }
    }

    /**
     * Set response options
     *
     * @param CurlReqRes $curlReqRes CurlReqRes instance
     *
     * @return void
     */
    protected function setOptionsResponse(CurlReqRes $curlReqRes)
    {
        $options = array(
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => false,
        );

        // response headers
        // @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        $options[CURLOPT_HEADERFUNCTION] = function ($curl, $data) use ($curlReqRes) {
            $response = $curlReqRes->getResponse();
            $headerLine = \trim($data);
            if ($headerLine === '') {
                return \strlen($data);
            }
            $response = \strpos(\strtoupper($headerLine), 'HTTP/') === 0
                ? $this->responseWithStatus($response, $headerLine)
                : $this->responseWithAddedHeaderLine($response, $headerLine);
            $curlReqRes->setResponse($response);
            return \strlen($data);
        };

        // response body
        $options[CURLOPT_WRITEFUNCTION] = static function ($curl, $data) use ($curlReqRes) {
            $curl; // suppress warning
            return $curlReqRes->getResponse()->getBody()->write($data);
        };

        $this->curlOptions = \array_replace($this->curlOptions, $options);
    }

    /**
     * Set CURLOPT_HTTP_VERSION
     *
     * @return void
     *
     * @throws RequestException Unsupported cURL http protocol version
     */
    private function setOptionsHttpVersion()
    {
        $ver = CURL_HTTP_VERSION_NONE;
        switch ($this->request->getProtocolVersion()) {
            case '1.0':
                $ver = CURL_HTTP_VERSION_1_0;
                break;
            case '1.1':
                $ver = CURL_HTTP_VERSION_1_1;
                break;
            case '2.0':
                if (\defined('CURL_HTTP_VERSION_2_0') === false) {
                    // @codeCoverageIgnoreStart
                    throw new RequestException(
                        'libcurl 7.33 required for HTTP 2.0',
                        $this->request
                    );
                    // @codeCoverageIgnoreEnd
                }
                $ver = CURL_HTTP_VERSION_2_0;
        }
        $this->curlOptions[CURLOPT_HTTP_VERSION] = $ver;
    }

    /**
     * Add header to response
     *
     * @param ResponseInterface $response   Response instance
     * @param string            $headerLine "name: value" header
     *
     * @return ResponseInterface
     *
     * @throws BadResponseException
     */
    protected function responseWithAddedHeaderLine(ResponseInterface $response, $headerLine)
    {
        $headerParts = \explode(':', $headerLine, 2);

        if (\count($headerParts) !== 2) {
            // CURL will catch this first with CURLE_WEIRD_SERVER_REPLY
            // @codeCoverageIgnoreStart
            throw new BadResponseException(
                \sprintf('"%s" is not a valid HTTP header', $headerLine),
                $this->request,
                $response
            );
            // @codeCoverageIgnoreEnd
        }

        $name  = \trim($headerParts[0]);
        $value = \trim($headerParts[1]);

        return $response->withAddedHeader($name, $value);
    }

    /**
     * Set response's protocol version, status code, & reason phrase
     *
     * @param ResponseInterface $response   Response instance
     * @param string            $statusLine ie HTTP/1.1 200 OK
     *
     * @return ResponseInterface
     *
     * @throws BadResponseException
     */
    protected function responseWithStatus(ResponseInterface $response, $statusLine)
    {
        $statusParts = \explode(' ', $statusLine, 3);
        $partsCount  = \count($statusParts);

        if ($partsCount < 2) {
            // CURL will catch this first with CURLE_UNSUPPORTED_PROTOCOL
            // @codeCoverageIgnoreStart
            throw new BadResponseException(
                \sprintf('"%s" is not a valid HTTP status line', $statusLine),
                $this->request,
                $response
            );
            // @codeCoverageIgnoreEnd
        }

        $version = \substr($statusParts[0], 5);
        $code = (int) $statusParts[1];
        $reasonPhrase = $partsCount > 2
            ? $statusParts[2]
            : '';

        return $response
            ->withProtocolVersion($version)
            ->withStatus($code, $reasonPhrase);
    }
}

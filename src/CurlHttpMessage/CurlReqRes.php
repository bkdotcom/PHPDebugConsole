<?php

namespace bdk\CurlHttpMessage;

use bdk\CurlHttpMessage\CurlReqResOptions;
use bdk\CurlHttpMessage\Exception\NetworkException;
use bdk\CurlHttpMessage\Exception\RequestException;
use bdk\Promise;
use ErrorException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Encapsulate Request, Response, CurlHandle, Promise, etc
 */
class CurlReqRes
{
    /** @var resource|CurlHandle */
    private $curlHandle;

    /** @var bool */
    private $curlHandleInternal = false;

    private $errno;
    private $error;

    private $options = array(
        'curl' => array(),
        'delay' => null,
        'isAsyncronous' => false,
        'maxRedirect' => 5,
        'noEarlierThan' => null,
    );

    /** @var Promise */
    private $promise;

    /** @var RequestInterface */
    private $request;

    /** @var ResposneInterface */
    private $response;

    /** @var callable */
    private $responseFactory;

    /**
     * Constructor
     *
     * @param RequestInterface $request         Request instance
     * @param callable         $responseFactory Callable that generates a ResponseInterface
     */
    public function __construct(RequestInterface $request, callable $responseFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->setRequest($request);
        $this->options['maxBodySize'] = 1024 * 1024;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (\is_resource($this->curlHandle) && \get_resource_type($this->curlHandle) === 'Unknown') {
            // already closed
            $this->curlHandle = null;
            return;
        }
        if ($this->curlHandle) {
            try {
                \curl_close($this->curlHandle);
            } catch (ErrorException $e) {
                // ignore exception
            }
            $this->curlHandle = null;
        }
    }

    /**
     * Execute the given request
     *
     * @return ResponseInterface
     */
    public function exec()
    {
        $curlHandle = $this->getCurlHandle(true);
        \curl_exec($curlHandle);
        return $this->finish();
    }

    /**
     * Check for error and return ResponseInterface on success
     *
     * @return ResponseInterface
     */
    public function finish()
    {
        $this->errno = \curl_errno($this->curlHandle);
        $this->error = \curl_error($this->curlHandle);

        $this->unsetOptions();
        if ($this->curlHandleInternal) {
            $this->setCurlHandle(null);
        }

        if ($this->errno === CURLE_OK && \strpos($this->error, 'Failed to connect') === 0) {
            // php < 7.0 ?
            $this->errno = CURLE_COULDNT_CONNECT;
        }
        if ($this->errno !== CURLE_OK) {
            return $this->finishError();
        }

        // Rewind the body of the response if possible.
        $body = $this->response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        // return new FulfilledPromise($this->response);
        return $this->response;
    }

    /**
     * Get the curl handle
     *
     * @param bool $create (false) whether handle should be created
     *
     * @return resource|CurlHandle|null
     */
    public function getCurlHandle($create = false)
    {
        if ($this->curlHandle === null && $create) {
            $this->curlHandleInternal = true;
            $this->setCurlHandle(\curl_init());
        }
        return $this->curlHandle;
    }

    /**
     * Set (or unset) cURL handle
     *
     * If setting, also sets curl options
     *
     * @param resource|CurlHandle|null $curlHandle curlHandle
     *
     * @return self
     */
    public function setCurlHandle($curlHandle)
    {
        if ($curlHandle === null) {
            $this->unsetOptions();
            if ($this->curlHandleInternal) {
                \curl_close($this->curlHandle);
            }
            $this->curlHandle = null;
            return $this;
        }
        $this->curlHandle = $curlHandle;
        $this->buildCurlOptions();
        \curl_setopt_array($this->curlHandle, $this->options['curl']);
        return $this;
    }

    /**
     * Get option by name
     *
     * @param string|array $path option path
     *
     * @return mixed
     */
    public function getOption($path)
    {
        $path = \is_array($path)
            ? $path
            : \array_filter(\preg_split('#[\./]#', (string) $path), 'strlen');
        $path = \array_reverse($path);
        $optRef = &$this->options;
        while ($path) {
            $key = \array_pop($path);
            if (\is_array($optRef) === false) {
                return null;
            }
            if (isset($optRef[$key])) {
                $optRef = &$optRef[$key];
                continue;
            }
            return null;
        }
        return $optRef;
    }

    /**
     * Get all options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set option value
     *
     * @param string|array $path option name or path
     * @param mixed        $val  new option value
     *
     * @return self
     */
    public function setOption($path, $val)
    {
        $path = \is_array($path)
            ? $path
            : \array_filter(\preg_split('#[\./]#', (string) $path), 'strlen');
        $path = \array_reverse($path);
        $optRef = &$this->options;
        while ($path) {
            $key = \array_pop($path);
            if (!isset($optRef[$key]) || !\is_array($optRef[$key])) {
                $optRef[$key] = array(); // initialize this level
            }
            $optRef = &$optRef[$key];
        }
        $optRef = $val;
        $this->setOptionsPost();
        return $this;
    }

    /**
     * Does a shallow merge
     *
     * @param array $options option values to merge
     *
     * @return self
     */
    public function setOptions($options)
    {
        $this->options = \array_merge($this->options, $options);
        $this->setOptionsPost();
        return $this;
    }

    /**
     * Get the promise
     *
     * @return Promise|null
     */
    public function getPromise()
    {
        return $this->promise;
    }

    /**
     * Set the promise identified with this curl request/response
     *
     * @param Promise $promise [description]
     *
     * @return self
     */
    public function setPromise(Promise $promise)
    {
        $this->promise = $promise;
        return $this;
    }

    /**
     * Get the request
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Replace current request and reset response
     *
     * @param RequestInterface $request RequestInterface instance
     *
     * @return self
     */
    public function setRequest(RequestInterface $request)
    {
        $this->request = $request;
        $this->response = \call_user_func($this->responseFactory);
        if ($this->curlHandle) {
            $this->buildCurlOptions();
        }
        return $this;
    }

    /**
     * Get the response
     *
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Set the response
     *
     * @param ResponseInterface $response ResponseInterface instance
     *
     * @return self
     */
    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Set cURL options
     *
     * @return void
     */
    protected function buildCurlOptions()
    {
        $curlReqResOptions = new CurlReqResOptions();
        $this->options['curl'] = $curlReqResOptions->getCurlOptions($this);
    }

    /**
     * Throw NetworkException or RequestException
     *
     * @return void
     *
     * @throws RequestException
     */
    protected function finishError()
    {
        static $networkErrors = array(
            CURLE_COULDNT_CONNECT,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_RESOLVE_PROXY,
            CURLE_GOT_NOTHING,
            CURLE_OPERATION_TIMEOUTED,
            CURLE_SSL_CONNECT_ERROR,
        );

        $infoUrl = 'see https://curl.haxx.se/libcurl/c/libcurl-errors.html';
        $message = \sprintf('cURL error %s: %s (%s)', $this->errno, $this->error, $infoUrl);

        $uri = (string) $this->request->getUri();
        if ($uri !== '' && \strpos($this->error, $uri) === false) {
            $message .= \sprintf(
                ' for %s %s',
                $this->request->getMethod(),
                $uri
            );
        }

        $exception = \in_array($this->errno, $networkErrors, true)
            ? new NetworkException($message, $this->request)
            : new RequestException($message, $this->request);

        throw $exception;
    }

    /**
     * Convert `delay` to `noEarlierThan`
     *
     * @return void
     */
    private function setOptionsPost()
    {
        if (isset($this->options['delay'])) {
            $this->options['noEarlierThan'] = \microtime(true) + $this->options['delay'] / 1000;
        }
    }

    /**
     * Remove all callback functions
     * they can hold onto references and are not cleaned up by curl_reset.
     *
     * @return void
     */
    protected function unsetOptions()
    {
        \curl_setopt($this->curlHandle, CURLOPT_HEADERFUNCTION, null);
        \curl_setopt($this->curlHandle, CURLOPT_READFUNCTION, null);
        \curl_setopt($this->curlHandle, CURLOPT_WRITEFUNCTION, null);
        \curl_setopt($this->curlHandle, CURLOPT_PROGRESSFUNCTION, null);
    }
}

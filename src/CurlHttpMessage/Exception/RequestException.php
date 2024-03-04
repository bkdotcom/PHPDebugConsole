<?php

namespace bdk\CurlHttpMessage\Exception;

use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/**
 * Request Exception
 */
class RequestException extends RuntimeException
{
	/** @var RequestInterface */
	private $request;

    /** @var ResponseInterface */
    private $response;

	/**
	 * Construct
	 *
	 * @param string            $message       Exception message
	 * @param RequestInterface  $request       Request object
     * @param ResponseInterface $response      Response object
	 * @param Exception|null    $prevException Previous exception
	 */
	public function __construct($message, RequestInterface $request, ResponseInterface $response = null, Exception $prevException = null)
	{
		$this->request = $request;
        $this->response = $response;

        $code = $response ? $response->getStatusCode() : 0;
        parent::__construct($message, $code, $prevException);
	}

    /**
     * Build exception
     *
     * @param RequestInterface    $request       Request
     * @param ResponseInterface   $response      Response
     * @param Throwable|Exception $prevException Previous exception
     *
     * @return self
     */
    public static function create(RequestInterface $request, ResponseInterface $response = null, Exception $prevException = null)
    {
        $level = $response
            ? (int) \floor($response->getStatusCode() / 100)
            : 0;

        $class = \in_array($level, array(4, 5), true)
            ? __NAMESPACE__ . '\\BadResponseException'
            : __CLASS__;

        $message = self::buildMessage($request, $response);

        return new $class($message, $request, $response, $prevException);
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
     * Get the response
     *
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Build exception message
     *
     * @param RequestInterface       $request  Request
     * @param ResponseInterface|null $response Response
     *
     * @return string
     */
    private static function buildMessage(RequestInterface $request, ResponseInterface $response = null)
    {
        if (!$response) {
            $label = 'Error completing request';
            return \sprintf(
                '%s: `%s %s`',
                $label,
                $request->getMethod(),
                (string) static::obfuscateUri($request->getUri())
            );
        }
        $label = 'Unsuccessful request';
        $level = (int) \floor($response->getStatusCode() / 100);
        if ($level === 4) {
            $label = 'Client error';
        } elseif ($level === 5) {
            $label = 'Server error';
        }
        return \sprintf(
            '%s: `%s %s` resulted in a `%s %s` response',
            $label,
            $request->getMethod(),
            (string) static::obfuscateUri($request->getUri()),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
    }

    /**
     * Obfuscates URI if there is a username and a password present
     *
     * @param UriInterface $uri Uri
     *
     * @return UriInterface
     */
    private static function obfuscateUri(UriInterface $uri)
    {
        $userInfo = $uri->getUserInfo();
        $pos = \strpos($userInfo, ':');
        if ($pos !== false) {
            $username = \substr($userInfo, 0, $pos);
            return $uri->withUserInfo($username, '***');
        }
        return $uri;
    }
}

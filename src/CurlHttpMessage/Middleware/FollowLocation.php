<?php

namespace bdk\CurlHttpMessage\Middleware;

use bdk\CurlHttpMessage\CurlReqRes;
use bdk\CurlHttpMessage\Exception\BadResponseException;
use bdk\CurlHttpMessage\Exception\RequestException;
use bdk\HttpMessage\Stream;
use bdk\HttpMessage\Uri;
use bdk\HttpMessage\UriUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Follow location header
 */
class FollowLocation
{
    private $handler;

    /**
     * Invoke
     *
     * @param callable $handler Next request handler in the middleware stack
     *
     * @return callable
     */
    public function __invoke(callable $handler)
    {
        return function (CurlReqRes $curlReqRes) use ($handler) {
            $this->handler = $handler;
            return $this->process($curlReqRes);
        };
    }

    /**
     * Process response and redirect if directed by response
     *
     * @param CurlReqRes $curlReqRes CurlReqRes instance
     *
     * @return PromiseInterface
     */
    protected function process(CurlReqRes $curlReqRes)
    {
        $handler = $this->handler;
        return $handler($curlReqRes)
            ->then(function (ResponseInterface $response) use ($curlReqRes) {
                $location = $response->getHeaderLine('Location');
                $statusCode = $response->getStatusCode();

                if (\strpos((string) $statusCode, '3') !== 0 || $location === '') {
                    return $response;
                }

                $request = $curlReqRes->getRequest();
                $uriOld = $request->getUri();
                $uriNew = UriUtils::resolve($uriOld, new Uri($location));

                $this->assertMax($curlReqRes);
                $this->assertScheme($curlReqRes, $uriNew);

                $this->updateCurlReqRes($curlReqRes, $uriNew);

                $onRedirect = $curlReqRes->getOption('onRedirect');
                if ($onRedirect) {
                    $onRedirect($request, $response, $uriNew);
                }

                return $this->process($curlReqRes);
            });
    }

    /**
     * Update next request and options
     *
     * @param CurlReqRes   $curlReqRes CurlReqRes instance
     * @param UriInterface $uriNew     Uri instance
     *
     * @return void
     */
    private function updateCurlReqRes(CurlReqRes $curlReqRes, UriInterface $uriNew)
    {
        $request = $curlReqRes->getRequest();
        $curlOptions = $curlReqRes->getOption('curl');

        $requestNext = $request->withUri($uriNew);

        if (UriUtils::isCrossOrigin($request->getUri(), $uriNew)) {
            unset(
                $curlOptions[CURLOPT_HTTPAUTH],
                $curlOptions[CURLOPT_USERPWD]
            );
            $requestNext = $requestNext->withoutHeader('Authorization');
            $requestNext = $requestNext->withoutHeader('Cookie');
        }

        $statusCode = $curlReqRes->getResponse()->getStatusCode();
        /*
        According to the HTTP specs, a 303 redirection should be followed using
            the GET method. 301 and 302 must not.
        */
        if ($statusCode <= 303) {
            $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
            $requestMethod = $request->getMethod();

            if (\in_array($requestMethod, $safeMethods, true) === false) {
                $requestNext = $requestNext->withMethod('GET');
            }

            $requestNext = $requestNext->withoutHeader('Content-Type');
            $requestNext = $requestNext->withBody(new Stream());
            unset(
                $curlOptions[CURLOPT_POSTFIELDS],
                $curlOptions[CURLOPT_UPLOAD],
                $curlOptions[CURLOPT_INFILESIZE],
                $curlOptions[CURLOPT_READFUNCTION]
            );
        }

        $curlReqRes
            ->setRequest($requestNext)
            ->setOption('curl', $curlOptions);
    }

    /**
     * Assert redirects does not exceed maxRedirect
     *
     * @param CurlReqRes $curlReqRes CurlReqRes instance
     *
     * @return void
     *
     * @throws RequestException
     */
    private function assertMax(CurlReqRes $curlReqRes)
    {
        $count = $curlReqRes->getOption('redirectCount') ?: 1;
        if ($count > $curlReqRes->getOption('maxRedirect')) {
            throw new RequestException(
                'Too many redirects (' . $count . ')',
                $curlReqRes->getRequest(),
                $curlReqRes->getResponse()
            );
        }
        $curlReqRes->setOption('redirectCount', $count + 1);
    }

    /**
     * Assert redirecting to http or https url
     *
     * @param CurlReqRes   $curlReqRes CurlReqRes instance
     * @param UriInterface $uri        Uri instance
     *
     * @return void
     *
     * @throws BadResponseException
     */
    private function assertScheme(CurlReqRes $curlReqRes, UriInterface $uri)
    {
        $allowed = array('http', 'https');
        if (\in_array($uri->getScheme(), $allowed, true) === false) {
            throw new BadResponseException(
                \sprintf(
                    'Redirect URI, %s, does not use one of the allowed redirect protocols: %s',
                    $uri,
                    \implode(', ', $allowed)
                ),
                $curlReqRes->getRequest(),
                $curlReqRes->getResponse()
            );
        }
    }
}

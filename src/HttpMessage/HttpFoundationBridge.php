<?php

/**
 * This file is part of HttpMessage
 *
 * @package   bdk/http-message
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v1.0
 */

namespace bdk\HttpMessage;

use bdk\HttpMessage\ServerRequest;
use bdk\HttpMessage\Stream;
use bdk\HttpMessage\UploadedFile;
use bdk\HttpMessage\Uri;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile as HttpFoundationUploadedFile;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Factories for creating ServerRequest & Response from HttpFoundation objects
 *
 * @psalm-api
 */
class HttpFoundationBridge
{
    /**
     * Create a Psr7 request object from HttpFoundation request
     *
     * @param HttpFoundationRequest $request HttpFoundation\Request obj
     *
     * @return ServerRequest
     *
     * @psalm-suppress ReservedWord complains about HttpFoundations' : mixed return spec
     */
    public static function createRequest(HttpFoundationRequest $request)
    {
        /** @psalm-var string  */
        $query = $request->server->get('QUERY_STRING', '');
        $uri = $request->getSchemeAndHttpHost()
            . $request->getBaseUrl()
            . $request->getPathInfo()
            . ($query !== '' ? '?' . $query : '');
        $uri = new Uri($uri);

        $bodyContentResource = $request->getContent(true);
        $stream = new Stream($bodyContentResource);

        $psr7request = new ServerRequest($request->getMethod(), $uri, $request->server->all());
        $psr7request = $psr7request
            ->withBody($stream)
            ->withUploadedFiles(self::getFiles($request->files->all()))
            ->withCookieParams($request->cookies->all())
            ->withQueryParams($request->query->all())
            ->withParsedBody($request->request->all());

        /** @var mixed $value */
        foreach ($request->attributes->all() as $key => $value) {
            $psr7request = $psr7request->withAttribute((string) $key, $value);
        }

        return $psr7request;
    }

    /**
     * Create Response from HttpFoundationResponse
     *
     * @param HttpFoundationResponse $response HttpFoundationResponse instance
     *
     * @return Response
     */
    public static function createResponse(HttpFoundationResponse $response)
    {
        $statusCode = $response->getStatusCode();
        $protocolVersion = $response->getProtocolVersion();
        $stream = self::createResponseStream($response);

        $psr7response = new Response($statusCode);
        $psr7response = $psr7response
            ->withProtocolVersion($protocolVersion)
            ->withBody($stream);

        $headers = $response->headers->all();
        foreach ($headers as $name => $values) {
            $values = \array_filter($values, static function ($value) {
                return $value !== null;
            });
            if ($values) {
                $psr7response = $psr7response->withHeader($name, $values);
            }
        }

        return $psr7response;
    }

    /**
     * Create a Stream from HttpFoundationResponse
     *
     * @param HttpFoundationResponse $response response instance
     *
     * @return Stream
     */
    private static function createResponseStream(HttpFoundationResponse $response)
    {
        if ($response instanceof BinaryFileResponse && !$response->headers->has('Content-Range')) {
            $pathName = $response->getFile()->getPathname();
            return new Stream(\fopen($pathName, 'rb+'));
        }
        $stream = new Stream(\fopen('php://temp', 'wb+'));
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            \ob_start(
                /**
                 * @param string $buffer
                 *
                 * @return string
                 */
                static function ($buffer) use ($stream) {
                    $stream->write($buffer);
                    return '';
                }
            );
            $response->sendContent();
            \ob_end_clean();
            return $stream;
        }
        /** @psalm-suppress ReservedWord */
        $content = $response->getContent();
        if ($content !== false) {
            $stream->write($content);
        }
        return $stream;
    }

    /**
     * Creates a PSR-7 UploadedFile instance from a Symfony one.
     *
     * @param HttpFoundationUploadedFile $uploadedFile HttpFoundation\File\UploadedFile
     *
     * @return UploadedFile
     */
    private static function createUploadedFile(HttpFoundationUploadedFile $uploadedFile)
    {
        return new UploadedFile(
            $uploadedFile->getRealPath(),
            (int) $uploadedFile->getSize(),
            $uploadedFile->getError(),
            $uploadedFile->getClientOriginalName(),
            $uploadedFile->getClientMimeType()
        );
    }

    /**
     * Converts Symfony uploaded files array to the PSR one.
     *
     * @param array $uploadedFiles uploadedFiles
     *
     * @return array
     */
    private static function getFiles(array $uploadedFiles)
    {
        return \array_map(static function ($value) {
            if ($value === null) {
                $value = new UploadedFile(
                    null,
                    0,
                    UPLOAD_ERR_NO_FILE
                );
            } elseif ($value instanceof HttpFoundationUploadedFile) {
                $value =  self::createUploadedFile($value);
            } elseif (\is_array($value)) {
                $value = self::getFiles($value);
            }
            return $value;
        }, $uploadedFiles);
    }
}

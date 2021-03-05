<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Psr7lite;

use bdk\Debug\Psr7lite\ServerRequest;
use bdk\Debug\Psr7lite\Stream;
use bdk\Debug\Psr7lite\UploadedFile;
use bdk\Debug\Psr7lite\Uri;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile as HttpFoundationUploadedFile;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Factories for creating Psr7lite ServerRequest & Response from HttpFoundation objects
 */
class HttpFoundationBridge
{

    /**
     * Create a Psr7lite request object from HttpFoundation request
     *
     * @param HttpFoundationRequest $request HttpFoundation\Request obj
     *
     * @return ServerRequest
     */
    public static function createRequest(HttpFoundationRequest $request)
    {
        $query = $request->server->get('QUERY_STRING', '');
        $uri = $request->getSchemeAndHttpHost()
            . $request->getBaseUrl()
            . $request->getPathInfo()
            . ($query !== '' ? '?' . $query : '');
        $uri = new Uri($uri);

        $bodyContent = $request->getContent(true);
        $resource = \fopen('php://temp', 'wb+');
        \fwrite($resource, $bodyContent);
        \rewind($resource);
        $stream = new Stream($resource);

        $psr7request = new ServerRequest($request->getMethod(), $uri, $request->server->all());
        $psr7request = $psr7request
            ->withBody($stream)
            ->withUploadedFiles(self::getFiles($request->files->all()))
            ->withCookieParams($request->cookies->all())
            ->withQueryParams($request->query->all())
            ->withParsedBody($request->request->all());

        foreach ($request->attributes->all() as $key => $value) {
            $psr7request = $psr7request->withAttribute($key, $value);
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
        $reasonPhrase = isset($response->statusTexts[$statusCode])
            ? $response->statusTexts[$statusCode]
            : null;
        $protocolVersion = $response->getProtocolVersion();
        $stream = self::createResponseStream($response);

        $psr7response = new Response($statusCode, $reasonPhrase);
        $psr7response = $psr7response
            ->withProtocolVersion($protocolVersion)
            ->withBody($stream);

        $headers = $response->headers->all();
        foreach ($headers as $name => $value) {
            $psr7response = $psr7response->withHeader($name, $value);
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
            \ob_start(function ($buffer) use ($stream) {
                $stream->write($buffer);
                return '';
            });
            $response->sendContent();
            \ob_end_clean();
            return $stream;
        }
        $stream->write($response->getContent());
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
    private static function getFiles($uploadedFiles)
    {
        $files = [];
        foreach ($uploadedFiles as $key => $value) {
            if ($value === null) {
                $files[$key] = new UploadedFile(
                    null,
                    0,
                    UPLOAD_ERR_NO_FILE
                );
                continue;
            }
            if ($value instanceof HttpFoundationUploadedFile) {
                $files[$key] = self::createUploadedFile($value);
                continue;
            }
            $files[$key] = self::getFiles($value);
        }
        return $files;
    }
}

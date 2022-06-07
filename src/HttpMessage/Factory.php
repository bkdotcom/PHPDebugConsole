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

namespace bdk\HttpMessage;

use bdk\HttpMessage\Request;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\ServerRequest;
use bdk\HttpMessage\Stream;
use bdk\HttpMessage\UploadedFile;
use bdk\HttpMessage\Uri;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * PSR-7 Factories.
 */
class Factory
{
    /**
     * Create a new request.
     *
     * @param string              $method The HTTP method associated with the request.
     * @param UriInterface|string $uri    The URI associated with the request.
     *
     * @return Request
     */
    public function createRequest($method, $uri)
    {
        return new Request($method, $uri);
    }

    /**
     * Create a new response.
     *
     * @param int    $code         The HTTP status code. Defaults to 200.
     * @param string $reasonPhrase The reason phrase to associate with the status code
     *     in the generated response. If none is provided, implementations MAY use
     *     the defaults as suggested in the HTTP specification.
     *
     * @return Response
     */
    public function createResponse($code = 200, $reasonPhrase = '')
    {
        return new Response($code, $reasonPhrase);
    }

    /**
     * Create a new server request.
     *
     * Note that server parameters are taken precisely as given - no parsing/processing
     * of the given values is performed. In particular, no attempt is made to
     * determine the HTTP method or URI, which must be provided explicitly.
     *
     * @param string              $method       The HTTP method associated with the request.
     * @param UriInterface|string $uri          The URI associated with the request.
     * @param array               $serverParams An array of Server API (SAPI) parameters with
     *     which to seed the generated request instance.
     *
     * @return ServerRequest
     */
    public function createServerRequest($method, $uri, $serverParams = array())
    {
        return new ServerRequest($method, $uri, $serverParams);
    }

    /**
     * Create a new stream from a string.
     *
     * The stream SHOULD be created with a temporary resource.
     *
     * @param string $content String content with which to populate the stream.
     *
     * @return Stream
     */
    public function createStream($content = '')
    {
        $resource = \fopen('php://temp', 'wb+');
        \fwrite($resource, $content);
        \rewind($resource);
        return $this->createStreamFromResource($resource);
    }

    /**
     * Create a stream from an existing file.
     *
     * The file MUST be opened using the given mode, which may be any mode
     * supported by the `fopen` function.
     *
     * The `$filename` MAY be any string supported by `fopen()`.
     *
     * @param string $filename The filename or stream URI to use as basis of stream.
     * @param string $mode     The mode with which to open the underlying filename/stream.
     *
     * @throws RuntimeException If the file cannot be opened.
     * @throws InvalidArgumentException If the mode is invalid.
     *
     * @return Stream
     */
    public function createStreamFromFile($filename, $mode = 'r')
    {
        \set_error_handler(function () {
        });
        $resource = \fopen($filename, $mode);
        \restore_error_handler();
        if ($resource === false) {
            if ($mode === '' || \in_array($mode[0], array('r', 'w', 'a', 'x', 'c')) === false) {
                throw new InvalidArgumentException('The mode ' . $mode . ' is invalid.');
            }
            throw new RuntimeException(\sprintf(
                'The file %s cannot be opened.',
                $filename
            ));
        }
        return $this->createStreamFromResource($resource);
    }

    /**
     * Create a new stream from an existing resource.
     *
     * The stream MUST be readable and may be writable.
     *
     * @param resource $resource The PHP resource to use as the basis for the stream.
     *
     * @return Stream
     */
    public function createStreamFromResource($resource)
    {
        return new Stream($resource);
    }

    /**
     * Create a new uploaded file.
     *
     * If a size is not provided it will be determined by checking the size of
     * the stream.
     *
     * @param StreamInterface $stream          The underlying stream
     *              representing the uploaded file content.
     * @param int             $size            The size of the file in bytes.
     * @param int             $error           The PHP file upload error.
     * @param string          $clientFilename  The filename as provided by the client, if any.
     * @param string          $clientMediaType The media type as provided by the client, if any.
     * @param string          $clientFullPath  The full-path as provided by the client, if any.
     *
     * @return UploadedFile
     *
     * @throws InvalidArgumentException If the file resource is not readable.
     *
     * @link http://php.net/manual/features.file-upload.post-method.php
     * @link http://php.net/manual/features.file-upload.errors.php
     */
    public function createUploadedFile(
        StreamInterface $stream,
        $size = null,
        $error = UPLOAD_ERR_OK,
        $clientFilename = null,
        $clientMediaType = null,
        $clientFullPath = null
    )
    {
        if ($size === null) {
            $size = $stream->getSize();
        }
        return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType, $clientFullPath);
    }

    /**
     * Create a new URI.
     *
     * @param string $uri The URI to parse.
     *
     * @throws InvalidArgumentException If the given URI cannot be parsed.
     *
     * @return Uri
     */
    public function createUri($uri = '')
    {
        return new Uri($uri);
    }
}

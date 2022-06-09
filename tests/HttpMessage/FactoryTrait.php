<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Request;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\ServerRequest;
use bdk\HttpMessage\Stream;
use bdk\HttpMessage\UploadedFile;
use bdk\HttpMessage\Uri;

trait FactoryTrait
{
    protected static function createMessage()
    {
        return self::createRequest();
    }

    protected static function createRequest($method = 'GET', $uri = '')
    {
        return new Request($method, $uri);
    }

    protected static function createResponse($code = 200, $reasonPhrase = null)
    {
        return new Response($code, $reasonPhrase);
    }

    protected static function createServerRequest($method = 'GET', $uri = '', $serverParams = array())
    {
        return new ServerRequest($method, $uri, $serverParams);
    }

    protected static function createStream($mixed = null)
    {
        return new Stream($mixed);
    }

    /**
     * Create Uploaded File
     *
     * @param mixed  $streamOrFile    stream, resource, or filepath
     * @param int    $size            Size in bytes
     * @param int    $error           one of the UPLOAD_ERR_* constants
     * @param string $clientFilename  client file name
     * @param string $clientMediaType client mime type
     * @param string $clientFullPath  client full path (as of php 8.1)
     *
     * @return UploadedFile
     */
    protected static function createUploadedFile($streamOrFile, $size = null, $error = UPLOAD_ERR_OK, $clientFilename = null, $clientMediaType = null, $clientFullPath = null)
    {
        return new UploadedFile($streamOrFile, $size, $error, $clientFilename, $clientMediaType, $clientFullPath);
    }

    protected static function createUri($value = '')
    {
        return new Uri($value);
    }
}

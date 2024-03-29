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

namespace bdk\HttpMessage\Utility;

use bdk\HttpMessage\ServerRequest as PsrServerRequest;
use bdk\HttpMessage\Stream;
use bdk\HttpMessage\UploadedFile;
use bdk\HttpMessage\Uri;
use bdk\HttpMessage\Utility\ContentType;
use bdk\HttpMessage\Utility\ParseStr;
use InvalidArgumentException;

/**
 * Build ServerRequest from globals ($_SERVER, $_COOKIE, $_POST, $_FILES)
 */
class ServerRequest
{
    /** @var non-empty-string used for unit tests */
    public static $inputStream = 'php://input';

    /**
     * Instantiate self from superglobals
     *
     * @param array $parseStrOpts Parse options (default: {convDot:false, convSpace:false})
     *
     * @return PsrServerRequest
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function fromGlobals($parseStrOpts = array())
    {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? $_SERVER['REQUEST_METHOD']
            : 'GET';
        $uri = Uri::fromGlobals();
        $files = self::filesFromGlobals($_FILES);
        $serverRequest = new PsrServerRequest($method, $uri, $_SERVER);
        $contentType = $serverRequest->getHeaderLine('Content-Type');
        // note: php://input not available with content-type = "multipart/form-data".
        $parsedBody = $method !== 'GET'
            ? self::postFromInput($contentType, self::$inputStream, $parseStrOpts) ?: $_POST
            : null;
        $query = $uri->getQuery();
        $queryParams = ParseStr::parse($query, $parseStrOpts);
        return $serverRequest
            ->withBody(new Stream(
                PHP_VERSION_ID < 70000
                    ? \stream_get_contents(\fopen('php://input', 'r+')) // prev 5.6 is not seekable / read once.. still not reliable in 5.6
                    : \fopen('php://input', 'r+')
            ))
            ->withCookieParams($_COOKIE)
            ->withParsedBody($parsedBody)
            ->withQueryParams($queryParams)
            ->withUploadedFiles($files);
    }

    /**
     * Create UploadedFiles tree from $_FILES
     *
     * @param array    $phpFiles $_FILES type array
     * @param string[] $path     {@internal} Path to current value
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private static function filesFromGlobals(array $phpFiles, array $path = array())
    {
        $files = array();
        /** @var mixed $value */
        foreach ($phpFiles as $key => $value) {
            $pathCurKey = $path;
            $pathCurKey[] = (string) $key;
	        if (\is_array($value) === false) {
	            throw new InvalidArgumentException(\sprintf(
	                'Invalid value in files specification at %s.  Array expected.  %s provided.',
	                \implode('.', $pathCurKey),
	                \gettype($value)
	            ));
	        }
	        if (self::isUploadFileInfoArray($value)) {
	        	$files[$key] = self::fileFromGlobalCreate($value);
	        	continue;
	        }
            $files[$key] = self::filesFromGlobals($value, $pathCurKey);
        }
        return $files;
    }

    /**
     * Create UploadedFile(s) from $_FILES entry
     *
     * @param array{
     *   name: array|string,
     *   type: array|string,
     *   tmp_name: array|string,
     *   size: array|int,
     *   error: array|int,
     *   full_path: array|string} $fileInfo $_FILES entry
     *
     * @return UploadedFile|array
     *
     * @psalm-suppress PossiblyInvalidArrayAccess
     * @psalm-suppress PossiblyInvalidArrayOffset
     * @psalm-suppress MixedArgumentTypeCoercion doesn't trust array being passed to fileFromGlobalCreate
     */
    private static function fileFromGlobalCreate(array $fileInfo)
    {
        if (\is_array($fileInfo['tmp_name']) === false) {
            return new UploadedFile($fileInfo);
        }
        /*
        <input type="file" name="foo[bar][a]">
        <input type="file" name="bar[baz][a]">
        will create something like
            'foo' => [
                'name' => [
                    'bar' => [
                        'a' => 'test2.jpg',
                        'b' => 'test3.jpg',
                    ],
                ],
                'type' => [
                    'bar' => []
                        'a' => 'image/jpeg',
                        'b' => 'image/jpeg',
                    ],
                ],
                ...
            ]
        */
        $files = array();
        $keys = \array_keys($fileInfo['tmp_name']);
        foreach ($keys as $key) {
            $files[$key] = self::fileFromGlobalCreate(array(
                'error'    => $fileInfo['error'][$key],
                'full_path' => isset($fileInfo['full_path'][$key])
                    ? $fileInfo['full_path'][$key]
                    : null,
                'name'     => $fileInfo['name'][$key],
                'size'     => $fileInfo['size'][$key],
                'tmp_name' => $fileInfo['tmp_name'][$key],
                'type'     => $fileInfo['type'][$key],
            ));
        }
        return $files;
    }

    /**
     * Is the given Content-Type parsable
     *
     * @param string $contentType Content-Type / Mime-Type
     *
     * @return bool
     */
    private static function isContentTypeParsable($contentType)
    {
        $parsableTypes = array(
            ContentType::FORM,
            ContentType::FORM_MULTIPART, // would be parsable... but php doesn't make available
            ContentType::JSON,
        );
        return \in_array($contentType, $parsableTypes, true);
    }

    /**
     * Are we uploaded file info array?  ('tmp_name', 'size', 'error', name', 'type'...
     *
     * Don't base this off a single key like 'tmp_name'.
     *   <input type="file" name="tmp_name" "some dingus named this tmp_name" />
     *
     * @param array $array branch of $_FILES structure
     *
     * @return bool
     *
     * @psalm-assert-if-true array{
     *   name: array|string,
     *   type: array|string,
     *   tmp_name: array|string,
     *   size: array|int,
     *   error: array|int,
     *   full_path: array|string} $array
     */
    private static function isUploadFileInfoArray(array $array)
    {
        $keysMustHave = array('name', 'type', 'tmp_name', 'size', 'error');
        $keysMayHave = array('full_path');
        $keys = \array_keys($array);
        if (\array_intersect($keysMustHave, $keys) !== $keysMustHave) {
            // missing must have
            return false;
        }
        // return true if no unknown keys
        return \array_diff($keys, \array_merge($keysMustHave, $keysMayHave)) === array();
    }

    /**
     * Get parsed body (POST data)
     *
     * Note: this will return null if content-type = "multipart/form-data" and input = "php://input"
     *
     * @param string $contentType  Content-Type header value
     * @param string $input        ('php://input') specify input
     * @param array  $parseStrOpts Parse options (default: {convDot:false, convSpace:false})
     *
     * @return array|null
     */
    private static function postFromInput($contentType, $input = 'php://input', array $parseStrOpts = array())
    {
        $contentType = \preg_replace('/\s*[;,].*$/', '', $contentType);
        $contentType = \strtolower($contentType);
        if (self::isContentTypeParsable($contentType) === false) {
            return null;
        }
        $rawBody = \file_get_contents($input);
        if ($rawBody === '') {
            return null;
        }
        if ($contentType !== ContentType::JSON) {
            return ParseStr::parse($rawBody, $parseStrOpts);
        }
        /** @var array */
        $jsonParsedBody = \json_decode($rawBody, true);
        return \json_last_error() === JSON_ERROR_NONE
            ? $jsonParsedBody
            : null;
    }
}

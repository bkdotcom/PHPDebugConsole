<?php

/**
 * This file is part of HttpMessage
 *
 * @package   bdk/http-message
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v1.0
 */

namespace bdk\HttpMessage;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Assertions for Message, Request, ServerRequest, & Response
 */
trait AssertionTrait
{
    /**
     * Test that value is a string (or optionally numeric)
     *
     * @param string $value        The value to check.
     * @param string $what         The name of the value.
     * @param bool   $allowNumeric Allow float or int?
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertString($value, $what = '', $allowNumeric = false)
    {
        if (\is_string($value)) {
            return;
        }
        if ($allowNumeric && \is_numeric($value)) {
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            '%s must be a string, but %s provided.',
            \ucfirst($what),
            $this->getTypeDebug($value)
        ));
    }

    /**
     * Get the value's type
     *
     * @param mixed $value Value to inspect
     *
     * @return string
     */
    protected static function getTypeDebug($value)
    {
        return \is_object($value)
            ? \get_class($value)
            : \gettype($value);
    }

    /*
        Message assertions
    */

    /**
     * Test valid header name
     *
     * @param string $name header name
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertHeaderName($name)
    {
        $this->assertString($name, 'Header name', true);
        if ($name === '') {
            throw new InvalidArgumentException('Header name can not be empty.');
        }
        /*
            see https://datatracker.ietf.org/doc/html/rfc7230#section-3.2.6
            alpha  => a-zA-Z
            digit  => 0-9
            others => !#$%&\'*+-.^_`|~
        */
        if (\preg_match('/^[a-zA-Z0-9!#$%&\'*+-.^_`|~]+$/', (string) $name) !== 1) {
            throw new InvalidArgumentException(\sprintf(
                '"%s" is not valid header name, it must be an RFC 7230 compatible string.',
                $name
            ));
        }
    }

    /**
     * Test valid header value
     *
     * @param array|string $value header value
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertHeaderValue($value)
    {
        if (\is_scalar($value) && \is_bool($value) === false) {
            $value = array((string) $value);
        }
        if (\is_array($value) === false) {
            throw new InvalidArgumentException(\sprintf(
                'The header field value only accepts string and array, but %s provided.',
                self::getTypeDebug($value)
            ));
        }
        if (empty($value)) {
            throw new InvalidArgumentException(
                'Header value can not be empty array.'
            );
        }
        foreach ($value as $item) {
            $this->assertHeaderValueLine($item);
        }
    }

    /**
     * Validate header value
     *
     * @param mixed $value Header value to test
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function assertHeaderValueLine($value)
    {
        if ($value === '') {
            return;
        }
        $this->assertString($value, 'Header value', true);
        /*
            https://www.rfc-editor.org/rfc/rfc7230.txt (page.25)

            field-content = field-vchar [ 1*( SP / HTAB ) field-vchar ]
            field-vchar   = VCHAR / obs-text
            obs-text      = %x80-FF (character range outside ASCII.)
                             NOT ALLOWED
            SP            = space
            HTAB          = horizontal tab
            VCHAR         = any visible [USASCII] character. (x21-x7e)
        */
        if (\preg_match('/^[ \t\x21-\x7e]+$/', (string) $value) !== 1) {
            throw new InvalidArgumentException(\sprintf(
                '"%s" is not valid header value, it must contains visible ASCII characters only.',
                $value
            ));
        }
    }

    /**
     * Check out whether a protocol version number is supported.
     *
     * @param string $version HTTP protocol version.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function assertProtocolVersion($version)
    {
        if (\is_numeric($version) === false) {
            throw new InvalidArgumentException(\sprintf(
                'Unsupported HTTP protocol version number. %s provided.',
                self::getTypeDebug($version)
            ));
        }
        if (\in_array((string) $version, $this->validProtocolVers, true) === false) {
            throw new InvalidArgumentException(\sprintf(
                'Unsupported HTTP protocol version number. "%s" provided.',
                $version
            ));
        }
    }

    /*
        Request assertions
    */

    /**
     * Assert valid method
     *
     * @param string $method Http methods
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertMethod($method)
    {
        $this->assertString($method, 'HTTP method');
        if ($method === '') {
            throw new InvalidArgumentException('Method must be a non-empty string.');
        }
        if (\preg_match('/^[a-z]+$/i', $method) !== 1) {
            throw new InvalidArgumentException('Method name must contain only ASCII alpha characters');
        }
    }

    /*
        ServerRequest assertions
    */

    /**
     * Assert valid attribute name
     *
     * @param string $name  Attribute name
     * @param bool   $throw (false) Whether to throw InvalidArgumentException
     *
     * @return bool
     * @throws InvalidArgumentException if $throw === true
     */
    protected function assertAttributeName($name, $throw = true)
    {
        try {
            $this->assertString($name, 'Attribute name', true);
        } catch (InvalidArgumentException $e) {
            if ($throw) {
                throw $e;
            }
            return false;
        }
        return true;
    }

    /**
     * Assert valid cookie parameters
     *
     * @param array $cookies Cookie parameters
     *
     * @return void
     * @throws InvalidArgumentException
     *
     * @see https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html#name-syntax
     */
    protected function assertCookieParams($cookies)
    {
        $nameRegex = '/^[!#-+\--:<-[\]-~]+$/';
        \array_walk($cookies, function ($value, $name) use ($nameRegex) {
            if (\preg_match($nameRegex, $name) !== 1) {
                throw new InvalidArgumentException(\sprintf(
                    'Invalid cookie name specified: %s',
                    $name
                ));
            }
            $this->assertString($value, 'Cookie value', true);
        });
    }

    /**
     * Assert valid query parameters
     *
     * @param array $get Query parameters
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function assertQueryParams($get)
    {
        \array_walk_recursive($get, function ($value) {
            $this->assertString($value, 'Query param value');
        });
    }

    /**
     * Throw an exception if an unsupported argument type is provided.
     *
     * @param array|object|null $data The deserialized body data. This will
     *     typically be in an array or object.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertParsedBody($data)
    {
        if (
            $data === null ||
            \is_array($data) ||
            \is_object($data)
        ) {
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            'ParsedBody must be array, object, or null, but %s provided.',
            self::getTypeDebug($data)
        ));
    }

    /**
     * Recursively validate the structure in an uploaded files array.
     *
     * @param array $uploadedFiles uploaded files tree
     *
     * @return void
     *
     * @throws InvalidArgumentException if any leaf is not an UploadedFileInterface instance.
     */
    protected function assertUploadedFiles($uploadedFiles)
    {
        \array_walk_recursive($uploadedFiles, static function ($val) {
            if (!($val instanceof UploadedFileInterface)) {
                throw new InvalidArgumentException(\sprintf(
                    'Invalid file in uploaded files structure. Expected UploadedFileInterface, but %s provided',
                    self::getTypeDebug($val)
                ));
            }
        });
    }

    /*
        Response assertions
    */

    /**
     * Validate reason phrase
     *
     * @param string $phrase Reason phrase to test
     *
     * @return void
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7230#section-3.1.2
     *
     * @throws InvalidArgumentException
     */
    protected function assertReasonPhrase($phrase)
    {
        if ($phrase === '') {
            return;
        }
        $this->assertString($phrase, 'Reason-phrase');
        // Don't allow control characters (incl \r & \n)
        if (\preg_match('#[^\P{C}\t]#u', $phrase, $matches, PREG_OFFSET_CAPTURE) === 1) {
            throw new InvalidArgumentException(\sprintf(
                'Reason phrase contains a prohibited character at position %s.',
                $matches[0][1]
            ));
        }
    }

    /**
     * Validate status code
     *
     * @param int $code Status Code
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertStatusCode($code)
    {
        if (\is_string($code) && \preg_match('/^\d+$/', $code)) {
            $code = (int) $code;
        }
        if (\is_int($code) === false) {
            throw new InvalidArgumentException(\sprintf(
                'Status code must to be an integer, but %s provided',
                self::getTypeDebug($code)
            ));
        }
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException(\sprintf(
                'Status code has to be an integer between 100 and 599. A status code of %d was given',
                $code
            ));
        }
    }

    /*
        Uri assertions
    */

    /**
     * Throw exception if invalid host string.
     *
     * @param string $host The host string to of a URI.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertHost($host)
    {
        $this->assertString($host, 'host');
        if (\in_array($host, array('','localhost'), true)) {
            // An empty host value is equivalent to removing the host.
            // No validation required
            return;
        }
        if ($this->isFqdn($host)) {
            return;
        }
        if (\filter_var($host, FILTER_VALIDATE_IP)) {
            // only if php < 7.0
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            '"%s" is not a valid host',
            $host
        ));
    }

    /**
     * Throw exception if invalid port value
     *
     * @param int $port port value
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertPort($port)
    {
        if (\is_int($port) === false) {
            throw new InvalidArgumentException(\sprintf(
                'Port must be a int, but %s provided.',
                $this->getTypeDebug($port)
            ));
        }
        if ($port < 1 || $port > 0xffff) {
            throw new InvalidArgumentException(\sprintf('Invalid port: %d. Must be between 0 and 65535', $port));
        }
    }

    /**
     * Assert valid scheme
     *
     * @param string $scheme Scheme to validate
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function assertScheme($scheme)
    {
        $this->assertString($scheme, 'scheme');
        if ($scheme === '') {
            return;
        }
        if (\preg_match('/^[a-z][-a-z0-9.+]*$/i', $scheme) !== 1) {
            throw new InvalidArgumentException(\sprintf(
                'Invalid scheme: "%s"',
                $scheme
            ));
        }
    }

    /**
     * Test if hostname is a fully-qualified domain naim
     *
     * @param string $host Hostname to test
     *
     * @return bool
     *
     * @see https://www.regextester.com/103452
     */
    private function isFqdn($host)
    {
        if (PHP_VERSION_ID >= 70000) {
            return \filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
        }
        $regexPartialHostname = '(?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]';
        $regex1 = '/(?=^.{4,253}$)(^(' . $regexPartialHostname . '\.)+[a-zA-Z]{2,63}$)/';
        $regex2 = '/^' . $regexPartialHostname . '$/';
        return \preg_match($regex1, $host) === 1 || \preg_match($regex2, $host) === 1;
    }
}

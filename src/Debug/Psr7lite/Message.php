<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Psr7lite;

use bdk\Debug\Psr7lite\Stream;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * INTERNAL USE ONLY
 *
 * @psalm-consistent-constructor
 */
class Message
{

    /**
     * @var StreamInterface|Stream
     */
    private $body;

    /**
     * @var array Map of all registered headers, as name => array of values
     */
    private $headers = array();

    /**
     * @var array Map of lowercase header name => original name at registration
     */
    private $headerNames = array();

    /** @var string */
    private $method = 'GET';

    /** @var string */
    private $protocolVersion = '1.1';

    /**
     * Valid HTTP version numbers.
     *
     * @var array
     */
    protected $validProtocolVers = array(
        '1.1',
        '2.0',
        '3.0',
    );

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * @return string HTTP protocol version (e.g., "1.1", "1.0").
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * @param string $version HTTP protocol version
     *
     * @return static
     */
    public function withProtocolVersion($version)
    {
        if ($version === $this->protocolVersion) {
            return $this;
        }
        $this->assertProtocolVersion($version);
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    /**
     * @return string[][] Returns an associative array of the message's headers. Each
     *     key is a header name, and each value is an array of strings for that header.
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name.
     *
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($name)
    {
        $nameLower = \strtolower($name);
        return isset($this->headerNames[$nameLower]);
    }

    /**
     * @param string $name header name
     *
     * @return string[] An array of string values as provided for the given
     *    header. If the header does not appear in the message, an empty array is returned.
     */
    public function getHeader($name)
    {
        $nameLower = \strtolower($name);
        if (!isset($this->headerNames[$nameLower])) {
            return array();
        }
        $name = $this->headerNames[$nameLower];
        return $this->headers[$name];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method will return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     *
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method will return an empty string.
     */
    public function getHeaderLine($name)
    {
        return \implode(', ', $this->getHeader($name));
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * @param string          $name  Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     *
     * @return static
     * @throws InvalidArgumentException for invalid header names or values.
     */
    public function withHeader($name, $value)
    {
        $this->assertHeaderName($name);
        $value = $this->normalizeHeaderValue($value);
        $nameLower = \strtolower($name);
        $new = clone $this;
        if (isset($new->headerNames[$nameLower])) {
            // remove previous header-name
            $namePrev = $new->headerNames[$nameLower];
            unset($new->headers[$namePrev]);
        }
        $new->headerNames[$nameLower] = $name;
        $new->headers[$name] = $value;
        if ($nameLower === 'host') {
            // Ensure Host is the first header.
            // See: http://tools.ietf.org/html/rfc7230#section-5.4
            $order = array($name);
            \uksort($new->headers, function ($keyA, $keyB) use ($order) {
                $aPos = \array_search($keyA, $order);
                $bPos = \array_search($keyB, $order);
                if ($aPos === false && $bPos === false) {   // both items are dont cares
                    return 0;
                }
                if ($aPos === false) {                      // $a is a dont care
                    return 1;                               //   $a > $b
                }
                if ($bPos === false) {                      // $b is a dont care
                    return -1;                              //   $a < $b
                }
            });
        }
        return $new;
    }

    /**
     * Return an instance with the specified header values appended to the current value
     *
     * Existing values for the specified header will be maintained.
     * The new value(s) will be appended to the existing list.
     * If the header did not exist previously, it will be added.
     *
     * @param string          $name  Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     *
     * @return static
     * @throws InvalidArgumentException for invalid header names.
     * @throws InvalidArgumentException for invalid header values.
     */
    public function withAddedHeader($name, $value)
    {
        $new = clone $this;
        $new->setHeaders(array(
            $name => $value,
        ));
        return $new;
    }

    /**
     * Return an instance without the specified header.
     *
     * @param string $name Case-insensitive header field name to remove.
     *
     * @return static
     */
    public function withoutHeader($name)
    {
        $nameLower = \strtolower($name);
        if (!isset($this->headerNames[$nameLower])) {
            return $this;
        }
        $new = clone $this;
        unset($new->headers[$name], $new->headerNames[$nameLower]);
        return $new;
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface|Stream The body as a stream.
     */
    public function getBody()
    {
        if (!$this->body) {
            $this->body = new Stream();
        }
        return $this->body;
    }

    /**
     * Return an instance with the specified message body.
     *
     * @param StreamInterface|Stream $body Body
     *
     * @return static
     * @throws \InvalidArgumentException
     */
    public function withBody($body)
    {
        if (!($body instanceof StreamInterface) && !($body instanceof Stream)) {
            throw new \InvalidArgumentException('body must be an instance of StreamInterface');
        }
        if ($body === $this->body) {
            return $this;
        }
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    /**
     * Test valid header name
     *
     * @param string $name header name
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected static function assertHeaderName($name)
    {
        if (!\is_string($name)) {
            throw new InvalidArgumentException(\sprintf(
                'Header name must be a string but "%s" provided.',
                \is_object($name) ? \get_class($name) : \gettype($name)
            ));
        }
        if ($name === '') {
            throw new InvalidArgumentException('Header name can not be empty.');
        }
        /*
            see https://tools.ietf.org/html/rfc7230#section-3.2.6
            alpha  => a-zA-Z
            digit  => 0-9
            others => !#$%&\'*+-.^_`|~
        */
        if (!\preg_match('/^[a-zA-Z0-9!#$%&\'*+-.^_`|~]+$/', $name)) {
            throw new InvalidArgumentException(\sprintf(
                '"%s" is not valid header name, it must be an RFC 7230 compatible string.',
                $name
            ));
        }
    }

    /**
     * Test valid header value
     *
     * @param string $value header value
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected static function assertHeaderValue($value = null)
    {
        if (\is_scalar($value) && !\is_bool($value)) {
            $value = array((string) $value);
        }
        if (!\is_array($value)) {
            throw new InvalidArgumentException(\sprintf(
                'The header field value only accepts string and array, but "%s" provided.',
                \is_object($value) ? \get_class($value) : \gettype($value)
            ));
        }
        if (empty($value)) {
            throw new InvalidArgumentException(
                'Header value can not be empty array.'
            );
        }
        foreach ($value as $item) {
            if ($item === '') {
                continue;
            }
            if (!\is_scalar($item) || \is_bool($item)) {
                throw new InvalidArgumentException(\sprintf(
                    'The header values only accept string and number, but "%s" provided.',
                    \is_object($item) ? \get_class($item) : \gettype($item)
                ));
            }

            /*
            https://www.rfc-editor.org/rfc/rfc7230.txt (page.25)
            field-content = field-vchar [ 1*( SP / HTAB ) field-vchar ]
            field-vchar   = VCHAR / obs-text
            obs-text      = %x80-FF
            SP            = space
            HTAB          = horizontal tab
            VCHAR         = any visible [USASCII] character. (x21-x7e)
            %x80-FF       = character range outside ASCII.
            obs-text SHOULD N0T BE USED.
            OR EVEN I CAN PASS CHINESE CHARACTERS, THAT'S WEIRD.
            */

            if (!\preg_match('/^[ \t\x21-\x7e]+$/', $item)) {
                throw new InvalidArgumentException(\sprintf(
                    '"%s" is not valid header value, it must contains visible ASCII characters only.',
                    $item
                ));
            }
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
    protected function assertProtocolVersion(string $version): void
    {
        if (!\in_array($version, $this->validProtocolVers)) {
            throw new InvalidArgumentException(\sprintf(
                'Unsupported HTTP protocol version number. "%s" provided.',
                $version
            ));
        }
    }

    /**
     * Get all HTTP header key/values as an associative array for the current request.
     *
     * Uses getallheaders (aka apache_request_headers) if avail / falls back to $_SERVER vals
     *
     * @param array $serverParams $_SERVER
     *
     * @return string[string] The HTTP header key/value pairs.
     */
    protected static function getAllHeaders($serverParams)
    {
        if (\function_exists('getallheaders')) {
            return \getallheaders();
        }
        $headers = array();
        $keysSansHttp = array(
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        );
        foreach ($serverParams as $key => $value) {
            if (\substr($key, 0, 5) === 'HTTP_') {
                $key = \substr($key, 5);
                if (!isset($keysSansHttp[$key]) || !isset($serverParams[$key])) {
                    $key = \str_replace(' ', '-', \ucwords(\strtolower(\str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } elseif (isset($keysSansHttp[$key])) {
                $headers[$keysSansHttp[$key]] = $value;
            }
        }
        if (!isset($headers['Authorization'])) {
            $auth = self::getAuthorizationHeader($serverParams);
            if ($auth) {
                $headers['Authorization'] = $auth;
            }
        }
        return $headers;
    }

    /**
     * Build Authorization header from $_SERVER values
     *
     * @param array $serverParams $_SERVER vals
     *
     * @return null|string
     */
    private static function getAuthorizationHeader($serverParams)
    {
        $auth = null;
        if (isset($serverParams['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $serverParams['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (isset($serverParams['PHP_AUTH_USER'])) {
            $basicPass = isset($serverParams['PHP_AUTH_PW']) ? $serverParams['PHP_AUTH_PW'] : '';
            $auth = 'Basic ' . \base64_encode($serverParams['PHP_AUTH_USER'] . ':' . $basicPass);
        } elseif (isset($serverParams['PHP_AUTH_DIGEST'])) {
            $auth = $serverParams['PHP_AUTH_DIGEST'];
        }
        return $auth;
    }

    /**
     * Trim header value(s)
     *
     * @param string|array $value header value
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private static function normalizeHeaderValue($value)
    {
        self::assertHeaderValue($value);
        return \array_values(self::trimHeaderValues($value));
    }

    /**
     * Set header values
     *
     * @param array $headers header name/value pairs
     *
     * @return void
     */
    protected function setHeaders($headers = array())
    {
        foreach ($headers as $name => $value) {
            if (\is_int($name)) {
                // Numeric array keys are converted to int by PHP but having a header name '123' is not forbidden by the spec
                // and also allowed in withHeader(). So we need to cast it to string again for the following assertion to pass.
                $name = (string) $name;
            }
            self::assertHeaderName($name);
            $values = $this->normalizeHeaderValue($value);
            $nameLower = \strtolower($name);
            if (isset($this->headerNames[$nameLower])) {
                $name = $this->headerNames[$nameLower];
                $this->headers[$name] = \array_merge($this->headers[$name], $values);
                continue;
            }
            $this->headerNames[$nameLower] = $name;
            $this->headers[$name] = $values;
        }
    }

    /**
     * Trims whitespace from the header values.
     *
     * Spaces and tabs ought to be excluded by parsers when extracting the field value from a header field.
     *
     * header-field = field-name ":" OWS field-value OWS
     * OWS          = *( SP / HTAB )
     *
     * @param string[] $values Header values
     *
     * @return string[] Trimmed header values
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     */
    private static function trimHeaderValues($values = array())
    {
        return \array_map(function ($value) {
            if (!\is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException(\sprintf(
                    'Header value must be scalar or null but %s provided.',
                    \is_object($value) ? \get_class($value) : \gettype($value)
                ));
            }
            return \trim((string) $value, " \t");
        }, (array) $values);
    }
}

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

use InvalidArgumentException;

/**
 * INTERNAL USE ONLY
 */
class Uri
{

    private const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
    private const SCHEMES = array(
        'ftp' => 21,
        'http' => 80,
        'https' => 443,
    );

    /** @var string Uri scheme. */
    private $scheme = '';

    /** @var string Uri user info. */
    private $userInfo = '';

    /** @var string Uri host. */
    private $host = '';

    /** @var null|int Uri port. */
    private $port;

    /** @var string Uri path. */
    private $path = '';

    /** @var string Uri query string. */
    private $query = '';

    /** @var string Uri fragment. */
    private $fragment = '';

    /**
     * Constructor
     *
     * @param string|null $uri Uri to wrap
     *
     * @throws InvalidArgumentException
     */
    public function __construct($uri = '')
    {
        if ($uri === null) {
            $uri = '';
        }
        $this->assertString($uri, 'uri');
        if ($uri === '') {
            return;
        }
        $parts = \parse_url($uri);
        if ($parts === false) {
            throw new InvalidArgumentException('Unable to parse URI: ' . $uri);
        }
        $partFilters = array(
            'scheme' => 'lowercase',
            'host' => 'lowercase',
            'port' => 'filterPort',
            'path' => 'filterPath',
            'query' => 'filterQueryAndFragment',
            'fragment' => 'filterQueryAndFragment',
        );
        foreach ($partFilters as $part => $filter) {
            if (isset($parts[$part])) {
                $this->{$part} = $this->{$filter}($parts[$part]);
            }
        }
        if (isset($parts['user'])) {
            $this->userInfo = $parts['user'];
        }
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $parts['pass'];
        }
    }

    /**
     * Return stringified value
     *
     * @return string
     */
    public function __toString()
    {
        return self::createUriString(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $this->fragment
        );
    }

    /**
     * Retrieve the scheme component of the URI.
     *
     * @return string
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * Retrieve the authority component of the URI.
     *
     * If the port component is not set or is the standard port for the current
     * scheme, it will not be included
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2
     *
     * @return string The URI authority, in "[user-info@]host[:port]" format. (or empty string)
     */
    public function getAuthority()
    {
        if ($this->host === '') {
            return '';
        }
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }
        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }

    /**
     * Retrieve the user information component of the URI.
     *
     * If a user is present in the URI, this will return that value;
     * additionally, if the password is also present, it will be appended to the
     * user value, with a colon (":") separating the values.
     *
     * The trailing "@" character is not part of the user information and will not be included
     *
     * @return string The URI user information, in "username[:password]" format. (or empty string)
     */
    public function getUserInfo()
    {
        return $this->userInfo;
    }

    /**
     * Retrieve the host component of the URI.
     *
     * The value returned will be normalized to lowercase, per RFC 3986
     * Section 3.2.2.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return string The URI host (or empty string).
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Retrieve the port component of the URI.
     *
     * If the port is the standard port used with the current scheme, null will be returned.
     *
     * If no port is present, and no scheme is present, null will be returned
     *
     * If no port is present, but a scheme is present, null will be returned.
     *
     * @return null|int The URI port.
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Retrieve the path component of the URI.
     *
     * The path can either be
     *    empty or
     *    absolute (starting with a slash)
     *    rootless (not starting with a slash).
     *
     * Normally, the empty path "" and absolute path "/" are considered equal as
     * defined in RFC 7230 Section 2.7.3. But this method does automatically
     * do this normalization because in contexts with a trimmed base path, e.g.
     * the front controller, this difference becomes significant.
     * It's the task of the user to handle both "" and "/".
     *
     * The value returned will be percent-encoded, but will not double-encode
     * any characters.
     * see RFC 3986, Sections 2 and 3.3.
     *
     * As an example, if the value should include a slash ("/") not intended as
     * delimiter between path segments, that value will be passed in encoded
     * form (e.g., "%2F") to the instance.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-2
     * @see https://tools.ietf.org/html/rfc3986#section-3.3
     *
     * @return string The URI path.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Retrieve the query string of the URI.
     *
     * The leading "?" character is not part of the query and will not be
     * included.
     *
     * The value returned will be percent-encoded, but will not double-encode
     * any characters.
     * see RFC 3986, Sections 2 and 3.4.
     *
     * As an example, if a value in a key/value pair of the query string should
     * include an ampersand ("&") not intended as a delimiter between values,
     * that value MUST be passed in encoded form (e.g., "%26") to the instance.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-2
     * @see https://tools.ietf.org/html/rfc3986#section-3.4
     *
     * @return string The URI query string. (or empty string)
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Retrieve the fragment component of the URI.
     *
     * The leading "#" character is not part of the fragment and MUST NOT be
     * added.
     *
     * The value returned will be percent-encoded, but will not double-encode
     * any characters.
     * see RFC 3986, Sections 2 and 3.5.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-2
     * @see https://tools.ietf.org/html/rfc3986#section-3.5
     *
     * @return string The URI fragment. (or empty string)
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     * Return an instance with the specified scheme.
     *
     * An empty scheme is equivalent to removing the scheme.
     *
     * @param string $scheme The scheme to use with the new instance.
     *
     * @return static A new instance with the specified scheme.
     *
     * @throws InvalidArgumentException for invalid schemes.
     * @throws InvalidArgumentException for unsupported schemes.
     */
    public function withScheme($scheme)
    {
        $this->assertString($scheme, 'scheme');
        $scheme = self::lowercase($scheme);
        if ($scheme === $this->scheme) {
            return $this;
        }
        $new = clone $this;
        $new->scheme = $scheme;
        $new->port = $new->filterPort($new->port);
        return $new;
    }

    /**
     * Return an instance with the specified user information.
     *
     * Password is optional, but the user information MUST include the
     * user; an empty string for the user is equivalent to removing user
     * information.
     *
     * @param string      $user     The user name to use for authority.
     * @param null|string $password The password associated with $user.
     *
     * @return static A new instance with the specified user information.
     */
    public function withUserInfo($user, $password = null)
    {
        $this->assertString($user, 'user');
        $info = $user;
        if ($password !== null && $password !== '') {
            $this->assertString($password, 'password');
            $info .= ':' . $password;
        }
        if ($info === $this->userInfo) {
            return $this;
        }
        $new = clone $this;
        $new->userInfo = $info;
        return $new;
    }

    /**
     * Return an instance with the specified host.
     *
     * An empty host value is equivalent to removing the host.
     *
     * @param string $host The hostname to use with the new instance.
     *
     * @return static A new instance with the specified host.
     *
     * @throws InvalidArgumentException for invalid hostnames.
     */
    public function withHost($host)
    {
        $this->assertHost($host);
        $host = self::lowercase($host);
        if ($host === $this->host) {
            return $this;
        }
        $new = clone $this;
        $new->host = $host;
        return $new;
    }

    /**
     * Return an instance with the specified port.
     *
     * A null value provided for the port is equivalent to removing the port
     * information.
     *
     * @param null|int $port The port to use with the new instance;
     *                          a null value removes the port information.
     *
     * @return static A new instance with the specified port.
     * @throws InvalidArgumentException for invalid ports.
     */
    public function withPort($port)
    {
        $port = $this->filterPort($port);
        if ($port === $this->port) {
            return $this;
        }
        $new = clone $this;
        $new->port = $port;
        return $new;
    }

    /**
     * Return an instance with the specified path.
     *
     * The path can either be
     *     empty
     *     absolute (starting with a slash)
     *     rootless (not starting with a slash).
     *
     * If an HTTP path is intended to be host-relative rather than path-relative
     * then it must begin with a slash ("/"). HTTP paths not starting with a slash
     * are assumed to be relative to some base path known to the application or
     * consumer.
     *
     * Users can provide both encoded and decoded path characters.
     *
     * @param string $path The path to use with the new instance.
     *
     * @return static A new instance with the specified path.
     *
     * @throws InvalidArgumentException for invalid paths.
     */
    public function withPath($path)
    {
        $path = $this->filterPath($path);
        if ($path === $this->path) {
            return $this;
        }
        $new = clone $this;
        $new->path = $path;
        return $new;
    }

    /**
     * Return an instance with the specified query string.
     *
     * Users can provide both encoded and decoded query characters.
     *
     * An empty query string value is equivalent to removing the query string.
     *
     * @param string $query The query string to use with the new instance.
     *
     * @return static A new instance with the specified query string.
     * @throws InvalidArgumentException for invalid query strings.
     */
    public function withQuery($query)
    {
        $this->assertString($query, 'query');
        $query = $this->filterQueryAndFragment($query);
        if ($query === $this->query) {
            return $this;
        }
        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    /**
     * Return an instance with the specified URI fragment.
     *
     * Users can provide both encoded and decoded fragment characters.
     *
     * An empty fragment value is equivalent to removing the fragment.
     *
     * @param string $fragment The fragment to use with the new instance.
     *
     * @return static A new instance with the specified fragment.
     */
    public function withFragment($fragment)
    {
        $this->assertString($fragment, 'fragment');
        $fragment = $this->filterQueryAndFragment($fragment);
        if ($fragment === $this->fragment) {
            return $this;
        }
        $new = clone $this;
        $new->fragment = $fragment;
        return $new;
    }

    /**
     * Throw exception for the invalid host string.
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
        if ($host === '') {
            // Note: An empty host value is equivalent to removing the host.
            // So that if the host is empty, ignore the following check.
            return;
        }
        if (!\filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException(\sprintf(
                '"%s" is not a valid host',
                $host
            ));
        }
    }

    /**
     * Throw exception for the invalid value.
     *
     * @param string $value The value to check.
     * @param string $what  The name of the value.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertString($value, $what = '')
    {
        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf(
                '%s must be a string, but %s provided.',
                \ucfirst($what),
                \is_object($value) ? \get_class($value) : \gettype($value)
            ));
        }
    }

    /**
     * Create a URI string from its various parts.
     *
     * @param string $scheme    Scheme
     * @param string $authority Authority [user-info@]host[:port]
     * @param string $path      Path
     * @param string $query     Query
     * @param string $fragment  Fragment
     *
     * @return string
     */
    private static function createUriString($scheme, $authority, $path, $query, $fragment)
    {
        $uri = '';
        if ($scheme !== '') {
            $uri .= $scheme . ':';
        }
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }
        $uri .= self::createUriPath($authority, $path);
        if ($query !== '') {
            $uri .= '?' . $query;
        }
        if ($fragment !== '') {
            $uri .= '#' . $fragment;
        }
        return $uri;
    }

    /**
     * Create path component of Uri
     *
     * @param string $authority Authority [user-info@]host[:port]
     * @param string $path      Path
     *
     * @return string
     */
    private static function createUriPath($authority, $path)
    {
        if ($path === '') {
            return '';
        }
        if ($path[0] !== '/') {
            if ($authority !== '') {
                // If the path is rootless and an authority is present, the path MUST be prefixed by "/"
                $path = '/' . $path;
            }
        } elseif (isset($path[1]) && $path[1] === '/') {
            if ($authority === '') {
                // If the path is starting with more than one "/" and no authority is present, the
                // starting slashes MUST be reduced to one.
                $path = '/' . \ltrim($path, '/');
            }
        }
        return $path;
    }

    /**
     * Is a given port non-standard for the current scheme?
     *
     * @param string $scheme Scheme
     * @param int    $port   Port
     *
     * @return bool
     */
    private static function isNonStandardPort($scheme, $port)
    {
        return !isset(self::SCHEMES[$scheme]) || $port !== self::SCHEMES[$scheme];
    }

    /**
     * Filter/validate port
     *
     * @param null|int $port Port
     *
     * @return null|int
     * @throws InvalidArgumentException
     */
    private function filterPort($port)
    {
        if ($port === null) {
            return null;
        }
        if (\is_string($port) && \preg_match('/^\d+$/', $port)) {
            $port = (int) $port;
        }
        if (!\is_int($port)) {
            throw new InvalidArgumentException(\sprintf(
                'Port must be a int, but %s provided.',
                \is_object($port) ? \get_class($port) : \gettype($port)
            ));
        }
        if ($port < 0 || $port > 0xffff) {
            throw new InvalidArgumentException(\sprintf('Invalid port: %d. Must be between 0 and 65535', $port));
        }
        return self::isNonStandardPort($this->scheme, $port)
            ? $port
            : null;
    }

    /**
     * Filter/validate path
     *
     * @param string $path URI path
     *
     * @return string
     * @throws InvalidArgumentException
     */
    private function filterPath($path)
    {
        $this->assertString($path, 'path');
        $specPattern = '%:@\/';
        $encodePattern = '%(?![A-Fa-f0-9]{2})';
        $regex = '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . $specPattern . ']+|' . $encodePattern . ')/';
        return $this->regexEncode($regex, $path);
    }

    /**
     * Filter/validate query and fragment
     *
     * @param string $str query & frabment
     *
     * @return string
     * @throws InvalidArgumentException
     */
    private function filterQueryAndFragment($str)
    {
        $specPattern = '%:@\/\?';
        $encodePattern = '%(?![A-Fa-f0-9]{2})';
        $regex = '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . $specPattern . ']+|' . $encodePattern . ')/';
        return $this->regexEncode($regex, $str);
    }

    /**
     * Perform Locale-independent lowercasing
     *
     * @param string $str String to lowercase
     *
     * @return string
     */
    private static function lowercase($str)
    {
        return \strtr(
            $str,
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz'
        );
    }

    /**
     * Call rawurlencode on on match
     *
     * @param string $regex Regular expression
     * @param string $str   string
     *
     * @return string
     */
    private static function regexEncode($regex, $str)
    {
        return \preg_replace_callback($regex, function ($matches) {
            return \rawurlencode($matches[0]);
        }, $str);
    }
}

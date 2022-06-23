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

use bdk\HttpMessage\Message;
use Psr\Http\Message\ResponseInterface;

/**
 * Http Response
 *
 * @psalm-consistent-constructor
 */
class Response extends Message implements ResponseInterface
{
    /**
     * Map of standard HTTP status code/reason phrases
     *
     * @var array
     */
    private static $phrases = array(
        // 1xx: Informational
        // Request received, continuing process.
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',

        // 2xx: Success
        // The action was successfully received, understood, and accepted.
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',

        // 3xx: Redirection
        // Further action must be taken in order to complete the request.
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',

        // 4xx: Client Error
        // The request contains bad syntax or cannot be fulfilled.
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',

        // 5xx: Server Error
        // The server failed to fulfill an apparently valid request.
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    );

    /** @var string */
    private $reasonPhrase = '';

    /** @var int */
    private $statusCode = 200;

    /**
     * Constructor
     *
     * @param int    $code         The HTTP status code. Defaults to 200.
     * @param string $reasonPhrase The reason phrase to associate with the status code
     *     in the generated response.
     */
    public function __construct($code = 200, $reasonPhrase = null)
    {
        list($code, $reasonPhrase) = $this->filterCodePhrase($code, $reasonPhrase);
        $this->statusCode = $code;
        $this->reasonPhrase = $reasonPhrase;
    }

    /**
     * Get the "phrase" associated with the status code
     *
     * @param string $code 3-digit status code
     *
     * @return string
     */
    public static function codePhrase($code)
    {
        $code = (int) $code;
        return isset(self::$phrases[$code])
            ? self::$phrases[$code]
            : '';
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int Status code.
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Gets the response reason phrase associated with the status code.
     *
     * @return string Reason phrase; must return an empty string if none present.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7231#section-6
     * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     */
    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * If no reason phrase is specified, implementations MAY choose to default
     * to the RFC 7231 or IANA recommended reason phrase for the response's
     * status code.
     *
     * @param int    $code         The 3-digit integer result code to set.
     * @param string $reasonPhrase The reason phrase to use with the
     *     provided status code; if none is provided, implementations MAY
     *     use the defaults as suggested in the HTTP specification.
     *
     * @return static
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7231#section-6
     * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     *
     * @throws \InvalidArgumentException For invalid status code arguments.
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        list($code, $reasonPhrase) = $this->filterCodePhrase($code, $reasonPhrase);
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase;
        return $new;
    }

    /**
     * Filter/validate code and reason-phrase
     *
     * @param int    $code   Status Code
     * @param string $phrase Reason Phrase
     *
     * @return array code & phrase
     *
     * @throws InvalidArgumentException
     */
    private function filterCodePhrase($code, $phrase)
    {
        $this->assertStatusCode($code);
        $code = (int) $code;
        if ($phrase === null || $phrase === '') {
            $phrase = \array_key_exists($code, self::$phrases)
                ? self::$phrases[$code]
                : '';
        }
        $this->assertReasonPhrase($phrase);
        return array($code, $phrase);
    }
}

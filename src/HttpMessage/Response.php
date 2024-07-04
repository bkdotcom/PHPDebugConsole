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

use bdk\HttpMessage\Message;
use bdk\HttpMessage\Utility\Response as ResponseUtil;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Http Response
 *
 * @psalm-consistent-constructor
 */
class Response extends Message implements ResponseInterface
{
    /** @var string */
    private $reasonPhrase = '';

    /** @var int */
    private $statusCode = 200;

    /**
     * Constructor
     *
     * @param int         $code         The HTTP status code. Defaults to 200.
     * @param string|null $reasonPhrase The reason phrase to associate with the status code
     *                                    Defaults to standard phrase for given code
     */
    public function __construct($code = 200, $reasonPhrase = null)
    {
        list($code, $reasonPhrase) = $this->filterCodePhrase($code, $reasonPhrase);
        $this->statusCode = $code;
        $this->reasonPhrase = $reasonPhrase;
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
     * @throws InvalidArgumentException For invalid status code arguments.
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
     * @param int         $code   Status Code
     * @param string|null $phrase Reason Phrase
     *
     * @return array{0:int,1:string} code & phrase
     *
     * @throws InvalidArgumentException
     */
    private function filterCodePhrase($code, $phrase)
    {
        $this->assertStatusCode($code);
        $code = (int) $code;
        if ($phrase === null || $phrase === '') {
            $phrase = ResponseUtil::codePhrase($code);
        }
        $this->assertReasonPhrase($phrase);
        return array($code, $phrase);
    }
}

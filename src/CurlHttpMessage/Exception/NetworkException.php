<?php

/**
 * @package   bdk\curlhttpmessage
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\CurlHttpMessage\Exception;

use bdk\CurlHttpMessage\Exception\RequestException;

/**
 * Network Exception
 *
 * Failed http request due to a network related issue
 */
class NetworkException extends RequestException
{
}

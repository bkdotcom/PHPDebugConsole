<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Plugin;

use bdk\HttpMessage\Utility\ContentType;
use Psr\Http\Message\StreamInterface;

/**
 * Helper methods for LogRequest & LogResponse
 */
class AbstractLogReqRes
{
    /** @var array<string,mixed> */
    protected $cfg = array(
        'channelName' => 'Request / Response',
        'channelOpts' => array(
            'channelIcon' => 'fa fa-exchange',
            'channelSort' => 10,
            'nested' => false,
        ),
    );
    /** @var \bdk\Debug|null */
    protected $debug;
    /** @var string */
    protected $headerStyle = 'display:block; font-size:110%; font-weight:bold; padding:0.25em 0.5em; text-indent:0; border-bottom:#31708f 1px solid; background: linear-gradient(0deg, rgba(0,0,0,0.1) 0%, rgba(255,255,255,0.1) 100%);';

    /**
     * Display warning if body doesn't match supplied Content-Type
     *
     * If JSON or XML is posted using the default application/x-www-form-urlencoded Content-Type
     *   $_POST will be improperly populated
     *
     * @param string|null $contentTypeDetected Detected Content-Type
     * @param string|null $contentTypeUser     User provided Content-Type (request or response)
     * @param string|null $requestMethod       (null) Request method (or `null` if testing response)
     *
     * @return void
     */
    protected function assertCorrectContentType($contentTypeDetected, $contentTypeUser, $requestMethod = null)
    {
        $contentTypeDetected = \preg_replace('/\s*[;,].*$/', '', (string) $contentTypeDetected);
        $contentTypeUser = \preg_replace('/\s*[;,].*$/', '', (string) $contentTypeUser);
        if ($contentTypeDetected === $contentTypeUser) {
            return;
        }
        $message = \sprintf(
            'It appears %s %s %s',
            $contentTypeDetected,
            $requestMethod
                ? 'was received'
                : 'is being sent',
            $contentTypeUser
                ? 'with the wrong Content-Type'
                : 'without a Content-Type header'
        );
        $formTypes = array(ContentType::FORM, ContentType::FORM_MULTIPART);
        if ($requestMethod === 'POST' && \in_array($contentTypeUser, $formTypes, true)) {
            $message .= "\n" . 'Pay no attention to $_POST and instead use php://input';
        }
        $this->debug->warn($message, $this->debug->meta(array(
            'detectFiles' => false,
            'file' => null,
            'line' => null,
        )));
    }

    /**
     * Inspect content to determine mime-type
     *
     * @param StreamInterface|string $content         Reqeust/response body
     * @param string                 $contentTypeUser Content-Type provided with request or being sent with response
     *
     * @return string|null The detected content-type.  may contain the supplied character encoding / boundary
     */
    protected function detectContentType($content, $contentTypeUser)
    {
        $ctuTrimmed = \preg_replace('/\s*[;,].*$/', '', (string) $contentTypeUser);
        $contentTypeDetected = $this->debug->stringUtil->contentType($content);
        $xmlTypes = array(ContentType::XML, 'application/xml');
        $userIsXml = \in_array($ctuTrimmed, $xmlTypes, true)
            || \preg_match('/application\\/\S+\+xml/', $ctuTrimmed);
        if (
            \array_filter(array(
                \in_array($contentTypeDetected, $xmlTypes, true) && $userIsXml,
                $contentTypeDetected === ContentType::TXT && \in_array($ctuTrimmed, array(ContentType::FORM, ContentType::FORM_MULTIPART), true),
                $contentTypeDetected === 'application/x-empty',
            ))
        ) {
            $contentTypeDetected = $contentTypeUser;
        }
        return $contentTypeDetected;
    }
}

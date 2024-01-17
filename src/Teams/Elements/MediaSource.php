<?php

namespace bdk\Teams\Elements;

use bdk\Teams\AbstractItem;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * MediaSource element
 *
 * CURRENTLY NOT SUPPORTED BY TEAMS
 *
 * @see https://adaptivecards.io/explorer/MediaSource.html
 */
class MediaSource extends AbstractItem
{
    /**
     * Constructor
     *
     * @param string|UriInterface $url      URL
     * @param string              $mimeType Mime type
     */
    public function __construct($url = null, $mimeType = null)
    {
        if ($url !== null) {
            self::assertUrl($url, true);
        }
        $this->type = 'MediaSource';
        $this->fields = array(
            'mimeType' => $mimeType,
            'url' => $url ? (string) $url : null,
        );
    }

    /**
     * Returns content of card element
     *
     * @param float $version Card version
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function getContent($version)
    {
        if ($this->fields['mimeType'] === null) {
            throw new RuntimeException('MediaSource mimeType is required');
        }
        if ($this->fields['url'] === null) {
            throw new RuntimeException('MediaSource url is required');
        }
        return array(
            'mimeType' => $this->fields['mimeType'],
            'url' => $this->fields['url'],
        );
    }

    /**
     * Returns new instance with specified mime-type
     * Mime type of associated media (e.g. "video/mp4")
     *
     * @param string $mimeType Mime-type
     *
     * @return static
     */
    public function withMimeType($mimeType)
    {
        $mimeType = self::asString($mimeType, false, __METHOD__);
        return $this->with('mimeType', $mimeType);
    }

    /**
     * Returns new instance with specified url
     * URL to media.
     * Supports data URI in version 1.2+
     *
     * @param string|UriInterface $url Url
     *
     * @return static
     */
    public function withUrl($url)
    {
        self::assertUrl($url, true);
        return $this->with('url', (string) $url);
    }
}

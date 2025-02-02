<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Elements;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Media element
 *
 * CURRENTLY NOT SUPPORTED BY TEAMS
 *
 * @see https://adaptivecards.io/explorer/Media.html
 */
class Media extends AbstractElement
{
    /**
     * Constructor
     *
     * @param MediaSource[] $sources Sources
     */
    public function __construct(array $sources = array())
    {
        self::assertSources($sources);
        parent::__construct(array(
            'altText' => null,
            'poster' => null,
            'sources' => $sources,
        ), 'Media');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        if ($this->fields['sources'] === array()) {
            throw new RuntimeException('Element sources is empty');
        }

        $attrVersions = array(
            'altText' => 1.1,
            'poster' => 1.1,
            'sources' => 1.1,
        );

        $content = parent::getContent($version);
        foreach ($attrVersions as $name => $ver) {
            if ($version >= $ver) {
                /** @var mixed */
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
    }

    /**
     * Adds media
     *
     *    withAddedSource(MediaSource $mediaSource)
     *    withAddedSource($url, $mimeType)
     *
     * @param MediaSource|string $url      Media Url
     * @param string|null        $mimeType Mime-type
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withAddedSource($url, $mimeType = null)
    {
        $mediaSource = $url instanceof MediaSource
            ? $url
            : new MediaSource($url, $mimeType);
        return $this->withAdded('sources', $mediaSource);
    }

    /**
     * Return new instance with given alternative text
     *
     * @param string $altText Alternate text
     *
     * @return static
     */
    public function withAltText($altText)
    {
        return $this->with('altText', $altText);
    }

    /**
     * Return new instance with the given poster image
     *
     * URL of an image to display before playing.
     * Supports data URI in version 1.2+
     *
     * @param string|UriInterface $url URL of an image to display before playing.
     *
     * @return static
     */
    public function withPoster($url)
    {
        if ($url !== null) {
            self::assertUrl($url, true);
        }
        return $this->with('poster', $url ? (string) $url : null);
    }

    /**
     * Return new instance with sources replaced with supplied sources
     *
     * @param MediaSource[] $sources New sources
     *
     * @return Media
     *
     * @throws InvalidArgumentException
     */
    public function withSources(array $sources)
    {
        if ($sources === array()) {
            throw new InvalidArgumentException(\sprintf(
                '%s - Sources must be non-empty',
                __METHOD__
            ));
        }
        self::assertSources($sources);
        return $this->with('sources', $sources);
    }

    /**
     * Assert list of MediaSource
     *
     * @param MediaSource[] $sources list of media sources to test
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function assertSources($sources)
    {
        foreach ($sources as $i => $source) {
            if ($source instanceof MediaSource) {
                continue;
            }
            throw new InvalidArgumentException('Invalid source found at index ' . $i);
        }
    }
}

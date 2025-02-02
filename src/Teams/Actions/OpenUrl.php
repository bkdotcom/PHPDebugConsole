<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Actions;

use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * OpenUrl action
 *
 * @see https://adaptivecards.io/explorer/Action.OpenUrl.html
 */
class OpenUrl extends AbstractAction
{
    /**
     * Constructor
     *
     * @param string|UriInterface $url The url to open
     */
    public function __construct($url = null)
    {
        if ($url !== null) {
            self::assertUrl($url);
        }
        parent::__construct(array(
            'url' => $url ? (string) $url : null,
        ), 'Action.OpenUrl');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        if ($this->fields['url'] === null) {
            throw new RuntimeException('OpenUrl url is required');
        }
        $content = parent::getContent($version);
        /** @var string */
        $content['url'] = $this->fields['url'];
        return $content;
    }

    /**
     * Sets url
     *
     * @param string|UriInterface $url The url to open
     *
     * @return OpenUrl
     */
    public function withUrl($url)
    {
        self::assertUrl($url);
        return $this->with('url', (string) $url);
    }
}

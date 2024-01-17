<?php

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
        parent::__construct();
        $this->type = 'Action.OpenUrl';
        $this->fields = \array_merge($this->fields, array(
            'url' => $url ? (string) $url : null,
        ));
    }

    /**
     * Returns content of card action
     *
     * @param float $version Card version
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function getContent($version)
    {
        if ($this->fields['url'] === null) {
            throw new RuntimeException('OpenUrl url is required');
        }
        $content = parent::getContent($version);
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

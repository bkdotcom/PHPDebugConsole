<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Elements;

use bdk\Teams\Enums;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

/**
 * Common element methods
 */
trait CommonTrait
{
    /**
     * Return new instance with given backgroundImage
     *
     * @param string|UriInterface           $url                 Image url
     * @param Enums::FILLMODE_*             $fillmode            fill mode
     * @param Enums::HORIZONTAL_ALIGNMENT_* $horizontalAlignment horizontal alignment
     * @param Enums::VERTICAL_ALIGNMENT_*   $verticalAlignment   Vertical alignment
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withBackgroundImage($url, $fillmode = null, $horizontalAlignment = null, $verticalAlignment = null)
    {
        if ($url !== null) {
            self::assertUrl($url);
        }
        self::assertEnumValue($fillmode, 'FILLMODE_', 'fillmode');
        self::assertEnumValue($horizontalAlignment, 'HORIZONTAL_ALIGNMENT_', 'horizontalAlignment');
        self::assertEnumValue($verticalAlignment, 'VERTICAL_ALIGNMENT_', 'verticalAlignment');
        $backgroundImage = self::normalizeContent(array(
            'fillmode' => $fillmode,
            'horizontalAlignment' => $horizontalAlignment,
            'url' => $url ? (string) $url : null,
            'verticalContentAlignment' => $verticalAlignment,
        ));
        return \count($backgroundImage) > 1
            ? $this->with('backgroundImage', $backgroundImage)
            : $this->with('backgroundImage', $url);
    }
}

<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Elements;

use bdk\Teams\AbstractExtendableItem;

/**
 * The base object off of which "toggleable" items are built
 */
class AbstractToggleableItem extends AbstractExtendableItem
{
    /**
     * Constructor
     *
     * @param array<string,mixed> $fields Field values
     * @param string              $type   Item type
     */
    public function __construct(array $fields, $type)
    {
        $fields = \array_merge($this->fields, array(
            'id' => null,
            'isVisible' => null,
        ), $fields);
        parent::__construct($fields, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        $attrVersions = array(
            'id' => 1.0,
            'isVisible' => 1.2,
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
     * Return new instance with specified id value
     *
     * @param string $id A unique identifier associated with the item.
     *
     * @return static
     */
    public function withId($id)
    {
        return $this->with('id', $id);
    }

    /**
     * Sets isVisible flag
     * If false, this item will be removed from the visual tree.
     *
     * @param bool $isVisible Visible?
     *
     * @return static
     */
    public function withIsVisible($isVisible = true)
    {
        self::assertBool($isVisible, 'isVisible');
        return $this->with('isVisible', $isVisible);
    }
}

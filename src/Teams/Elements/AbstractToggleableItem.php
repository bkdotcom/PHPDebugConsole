<?php

declare(strict_types=1);

namespace bdk\Teams\Elements;

use bdk\Teams\AbstractExtendableItem;

/**
 *
 */
class AbstractToggleableItem extends AbstractExtendableItem
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->fields = \array_merge($this->fields, array(
            'id' => null,
            'isVisible' => null,
        ));
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

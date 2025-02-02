<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams;

/**
 * The base object off of which Elements and Actions are built
 */
class AbstractExtendableItem extends AbstractItem
{
    /**
     * Constructor
     *
     * @param array<string,mixed> $fields Field values
     * @param string              $type   Item type
     */
    public function __construct(array $fields, $type)
    {
        $fields = \array_merge(array(
            'requires' => array(),
        ), $fields);
        parent::__construct($fields, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        /** @var array<string,float> */
        $attrVersions = array(
            'requires' => 1.2,
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
     * A series of key/value pairs indicating features that
     * the item requires with corresponding minimum version.
     * When a feature is missing or of insufficient version, fallback is triggered
     *
     * @param array<string,float> $requires Required features
     *
     * @return static
     */
    public function withRequires(array $requires = array())
    {
        return $this->with('requires', $requires);
    }
}

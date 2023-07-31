<?php

namespace bdk\Teams;

/**
 * The base object off of which Elements and Actions are built
 */
class AbstractExtendableItem extends AbstractItem
{
    protected $fields = array(
        'requires' => array(),
    );

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        $attrVersions = array(
            'requires' => 1.2,
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
     * A series of key/value pairs indicating features that
     * the item requires with corresponding minimum version.
     * When a feature is missing or of insufficient version, fallback is triggered
     *
     * @param array $requires Required features
     *
     * @return static
     */
    public function withRequires(array $requires = array())
    {
        return $this->with('requires', $requires);
    }
}

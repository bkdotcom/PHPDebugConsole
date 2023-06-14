<?php

namespace bdk\Teams\Elements;

use bdk\Teams\AbstractItem;

/**
 * Fact
 *
 * @see https://adaptivecards.io/explorer/Fact.html
 */
class Fact extends AbstractItem
{
    /**
     * Constructor
     *
     * @param string $title Fact title
     * @param string $value Fact name
     */
    public function __construct($title, $value)
    {
        $this->type = 'Fact';
        $this->fields = array(
            'title' => self::asString($title, false, __METHOD__),
            'value' => self::asString($value, false, __METHOD__),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        return array(
            'title' => $this->fields['title'],
            'value' => $this->fields['value'],
        );
    }
}

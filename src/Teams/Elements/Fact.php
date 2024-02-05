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
     * @param string|int     $title Fact title
     * @param string|numeric $value Fact value
     */
    public function __construct($title, $value)
    {
        parent::__construct(array(
            'title' => self::asString($title, false, __METHOD__),
            'value' => self::asString($value, false, __METHOD__),
        ), 'Fact');
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

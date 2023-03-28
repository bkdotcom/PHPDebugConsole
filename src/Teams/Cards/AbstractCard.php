<?php

declare(strict_types=1);

namespace bdk\Teams\Cards;

use bdk\Teams\AbstractItem;

/**
 * Abstract card
 */
abstract class AbstractCard extends AbstractItem implements CardInterface
{
    protected $fields = array();

    /**
     * Returns card data
     *
     * @return array
     */
    abstract public function getMessage();

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->getMessage();
    }
}

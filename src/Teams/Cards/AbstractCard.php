<?php

namespace bdk\Teams\Cards;

use bdk\Teams\AbstractItem;

/**
 * Abstract card
 */
abstract class AbstractCard extends AbstractItem implements CardInterface
{
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

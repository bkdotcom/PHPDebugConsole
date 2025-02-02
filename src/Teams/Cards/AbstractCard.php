<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

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
